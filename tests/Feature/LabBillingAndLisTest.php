<?php

declare(strict_types=1);

use App\Domain\Lab\Actions\AmendReport;
use App\Domain\Lab\Actions\CancelLabBill;
use App\Domain\Lab\Actions\CollectSpecimen;
use App\Domain\Lab\Actions\CreateLabBill;
use App\Domain\Lab\Actions\CreateOrUpdateLabTest;
use App\Domain\Lab\Actions\ReceiveSpecimen;
use App\Domain\Lab\Actions\ReleaseReport;
use App\Domain\Lab\Actions\SaveResults;
use App\Domain\Lab\Actions\VerifyResults;
use App\Domain\Lab\Exceptions\InvalidStateTransitionException;
use App\Domain\Lab\Models\LabCriticalAcknowledgement;
use App\Domain\Lab\Models\LabOrder;
use App\Domain\Lab\Models\LabParameter;
use App\Domain\Lab\Models\LabPriceList;
use App\Domain\Lab\Models\LabPriceListItem;
use App\Domain\Lab\Models\LabReferenceRange;
use App\Domain\Lab\Models\LabReport;
use App\Domain\Lab\Services\BillNumberGenerator;
use App\Domain\Lab\Services\FormulaEvaluator;
use App\Domain\Lab\Services\ReferenceRangeResolver;
use App\Domain\Lab\States\LabState;
use App\Models\Legacy\BillingPaymentType;
use App\Models\Legacy\HospitalService;
use App\Models\Legacy\LabInvestigation;
use App\Models\Legacy\Patient;
use App\Models\Legacy\PatientBilling;
use App\Models\Legacy\PatientGenBill;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    // Ensure test patient exists
    $this->patient = Patient::first() ?? Patient::create([
        'uhid' => 888801,
        'prefix' => 'Ms.',
        'Fname' => 'Alice',
        'Lname' => 'Smith',
        'age' => 28,
        'sex' => 'female',
        'i_cover' => 0,
        'mobile_no' => 9876543210,
        'mnvs_otp' => 0,
        'login_id' => 'TEST',
        'vpo' => 'City',
        'houseno' => 1,
        'street' => 'Street',
        'city' => 'City',
        'district' => 'District',
        'state' => 'State',
        'pin' => 110001,
        'email_id1' => 'alice@test.local',
        'email_id2' => '',
        'Mothername' => '',
        'dump' => 0,
        'passcode' => '0',
        'user_p_level' => 1,
        'doctor_id' => 1,
        'org_name' => 'Test Hospital',
        'hospital_id' => 1,
        'opr_name' => 'Admin',
        'opr_id' => 1,
        'mars_passcode' => '',
        'date' => Carbon::today(),
        'time' => Carbon::now()->format('H:i:s'),
        'admission_status' => 0,
        'm_count' => 0,
        'p_lang' => 1,
        'global_euid' => 0,
        'abha_add' => '',
        'abha_mobile' => '',
        'first_reg_charge' => 0.00,
    ]);

    // Use existing real hospital services from the legacy schema
    $this->serviceA = HospitalService::where('flag', 'test')->where('active', 1)->firstOrFail();
    $this->serviceB = HospitalService::where('flag', 'test')->where('active', 1)->where('id', '!=', $this->serviceA->id)->firstOrFail();

    // Setup Price List
    $priceList = LabPriceList::firstOrCreate(
        ['payer_category' => 'general'],
        ['name' => 'General Payer Price List', 'is_active' => true]
    );

    LabPriceListItem::updateOrCreate(
        ['price_list_id' => $priceList->id, 'service_id' => $this->serviceA->id],
        ['charge' => 350.00]
    );
    LabPriceListItem::updateOrCreate(
        ['price_list_id' => $priceList->id, 'service_id' => $this->serviceB->id],
        ['charge' => 150.00]
    );
});

