<?php

use App\Domain\Lab\Actions\CreateLabBill;
use App\Domain\Lab\Actions\ReleaseReport;
use App\Domain\Lab\Services\ReportRenderer;
use App\Models\LabSetting;
use App\Models\Legacy\HospitalDepartment;
use App\Models\Legacy\HospitalService;
use App\Models\Legacy\Patient;
use App\Models\User;

beforeEach(function () {
    $this->patient = Patient::first() ?? Patient::create([
        'uhid' => 888801,
        'prefix' => 'Mr.',
        'Fname' => 'Combine',
        'Lname' => 'Patient',
        'age' => 45,
        'sex' => 'M',
        'mobile_no' => '9876543210',
        'i_cover' => '',
    ]);

    $this->serviceA = HospitalService::where('flag', 'test')->where('active', 1)->firstOrFail();
    $this->serviceB = HospitalService::where('flag', 'test')->where('active', 1)->where('id', '!=', $this->serviceA->id)->firstOrFail();

    $this->adminUser = User::first() ?? User::factory()->create();
    LabSetting::current()->update(['combine_reports_on_print' => false]);
});

afterEach(function () {
    LabSetting::current()->update(['combine_reports_on_print' => false]);
});

test('lab setting updates combine_reports_on_print configuration', function () {
    $this->actingAs($this->adminUser);

    $setting = LabSetting::current();

    // Enable combine report print
    $res = $this->post(route('lab.settings.update'), [
        'lab_name' => $setting->lab_name,
        'mobile_no' => $setting->mobile_no,
        'technician_name' => $setting->technician_name,
        'technician_designation' => $setting->technician_designation,
        'pathologist_name' => $setting->pathologist_name,
        'pathologist_designation' => $setting->pathologist_designation,
        'combine_reports_on_print' => 1,
    ]);

    $res->assertSessionHasNoErrors();
    $setting->refresh();
    expect($setting->combine_reports_on_print)->toBeTrue();

    // Disable combine report print
    $res2 = $this->post(route('lab.settings.update'), [
        'lab_name' => $setting->lab_name,
        'mobile_no' => $setting->mobile_no,
        'technician_name' => $setting->technician_name,
        'technician_designation' => $setting->technician_designation,
        'pathologist_name' => $setting->pathologist_name,
        'pathologist_designation' => $setting->pathologist_designation,
        'combine_reports_on_print' => 0,
    ]);

    $res2->assertSessionHasNoErrors();
    $setting->refresh();
    expect($setting->combine_reports_on_print)->toBeFalse();
});

test('2 billed reports come in one page when combine_reports_on_print is enabled', function () {
    $setting = LabSetting::current();
    $setting->update(['combine_reports_on_print' => true]);

    $createAction = app(CreateLabBill::class);
    $order = $createAction->execute([
        'patient_id' => $this->patient->id,
        'items' => [
            ['service_id' => $this->serviceA->id],
            ['service_id' => $this->serviceB->id],
        ],
        'user_id' => $this->adminUser->id,
    ]);

    $report = app(ReleaseReport::class)->execute($order->id, $this->adminUser->id);
    $order->refresh();

    $renderer = app(ReportRenderer::class);
    $html = $renderer->renderHtml($order, $report, true, false);

    // Combined on single page: 1 report-page container, 0 page-break dividers
    expect(substr_count($html, 'class="report-page"'))->toBe(1);
    expect(substr_count($html, 'class="page-break"'))->toBe(0);
    // Table header (Parameter, Value, Unit, Biological Reference Range) appears ONLY ONCE
    expect(substr_count($html, 'Biological Reference Range</th>'))->toBe(1);
    expect(substr_count($html, 'Parameter</th>'))->toBe(1);
    expect($html)->toContain($this->serviceA->service);
    expect($html)->toContain($this->serviceB->service);
    // QR code is removed from the report
    expect($html)->not->toContain('Scan to Verify Online');
    expect($html)->not->toContain('alt="Scan to Verify"');
});