test('1. bill total, item discount and server pricing calculation', function () {
    $createAction = app(CreateLabBill::class);

    $order = $createAction->execute([
        'patient_id' => $this->patient->id,
        'payer_category' => 'general',
        'priority' => 'routine',
        'items' => [
            ['service_id' => $this->serviceA->id, 'discount' => 50.00], // 350 - 50 = 300
            ['service_id' => $this->serviceB->id, 'discount' => 0.00],  // 150
        ],
        'bill_discount' => 20.00, // Additional bill discount: net = 430
        'payments' => [
            ['payment_mode' => 'cash', 'amount' => 200.00],
            ['payment_mode' => 'upi', 'amount' => 230.00, 'transaction_id' => 'UPI999'],
        ],
        'user_id' => 1,
    ]);

    expect($order)->toBeInstanceOf(LabOrder::class);
    expect($order->status)->toBe(LabState::ORDERED);
    expect($order->bill)->not->toBeNull();

    // Verify Legacy Bill
    $legacyBill = PatientBilling::find($order->bill_id);
    expect((float) $legacyBill->discount)->toBe(70.00); // 50 item + 20 bill discount

    // Verify Legacy Gen Bills
    $genBills = PatientGenBill::where('bill_id', $legacyBill->id)->get();
    expect($genBills)->toHaveCount(2);

    $item1 = $genBills->firstWhere('service_id', $this->serviceA->id);
    expect((float) $item1->charges)->toBe(350.00);
    expect((float) $item1->discount)->toBe(50.00);

    // Verify Split Payments
    $payments = BillingPaymentType::where('bill_id', $legacyBill->id)->get();
    expect($payments)->toHaveCount(2);
    expect((float) $payments->sum('amount'))->toBe(430.00);
});

test('2. idempotent bill creation with client_token', function () {
    $createAction = app(CreateLabBill::class);
    $token = 'idempotent_test_token_'.uniqid();

    $payload = [
        'client_token' => $token,
        'patient_id' => $this->patient->id,
        'items' => [
            ['service_id' => $this->serviceB->id],
        ],
        'user_id' => 1,
    ];

    $order1 = $createAction->execute($payload);
    $order2 = $createAction->execute($payload);

    expect($order1->id)->toBe($order2->id);
    expect($order1->accession_no)->toBe($order2->accession_no);
    expect(LabOrder::where('client_token', $token)->count())->toBe(1);
});

test('3. bill-number uniqueness under sequential generation', function () {
    $generator = new BillNumberGenerator;
    $fy = '2026-27';

    $numbers = [];
    for ($i = 0; $i < 10; $i++) {
        $num = $generator->nextNumber('LB_TEST', $fy);
        expect($numbers)->not->toContain($num);
        $numbers[] = $num;
    }

    expect(count($numbers))->toBe(10);
    expect(end($numbers))->toBe(reset($numbers) + 9);
});

test('4. every valid and invalid state transition enforcement', function () {
    $createAction = app(CreateLabBill::class);
    $order = $createAction->execute([
        'patient_id' => $this->patient->id,
        'items' => [['service_id' => $this->serviceA->id]],
        'user_id' => 1,
    ]);

    expect($order->status)->toBe(LabState::ORDERED);

    // Collect specimen
    $collectAction = app(CollectSpecimen::class);
    $specimen = $order->specimens->first();
    $collectAction->execute($specimen->barcode, 1);
    expect($order->fresh()->status)->toBe(LabState::COLLECTED);

    // Receive specimen
    $receiveAction = app(ReceiveSpecimen::class);
    $receiveAction->execute($specimen->barcode, 1);
    expect($order->fresh()->status)->toBe(LabState::RECEIVED);

    // Cannot jump from RECEIVED directly to RELEASED
    expect(fn () => $order->fresh()->status->transitionTo(LabState::RELEASED))
        ->toThrow(InvalidStateTransitionException::class);

    // Save results -> RESULTED
    $saveAction = app(SaveResults::class);
    $orderItem = $order->items->first();
    $param = LabParameter::where('service_id', $this->serviceA->id)->first();

    $saveAction->execute([
        'order_item_id' => $orderItem->id,
        'expected_row_version' => $orderItem->row_version,
        'results' => [['parameter_id' => $param->id, 'value' => '14.5']],
        'entered_by' => 1,
    ]);
    expect($order->fresh()->status)->toBe(LabState::RESULTED);

    // Cancelled is NOT allowed once resulted
    $cancelAction = app(CancelLabBill::class);
    expect(fn () => $cancelAction->execute(['order_id' => $order->id, 'reason' => 'Too late', 'approved_by' => 1]))
        ->toThrow(InvalidStateTransitionException::class);
});

test('5. reference range resolution by age and sex', function () {
    $resolver = app(ReferenceRangeResolver::class);

    $param = LabParameter::firstOrCreate(
        ['service_id' => 9991, 'short_code' => 'URIC_ACID'],
        ['name' => 'Uric Acid', 'decimal_places' => 1]
    );

    // Male range: 3.4 - 7.0
    LabReferenceRange::updateOrCreate(
        ['parameter_id' => $param->id, 'sex' => 'male', 'age_from_days' => 6570, 'age_to_days' => 43800],
        ['low' => 3.4, 'high' => 7.0, 'text_range' => '3.4 - 7.0']
    );

    // Female range: 2.4 - 6.0
    LabReferenceRange::updateOrCreate(
        ['parameter_id' => $param->id, 'sex' => 'female', 'age_from_days' => 6570, 'age_to_days' => 43800],
        ['low' => 2.4, 'high' => 6.0, 'text_range' => '2.4 - 6.0']
    );

    $resolvedMale = $resolver->resolve($param, 365 * 35, 'male');
    expect($resolvedMale['low'])->toBe(3.4);
    expect($resolvedMale['high'])->toBe(7.0);

    $resolvedFemale = $resolver->resolve($param, 365 * 35, 'female');
    expect($resolvedFemale['low'])->toBe(2.4);
    expect($resolvedFemale['high'])->toBe(6.0);
});

test('6. safe formula evaluation with Symfony ExpressionLanguage', function () {
    $evaluator = app(FormulaEvaluator::class);

    // Total Cholesterol / HDL ratio
    $ratio = $evaluator->evaluate('cholesterol / hdl', ['cholesterol' => 210, 'hdl' => 42], 2);
    expect($ratio)->toBe(5.0);

    // Safe error handling on division by zero
    $divZero = $evaluator->evaluate('val / zero', ['val' => 100, 'zero' => 0]);
    expect($divZero)->toBeNull();
});

test('7. critical flag requires clinical acknowledgement before verification', function () {
    $createAction = app(CreateLabBill::class);
    $order = $createAction->execute([
        'patient_id' => $this->patient->id,
        'items' => [['service_id' => $this->serviceB->id]],
        'user_id' => 1,
    ]);

    // Setup critical reference range: normal 0.6 - 1.2, critical high 4.0
    $param = LabParameter::firstOrCreate(
        ['service_id' => $this->serviceB->id, 'short_code' => 'CREAT_CRIT'],
        ['name' => 'Serum Creatinine Panic Test', 'decimal_places' => 2]
    );

    LabReferenceRange::updateOrCreate(
        ['parameter_id' => $param->id, 'sex' => 'female', 'age_from_days' => 0, 'age_to_days' => 43800],
        ['low' => 0.6, 'high' => 1.2, 'critical_low' => 0.2, 'critical_high' => 4.0, 'text_range' => '0.6 - 1.2']
    );

    $orderItem = $order->items->first();

    // Enter panic value: 7.5 (exceeds critical high 4.0 -> Flag HH)
    $saveAction = app(SaveResults::class);
    $saveRes = $saveAction->execute([
        'order_item_id' => $orderItem->id,
        'expected_row_version' => $orderItem->row_version,
        'results' => [['parameter_id' => $param->id, 'value' => '7.5']],
        'entered_by' => 1,
    ]);

    expect($saveRes['critical_count'])->toBe(1);
    $resultVal = $orderItem->resultValues()->first();
    expect($resultVal->flag)->toBe('HH');

    // Attempting to verify without acknowledgement throws ValidationException
    $verifyAction = app(VerifyResults::class);
    expect(fn () => $verifyAction->execute($order->id, 1))
        ->toThrow(ValidationException::class);

    // Acknowledge critical value
    LabCriticalAcknowledgement::create([
        'result_value_id' => $resultVal->id,
        'informed_person' => 'Dr. Sharma (ICU)',
        'informed_by' => 1,
        'informed_at' => Carbon::now(),
        'remarks' => 'Informed via phone, read-back confirmed',
    ]);

    // Now verification succeeds and releases report
    $verifiedOrder = $verifyAction->execute($order->id, 1);
    expect($verifiedOrder->status)->toBe(LabState::RELEASED);
});

test('8. report amendment creates version N+1 and preserves version N', function () {
    $createAction = app(CreateLabBill::class);
    $order = $createAction->execute([
        'patient_id' => $this->patient->id,
        'items' => [['service_id' => $this->serviceA->id]],
        'user_id' => 1,
    ]);

    $param = LabParameter::firstOrCreate(
        ['service_id' => $this->serviceA->id, 'short_code' => 'PARAM_V1'],
        ['name' => 'Test Param']
    );

    $orderItem = $order->items->first();
    app(SaveResults::class)->execute([
        'order_item_id' => $orderItem->id,
        'expected_row_version' => $orderItem->row_version,
        'results' => [['parameter_id' => $param->id, 'value' => '10']],
        'entered_by' => 1,
    ]);

    // Release Version 1
    $v1Report = app(ReleaseReport::class)->execute($order->id, 1);
    expect($v1Report->version)->toBe(1);
    expect($v1Report->isAmended())->toBeFalse();

    // Amend report -> Version 2
    $amendAction = app(AmendReport::class);
    $v2Report = $amendAction->execute($order->id, 1, 'Doctor requested repeat check on specimen');

    expect($v2Report->version)->toBe(2);
    expect($v2Report->amended_from_id)->toBe($v1Report->id);
    expect($v2Report->isAmended())->toBeTrue();

    // Both reports exist in database
    expect(LabReport::where('lab_order_id', $order->id)->count())->toBe(2);
});