test('2 billed reports come on separate pages when combine_reports_on_print is disabled', function () {
    $setting = LabSetting::current();
    $setting->update(['combine_reports_on_print' => false]);

    $createAction = app(CreateLabBill::class);
    $order = $createAction->execute([
        'patient_id' => $this->patient->id,
        'items' => [
            ['service_id' => $this->serviceA->id],
            ['service_id' => $this->serviceB->id],
        ],
        'user_id' => $this->adminUser->id,
    ]);

    $report = app(ReleaseReport::class)->execute($order->id, $this->adminUser->id);
    $order->refresh();

    $renderer = app(ReportRenderer::class);
    $html = $renderer->renderHtml($order, $report, true, false);

    // Separate mode: 2 report-page containers, 1 page-break divider
    expect(substr_count($html, 'class="report-page"'))->toBe(2);
    expect(substr_count($html, 'class="page-break"'))->toBe(1);
    expect(substr_count($html, 'Biological Reference Range</th>'))->toBe(2);
});

test('URL query parameter combine=1 or combine=0 dynamically overrides the lab setting', function () {
    $setting = LabSetting::current();
    $setting->update(['combine_reports_on_print' => false]);

    $createAction = app(CreateLabBill::class);
    $order = $createAction->execute([
        'patient_id' => $this->patient->id,
        'items' => [
            ['service_id' => $this->serviceA->id],
            ['service_id' => $this->serviceB->id],
        ],
        'user_id' => $this->adminUser->id,
    ]);

    $report = app(ReleaseReport::class)->execute($order->id, $this->adminUser->id);
    $order->refresh();

    $renderer = app(ReportRenderer::class);

    // Setting is false, but passing combine=true overrides it to 1 page
    $htmlCombined = $renderer->renderHtml($order, $report, true, false, null, null, null, true);
    expect(substr_count($htmlCombined, 'class="report-page"'))->toBe(1);
    expect(substr_count($htmlCombined, 'class="page-break"'))->toBe(0);

    // Setting is true, but passing combine=false overrides it to 2 pages
    $setting->update(['combine_reports_on_print' => true]);
    $htmlSeparate = $renderer->renderHtml($order, $report, true, false, null, null, null, false);
    expect(substr_count($htmlSeparate, 'class="report-page"'))->toBe(2);
    expect(substr_count($htmlSeparate, 'class="page-break"'))->toBe(1);

    // Reset back to false
    $setting->update(['combine_reports_on_print' => false]);
});