test('9. PDF immutability: reprint serves stored file with matching SHA-256', function () {
    $createAction = app(CreateLabBill::class);
    $order = $createAction->execute([
        'patient_id' => $this->patient->id,
        'items' => [['service_id' => $this->serviceA->id]],
        'user_id' => 1,
    ]);

    $report = app(ReleaseReport::class)->execute($order->id, 1);

    expect($report->pdf_path)->not->toBeNull();
    expect($report->pdf_sha256)->not->toBeNull();
    expect(Storage::disk('local')->exists($report->pdf_path))->toBeTrue();

    // Check that stored file hash matches
    $storedContent = Storage::disk('local')->get($report->pdf_path);
    expect(hash('sha256', $storedContent))->toBe($report->pdf_sha256);
});

test('9b. lab report renders single report per page with page break for subsequent reports', function () {
    $createAction = app(CreateLabBill::class);
    // Order with two separate tests
    $order = $createAction->execute([
        'patient_id' => $this->patient->id,
        'items' => [
            ['service_id' => $this->serviceA->id],
            ['service_id' => $this->serviceB->id],
        ],
        'user_id' => 1,
    ]);

    $report = app(ReleaseReport::class)->execute($order->id, 1);

    $renderer = app(\App\Domain\Lab\Services\ReportRenderer::class);
    $html = $renderer->renderHtml($order, $report, true, false, null, null, null, false);

    // Verify each report has its own report-page container and a page-break divider in between
    expect($html)->toContain('class="report-page"');
    expect($html)->toContain('class="page-break"');
    expect(substr_count($html, 'class="report-page"'))->toBe(2);
    expect(substr_count($html, 'class="page-break"'))->toBe(1);

    // Verify single item filtering
    $firstItemId = $order->items->first()->id;
    $singleHtml = $renderer->renderHtml($order, $report, true, false, null, null, $firstItemId);
    expect(substr_count($singleHtml, 'class="report-page"'))->toBe(1);
    expect(substr_count($singleHtml, 'class="page-break"'))->toBe(0);
});

test('10. permission check denies unauthorized users', function () {
    $clerkUser = User::firstOrCreate(
        ['username' => 'clerk_only'],
        ['name' => 'Clerk Only', 'email' => 'clerk@test.local', 'password' => bcrypt('secret')]
    );
    $clerkUser->syncPermissions(['lab.bill.create']);

    expect($clerkUser->hasPermissionTo('lab.bill.create'))->toBeTrue();
    expect($clerkUser->hasPermissionTo('lab.result.verify'))->toBeFalse();
});

test('11. legacy dual-write consistency: syncs patient_billing, patient_gen_bill, and lab_investigations', function () {
    $createAction = app(CreateLabBill::class);
    $order = $createAction->execute([
        'patient_id' => $this->patient->id,
        'items' => [['service_id' => $this->serviceA->id]],
        'user_id' => 1,
    ]);

    // Check legacy patient_billing
    $billing = PatientBilling::find($order->bill_id);
    expect($billing)->not->toBeNull();
    expect($billing->is_settled)->toBeTrue();

    // Check legacy patient_gen_bill
    $orderItem = $order->items->first();
    $genBill = PatientGenBill::find($orderItem->gen_bill_id);
    expect($genBill)->not->toBeNull();
    expect($genBill->bill_id)->toBe($billing->id);

    // Check legacy lab_investigations (1:1 with patient_gen_bill.id)
    $inv = LabInvestigation::where('lab_order_id', $genBill->id)->first();
    expect($inv)->not->toBeNull();
    expect($inv->status)->toBe('collected');

    // Enter result and verify test_results JSON was synced to legacy row
    $param = LabParameter::where('service_id', $this->serviceA->id)->first();
    app(SaveResults::class)->execute([
        'order_item_id' => $orderItem->id,
        'expected_row_version' => $orderItem->row_version,
        'results' => [['parameter_id' => $param->id, 'value' => '13.8']],
        'entered_by' => 1,
    ]);

    $updatedInv = LabInvestigation::where('lab_order_id', $genBill->id)->first();
    expect($updatedInv->status)->toBe('submitted');
    expect($updatedInv->test_results)->not->toBeNull();

    $resultsData = $updatedInv->test_results;
    $decoded = is_array($resultsData) ? $resultsData : json_decode((string) $resultsData, true);
    expect($decoded)->toBeArray();
    expect(array_key_first($decoded))->toBe($this->serviceA->service);
});

test('12. billing receipt and barcode label render cleanly without lazy loading violations', function () {
    $createAction = app(CreateLabBill::class);
    $order = $createAction->execute([
        'patient_id' => $this->patient->id,
        'items' => [
            ['service_id' => $this->serviceA->id],
            ['service_id' => $this->serviceB->id],
        ],
        'payments' => [
            ['mode' => 'cash', 'amount' => 500.00],
        ],
        'user_id' => 1,
    ]);

    // Test Receipt endpoint
    $receiptResponse = $this->get(route('lab.billing.receipt', $order->id));
    $receiptResponse->assertOk();
    $receiptResponse->assertSee($order->accession_no);
    $receiptResponse->assertSee($this->serviceA->service);

    // Test Barcode label endpoint
    $barcodeResponse = $this->get(route('lab.billing.barcode', $order->id));
    $barcodeResponse->assertOk();
    $barcodeResponse->assertSee($order->accession_no);
});

test('14. order 1 pdf generation and html preview', function () {
    LabOrder::find(1)?->update(['status' => \App\Domain\Lab\States\LabState::RELEASED]);

    // Ensure standard computerized letterhead state for test baseline
    $setting = \App\Models\LabSetting::current();
    $setting->update([
        'use_letterhead_image' => false,
        'footer_image_path' => null,
    ]);

    $order = \App\Domain\Lab\Models\LabOrder::find(1);
    if ($order && $order->status !== \App\Domain\Lab\States\LabState::RELEASED) {
        $order->update(['status' => \App\Domain\Lab\States\LabState::RELEASED]);
    }

    $pdfResponse = $this->get('/lab/reports/1/pdf');
    $pdfResponse->assertOk();
    expect($pdfResponse->headers->get('content-type'))->toBe('application/pdf');
    expect($pdfResponse->headers->get('x-report-sha256'))->not->toBeNull();

    $htmlResponse = $this->get('/lab/reports/1/view');
    $htmlResponse->assertOk();
    $htmlResponse->assertSee('CENTRAL HOSPITAL LABORATORY');
    $htmlResponse->assertSee('report-footer-computerized');
    $htmlResponse->assertSee('Electronically Authenticated Diagnostic Report');

    // Test with graphic footer banner image
    $setting = \App\Models\LabSetting::current();
    $setting->update([
        'footer_image_path' => 'assets/files/signatures/sign_anita.png',
        'use_letterhead_image' => true,
    ]);

    $graphicHtmlResponse = $this->get('/lab/reports/1/view');
    $graphicHtmlResponse->assertOk();
    $graphicHtmlResponse->assertSee('report-footer-graphic');

    $setting->update([
        'footer_image_path' => null,
        'use_letterhead_image' => false,
    ]);
});

test('15. group test component expansion in result entry and dual-write save', function () {
    $createTest = app(CreateOrUpdateLabTest::class);

    $child1 = $createTest->execute([
        'name' => 'Child Test Alpha '.uniqid(),
        'short_code' => 'CTA',
        'is_group' => false,
        'parameters' => [
            [
                'name' => 'Alpha Parameter',
                'short_code' => 'ALPHA',
                'input_type' => 'numeric',
                'unit' => 'mg/dL',
            ],
        ],
    ]);

    $child2 = $createTest->execute([
        'name' => 'Child Test Beta '.uniqid(),
        'short_code' => 'CTB',
        'is_group' => false,
        'parameters' => [
            [
                'name' => 'Beta Parameter',
                'short_code' => 'BETA',
                'input_type' => 'numeric',
                'unit' => 'g/dL',
            ],
        ],
    ]);

    $groupSrv = $createTest->execute([
        'name' => 'Test Panel Profile '.uniqid(),
        'is_group' => true,
        'component_ids' => [$child1->id, $child2->id],
    ]);

    $param1 = LabParameter::where('service_id', $child1->id)->first();
    $param2 = LabParameter::where('service_id', $child2->id)->first();

    $createAction = app(CreateLabBill::class);
    $order = $createAction->execute([
        'patient_id' => $this->patient->id,
        'items' => [['service_id' => $groupSrv->id]],
        'user_id' => 1,
    ]);

    $entryResponse = $this->get(route('lab.results.entry', $order->id));
    $entryResponse->assertOk();
    $entryResponse->assertSee($child1->service);
    $entryResponse->assertSee($child2->service);
    $entryResponse->assertSee('Alpha Parameter');
    $entryResponse->assertSee('Beta Parameter');

    $orderItem = $order->items->first();
    $saveResponse = $this->postJson(route('lab.results.save'), [
        'order_item_id' => $orderItem->id,
        'row_version' => $orderItem->row_version,
        'results' => [
            ['parameter_id' => $param1->id, 'value' => '12.5'],
            ['parameter_id' => $param2->id, 'value' => '4.2'],
        ],
    ]);

    $saveResponse->assertOk();
    $saveResponse->assertJson(['success' => true]);

    $this->assertDatabaseHas('lab_result_values', [
        'order_item_id' => $orderItem->id,
        'parameter_id' => $param1->id,
        'value_numeric' => 12.5,
    ]);
    $this->assertDatabaseHas('lab_result_values', [
        'order_item_id' => $orderItem->id,
        'parameter_id' => $param2->id,
        'value_numeric' => 4.2,
    ]);

    // Verify dual-write to legacy lab_investigations
    $inv = LabInvestigation::where('lab_order_id', $orderItem->gen_bill_id)->first();
    expect($inv)->not->toBeNull();
    $decodedResults = is_array($inv->test_results) ? $inv->test_results : json_decode((string) $inv->test_results, true);
    expect($decodedResults)->toBeArray();
    expect($decodedResults)->toHaveKey($groupSrv->service);
    expect($decodedResults[$groupSrv->service])->toHaveKey((string) $param1->id);
    expect($decodedResults[$groupSrv->service])->toHaveKey((string) $param2->id);
    expect($decodedResults[$groupSrv->service][(string) $param1->id]['value'])->toBe('12.5');
    expect($decodedResults[$groupSrv->service][(string) $param2->id]['value'])->toBe('4.2');
});