test('signatures only appear on approved reports and are omitted on unapproved draft reports', function () {
    // Create a dummy signature file
    $dummySignPath = public_path('test_dummy_signature.png');
    file_put_contents($dummySignPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

    $setting = LabSetting::current();
    $setting->update([
        'technician_name' => 'Lab Tech John',
        'technician_degree' => 'B.Sc MLT',
        'pathologist_name' => 'Dr. Jane Pathologist',
        'pathologist_degree' => 'MD Pathology',
        'technician_signature_path' => 'test_dummy_signature.png',
        'pathologist_signature_path' => 'test_dummy_signature.png',
        'use_letterhead_image' => false,
        'combine_reports_on_print' => false,
    ]);

    $createAction = app(CreateLabBill::class);
    $order = $createAction->execute([
        'patient_id' => $this->patient->id,
        'items' => [
            ['service_id' => $this->serviceA->id],
        ],
        'user_id' => $this->adminUser->id,
    ]);

    $renderer = app(ReportRenderer::class);

    // 1. Unapproved draft report (order is in ORDERED / RESULTED state, not released or verified)
    $dummyReport = new \App\Domain\Lab\Models\LabReport();
    $dummyReport->order_id = $order->id;
    $dummyReport->version = 1;
    $dummyReport->report_number = 'REP-DRAFT-001';

    $htmlDraft = $renderer->renderHtml($order, $dummyReport, true, false);
    expect($htmlDraft)->not->toContain('alt="Signature Lab Tech John"');
    expect($htmlDraft)->not->toContain('alt="Signature Dr. Jane Pathologist"');
    expect($htmlDraft)->not->toContain('Lab Tech John');
    expect($htmlDraft)->not->toContain('B.Sc MLT');
    expect($htmlDraft)->not->toContain('Dr. Jane Pathologist');
    expect($htmlDraft)->not->toContain('MD Pathology');
    expect($htmlDraft)->not->toContain('Draft / Interim Report — Signatures & Approval Pending');
    expect($htmlDraft)->toContain('Draft / Interim Report • Signatures Pending Approval');
    expect($htmlDraft)->not->toContain('Electronically Authenticated Diagnostic Report');

    // 2. Approved report (released order)
    $report = app(ReleaseReport::class)->execute($order->id, $this->adminUser->id);
    $order->refresh();

    $htmlApproved = $renderer->renderHtml($order, $report, true, false);
    expect($htmlApproved)->toContain('alt="Signature Lab Tech John"');
    expect($htmlApproved)->toContain('alt="Signature Dr. Jane Pathologist"');
    expect($htmlApproved)->toContain('Lab Tech John');
    expect($htmlApproved)->toContain('B.Sc MLT');
    expect($htmlApproved)->toContain('Dr. Jane Pathologist');
    expect($htmlApproved)->toContain('MD Pathology');
    expect($htmlApproved)->toContain('Electronically Authenticated Diagnostic Report');
    expect($htmlApproved)->not->toContain('Draft / Interim Report • Signatures Pending Approval');

    // Clean up
    if (file_exists($dummySignPath)) {
        unlink($dummySignPath);
    }
    $setting->update([
        'technician_signature_path' => null,
        'pathologist_signature_path' => null,
        'combine_reports_on_print' => false,
    ]);
});

test('header, patient details, signatures, and footer are common across all pages in multi-page combined reports', function () {
    $setting = LabSetting::current();
    $setting->update([
        'combine_reports_on_print' => true,
        'use_letterhead_image' => false,
        'footer_image_path' => null,
    ]);

    // Take 17 services to exceed single page capacity (17 * 2 = 34 rows > 30 per page)
    $services = HospitalService::where('flag', 'test')->where('active', 1)->take(17)->get();
    $items = $services->map(fn($s) => ['service_id' => $s->id])->all();

    $createAction = app(CreateLabBill::class);
    $order = $createAction->execute([
        'patient_id' => $this->patient->id,
        'items' => $items,
        'user_id' => $this->adminUser->id,
    ]);

    $report = app(ReleaseReport::class)->execute($order->id, $this->adminUser->id);
    $order->refresh();

    $renderer = app(ReportRenderer::class);
    $html = $renderer->renderHtml($order, $report, true, false);

    // Spans 2 pages because 7 tests exceeds the single-page limit of 16 rows
    expect(substr_count($html, 'class="report-page"'))->toBe(2);
    expect(substr_count($html, 'class="page-break"'))->toBe(1);

    // Common across ALL pages:
    // 1. Header (Acc. No) appears on every page
    expect(substr_count($html, 'Acc. No: ' . $order->accession_no))->toBe(2);
    // 2. Patient Demographic Details box appears on every page
    expect(substr_count($html, 'class="patient-demographic-box"'))->toBe(2);
    // 3. Table Header (Parameter | Value | Unit | Biological Reference Range) appears on every page
    expect(substr_count($html, 'Biological Reference Range</th>'))->toBe(2);
    // 4. Signatures table appears on every page
    expect(substr_count($html, 'class="signature-table"'))->toBe(2);
    // 5. Footer appears on every page
    expect(substr_count($html, 'Electronically Authenticated Diagnostic Report'))->toBe(2);
    // 6. End of report divider appears only on the final page
    expect(substr_count($html, '*** End of Report ***'))->toBe(1);

    $setting->update(['combine_reports_on_print' => false]);
});