test('16. daily summary bill report renders with date and status filters, KPIs, and settlement modal', function () {
    $createAction = app(CreateLabBill::class);
    $order = $createAction->execute([
        'patient_id' => $this->patient->id,
        'payer_category' => 'general',
        'items' => [
            ['service_id' => $this->serviceA->id, 'discount' => 50],
        ],
        'bill_discount' => 10,
        'payments' => [
            ['payment_mode' => 'cash', 'amount' => 100],
        ],
        'user_id' => 1,
    ]);

    expect($order->bill_id)->not->toBeNull();

    // 1. Test daily summary view renders with filters and KPIs without error
    $today = Carbon::now()->toDateString();
    $response = $this->get(route('lab.billing.daily-summary', [
        'from_date' => $today,
        'to_date' => $today,
        'status' => 'all',
    ]));
    $response->assertOk();
    $response->assertSee('All Bills');
    $response->assertSee('Total Bills');
    $response->assertSee('Total Net Billed');
    $response->assertSee('Total Collected');
    $response->assertSee('Balance Due');

    // 2. Test status filter = due
    $dueResponse = $this->get(route('lab.billing.daily-summary', [
        'from_date' => $today,
        'to_date' => $today,
        'status' => 'due',
    ]));
    $dueResponse->assertOk();

    // 3. Test bill details endpoint
    $detailsResponse = $this->getJson(route('lab.billing.details', $order->bill_id));
    $detailsResponse->assertOk();
    $detailsResponse->assertJson(['success' => true]);
    $detailsData = $detailsResponse->json('bill');
    $financials = $detailsResponse->json('financials');
    expect($detailsData['id'])->toBe($order->bill_id);
    expect($financials['due_amount'])->toBeGreaterThan(0);

    // 4. Test payment settlement endpoint
    $settleResponse = $this->postJson(route('lab.billing.settle-payment', $order->bill_id), [
        'amount' => $financials['due_amount'],
        'payment_mode' => 'upi',
        'transaction_id' => 'UPI-TEST-12345',
        'notes' => 'Settled in full via test',
    ]);
    $settleResponse->assertOk();
    $settleResponse->assertJson(['success' => true]);

    $pb = PatientBilling::find($order->bill_id);
    expect((bool) $pb->is_settled)->toBeTrue();

    // 5. Verify payment recorded in billing_payment_type
    $this->assertDatabaseHas('billing_payment_type', [
        'bill_id' => $order->bill_id,
        'amount' => $financials['due_amount'],
        'payment_mode' => 'upi',
    ]);
});

test('17. edit bill allows adding service, removing service with cancellation and soft delete, and editing payments', function () {
    $createAction = app(CreateLabBill::class);
    $order = $createAction->execute([
        'patient_id' => $this->patient->id,
        'payer_category' => 'general',
        'items' => [
            ['service_id' => $this->serviceA->id, 'discount' => 0],
        ],
        'bill_discount' => 0,
        'payments' => [
            ['payment_mode' => 'cash', 'amount' => 100],
        ],
        'user_id' => 1,
    ]);

    $billId = $order->bill_id;
    $existingGenBill = PatientGenBill::where('bill_id', $billId)->firstOrFail();
    $existingPayment = BillingPaymentType::where('bill_id', $billId)->firstOrFail();

    // 1. Add Service B, Remove Service A (mark as cancelled + soft delete), and edit payment details
    $response = $this->postJson(route('lab.billing.save-full', $billId), [
        'referral_doctor' => 'Dr. Sharma',
        'bill_discount' => 20.00,
        'services' => [
            // Removed existing Service A
            [
                'id' => $existingGenBill->id,
                'service_id' => $this->serviceA->id,
                'charges' => 350.00,
                'qty' => 1,
                'discount' => 0,
                'is_removed' => true,
            ],
            // Added new Service B
            [
                'id' => null,
                'service_id' => $this->serviceB->id,
                'charges' => 250.00,
                'qty' => 1,
                'discount' => 0,
                'is_removed' => false,
            ],
        ],
        'payments' => [
            // Edited existing payment
            [
                'id' => $existingPayment->id,
                'amount' => 150.00,
                'payment_mode' => 'upi',
                'transaction_id' => 'UPI-EDIT-999',
                'is_deleted' => false,
            ],
            // Added new payment
            [
                'id' => null,
                'amount' => 80.00,
                'payment_mode' => 'cash',
                'transaction_id' => null,
                'is_deleted' => false,
            ],
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    // 2. Verify Service A is cancelled and soft-deleted in patient_gen_bill
    $genBillA = PatientGenBill::find($existingGenBill->id);
    expect($genBillA->is_deleted)->toBe(1);
    expect($genBillA->is_cancelled)->toBe(1);
    expect($genBillA->service_status)->toBe('cancelled');
    expect($genBillA->cancelled_at)->not->toBeNull();

    // 3. Verify Service A is cancelled in legacy lab_investigations
    $invA = LabInvestigation::where('lab_order_id', $existingGenBill->id)->first();
    expect($invA)->not->toBeNull();
    expect($invA->status)->toBe('cancelled');

    // 4. Verify new Service B is created in patient_gen_bill and lab_investigations
    $genBillB = PatientGenBill::where('bill_id', $billId)
        ->where('service_id', $this->serviceB->id)
        ->where('is_deleted', 0)
        ->first();
    expect($genBillB)->not->toBeNull();
    expect((float) $genBillB->charges)->toBe(250.00);

    $invB = LabInvestigation::where('lab_order_id', $genBillB->id)->first();
    expect($invB)->not->toBeNull();
    expect($invB->status)->toBe('collected');

    // 5. Verify payments updated and added
    $this->assertDatabaseHas('billing_payment_type', [
        'id' => $existingPayment->id,
        'amount' => 150.00,
        'payment_mode' => 'upi',
        'transaction_id' => 'UPI-EDIT-999',
    ]);
    $this->assertDatabaseHas('billing_payment_type', [
        'bill_id' => $billId,
        'amount' => 80.00,
        'payment_mode' => 'cash',
    ]);

    // 6. Verify bill totals and settlement: Gross 250 - Disc 20 = Net 230. Paid: 150 + 80 = 230. Due = 0 -> is_settled = 1
    $pb = PatientBilling::find($billId);
    expect((float) $pb->discount)->toBe(20.00);
    expect((bool) $pb->is_settled)->toBeTrue();

    // 7. Verify updating service name and service on existing item directly
    $updateResp = $this->postJson(route('lab.billing.save-full', $billId), [
        'services' => [
            [
                'id' => $genBillB->id,
                'service_id' => $this->serviceA->id,
                'service_name' => 'Complete Blood Count (Updated)',
                'charges' => 400.00,
                'qty' => 1,
                'discount' => 0,
                'is_removed' => false,
            ],
        ],
    ]);
    $updateResp->assertOk();
    $genBillBUpdated = PatientGenBill::find($genBillB->id);
    expect($genBillBUpdated->service_id)->toBe($this->serviceA->id);
    expect((float) $genBillBUpdated->charges)->toBe(400.00);

    // 8. Verify single item remove endpoint
    $removeResp = $this->postJson(route('lab.billing.remove-item', ['bill' => $billId, 'item' => $genBillB->id]));
    $removeResp->assertOk();
    $removeResp->assertJson(['success' => true]);

    $genBillBAfter = PatientGenBill::find($genBillB->id);
    expect($genBillBAfter->is_deleted)->toBe(1);
    expect($genBillBAfter->is_cancelled)->toBe(1);
});

test('18. lab settings desk allows configuring lab name, gst, address, signatures, header/footer, and logo', function () {
    // 1. Check settings view loads
    $viewResp = $this->get(route('lab.settings.index'));
    $viewResp->assertOk();
    $viewResp->assertSee('Laboratory Settings');
    $viewResp->assertSee('Lab Profile');

    // 2. Post settings update
    $postResp = $this->post(route('lab.settings.update'), [
        'lab_name' => 'Metro Diagnostic & Pathology Lab',
        'tagline' => 'Advanced Clinical Diagnostics Center',
        'accreditation_text' => 'NABL & ISO Accredited Laboratory',
        'gst_number' => '07BBBBB1111B1Z9',
        'reg_number' => 'DL/LAB/2026/99',
        'mobile_no' => '9812345678',
        'phone_alt' => '011-45678901',
        'email' => 'metro@diagnostics.local',
        'website' => 'https://metrolab.local',
        'address_line1' => 'Sector 14, Main Market',
        'address_line2' => 'Gurugram, Haryana - 122001',
        'technician_name' => 'Kavita Verma',
        'technician_designation' => 'Senior Lab Technologist',
        'technician_degree' => 'M.Sc MLT',
        'pathologist_name' => 'Dr. Rajiv Gupta',
        'pathologist_designation' => 'Chief Pathologist',
        'pathologist_degree' => 'MD (Pathology), FICP',
        'show_barcode' => '1',
        'show_qr_code' => '1',
        'use_letterhead_image' => '0',
        'header_height' => 120,
        'footer_height' => 50,
        'report_disclaimer' => 'Test results validated electronically.',
        'receipt_footer_note' => 'Thank you for your visit.',
    ]);

    $postResp->assertRedirect(route('lab.settings.index'));

    // 3. Verify database persistence
    $this->assertDatabaseHas('lab_settings', [
        'lab_name' => 'Metro Diagnostic & Pathology Lab',
        'gst_number' => '07BBBBB1111B1Z9',
        'mobile_no' => '9812345678',
        'technician_name' => 'Kavita Verma',
        'pathologist_name' => 'Dr. Rajiv Gupta',
    ]);

    // 4. Verify report HTML reflects the newly saved lab name and doctor
    $order = LabOrder::first();
    if ($order) {
        $order->update(['status' => \App\Domain\Lab\States\LabState::RELEASED]);
        $reportViewResp = $this->get(route('lab.reports.view', $order->id));
        $reportViewResp->assertOk();
        $reportViewResp->assertSee('METRO DIAGNOSTIC &amp; PATHOLOGY LAB', false);
        $reportViewResp->assertSee('Dr. Rajiv Gupta');
        $reportViewResp->assertSee('Kavita Verma');
    }

    // Reset settings back to default
    \App\Models\LabSetting::current()->update([
        'lab_name' => 'Central Hospital Laboratory',
        'pathologist_name' => 'Dr. Sonali Kalyan',
        'technician_name' => 'Anita',
    ]);
});

test('19. walkin registration generates sequential uhid and compact specimen barcode SMP-%06d', function () {
    // Create walkin patient 1
    $createAction = app(CreateLabBill::class);

    $order1 = $createAction->execute([
        'walkin' => [
            'name' => 'Sequential Patient One',
            'age' => 32,
            'sex' => 'male',
            'phone' => '9876543210',
        ],
        'referral_doctor' => 'Dr. Sharma',
        'items' => [
            ['service_id' => $this->serviceA->id, 'discount' => 0],
        ],
        'payments' => [
            ['payment_mode' => 'cash', 'amount' => 350],
        ],
    ]);

    $order1->load(['patient', 'specimens']);
    expect($order1->patient)->not->toBeNull();
    $uhid1 = (int) $order1->patient->uhid;
    expect($uhid1)->toBeGreaterThan(0);

    $specimen1 = $order1->specimens->first();
    expect($specimen1)->not->toBeNull();
    expect($specimen1->barcode)->toMatch('/^SMP-\d+$/');

    // Create walkin patient 2 immediately after
    $order2 = $createAction->execute([
        'walkin' => [
            'name' => 'Sequential Patient Two',
            'age' => 28,
            'sex' => 'female',
            'phone' => '9876543211',
        ],
        'referral_doctor' => 'Dr. Verma',
        'items' => [
            ['service_id' => $this->serviceA->id, 'discount' => 0],
        ],
        'payments' => [
            ['payment_mode' => 'cash', 'amount' => 350],
        ],
    ]);

    $order2->load(['patient', 'specimens']);
    expect($order2->patient)->not->toBeNull();
    $uhid2 = (int) $order2->patient->uhid;
    expect($uhid2)->toBe($uhid1 + 1); // Strictly sequential!

    $specimen2 = $order2->specimens->first();
    expect($specimen2)->not->toBeNull();
    expect($specimen2->barcode)->toMatch('/^SMP-\d+$/');
});
