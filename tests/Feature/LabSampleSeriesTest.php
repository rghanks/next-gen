<?php

declare(strict_types=1);

use App\Domain\Lab\Actions\CreateLabBill;
use App\Domain\Lab\Models\LabOrder;
use App\Domain\Lab\Models\Specimen;
use App\Domain\Lab\Services\AccessionGenerator;
use App\Models\LabSetting;
use App\Models\Legacy\HospitalService;
use App\Models\Legacy\Patient;
use Illuminate\Validation\ValidationException;

test('accession generator accepts custom sample number and restarts series from there', function () {
    $generator = app(AccessionGenerator::class);

    // Set custom sample number 500
    $barcode1 = $generator->generateBarcode('blood', null, '500');
    expect($barcode1)->toBe('SMP-500');

    // Next auto-generated barcodes continue the series sequentially
    $barcode2 = $generator->generateBarcode();
    expect($barcode2)->toBe('SMP-501');

    $barcode3 = $generator->generateBarcode();
    expect($barcode3)->toBe('SMP-502');
});

test('accession generator accepts alphanumeric custom sample number e.g. SMP-000750', function () {
    $generator = app(AccessionGenerator::class);

    $barcode1 = $generator->generateBarcode('blood', null, 'SMP-000750');
    expect($barcode1)->toBe('SMP-000750');

    $barcode2 = $generator->generateBarcode();
    expect($barcode2)->toBe('SMP-751');
});

test('setting next sample number directly starts series from specified number', function () {
    $generator = app(AccessionGenerator::class);

    $generator->setNextSampleNumber(1000, 'SMP');
    expect($generator->previewNextBarcode())->toBe('SMP-1000');

    $barcode = $generator->generateBarcode();
    expect($barcode)->toBe('SMP-1000');

    $nextBarcode = $generator->generateBarcode();
    expect($nextBarcode)->toBe('SMP-1001');
});

test('billing desk creates order with custom sample number and starts series for subsequent orders', function () {
    $createLabBill = app(CreateLabBill::class);

    $patient = Patient::first() ?? Patient::create([
        'uhid' => 'UHID-SAMPLE-01',
        'Fname' => 'Sample',
        'Lname' => 'Tester',
        'age' => 30,
        'sex' => 'male',
        'mobile_no' => 9876543210,
        'hospital_id' => 1,
        'login_id' => 'ADMIN',
    ]);

    $service = HospitalService::where('active', 1)->first() ?? HospitalService::create([
        'service' => 'Complete Blood Count',
        'short_code' => 'CBC',
        'active' => 1,
        'is_group' => 0,
    ]);

    // Order 1: entered with custom sample number 2000
    $order1 = $createLabBill->execute([
        'patient_id' => $patient->id,
        'custom_sample_no' => '2000',
        'items' => [
            ['service_id' => $service->id, 'discount' => 0],
        ],
        'payments' => [
            ['payment_mode' => 'cash', 'amount' => 100],
        ],
    ]);

    expect($order1->specimens->first()->barcode)->toBe('SMP-2000');

    // Order 2: created without custom sample number (auto series)
    $order2 = $createLabBill->execute([
        'patient_id' => $patient->id,
        'items' => [
            ['service_id' => $service->id, 'discount' => 0],
        ],
        'payments' => [
            ['payment_mode' => 'cash', 'amount' => 100],
        ],
    ]);

    expect($order2->specimens->first()->barcode)->toBe('SMP-2001');

    // Order 3: created without custom sample number
    $order3 = $createLabBill->execute([
        'patient_id' => $patient->id,
        'items' => [
            ['service_id' => $service->id, 'discount' => 0],
        ],
        'payments' => [
            ['payment_mode' => 'cash', 'amount' => 100],
        ],
    ]);

    expect($order3->specimens->first()->barcode)->toBe('SMP-2002');
});

test('cannot assign duplicate sample number to another specimen', function () {
    $generator = app(AccessionGenerator::class);
    $order = LabOrder::first();

    if ($order && $order->specimens->isNotEmpty()) {
        $existingBarcode = $order->specimens->first()->barcode;

        // Trying to generate already existing barcode should throw ValidationException
        expect(fn () => $generator->generateBarcode('blood', null, $existingBarcode))
            ->toThrow(ValidationException::class);
    }
});

test('api endpoint set-sample-series updates starting number and returns next preview', function () {
    $response = $this->postJson(route('lab.orders.set-sample-series'), [
        'next_number' => 8888,
        'prefix' => 'SMP',
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'next_sample_no' => 'SMP-8888',
    ]);

    $previewResponse = $this->getJson(route('lab.orders.next-sample-no'));
    $previewResponse->assertOk();
    $previewResponse->assertJson([
        'success' => true,
        'next_sample_no' => 'SMP-8888',
    ]);
});

test('api endpoint update-sample-no updates specimen barcode and restarts series', function () {
    $order = LabOrder::with('specimens')->first();

    if ($order && $order->specimens->isNotEmpty()) {
        $response = $this->postJson(route('lab.orders.update-sample-no'), [
            'order_id' => $order->id,
            'custom_sample_no' => '9500',
            'start_series' => true,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'barcode' => 'SMP-9500',
        ]);

        $order->refresh();
        expect($order->specimens->first()->barcode)->toBe('SMP-9500');

        // Next preview should be 9501
        $preview = app(AccessionGenerator::class)->previewNextBarcode();
        expect($preview)->toBe('SMP-9501');
    }
});

test('settings one-time setup locks prefix and sample no as readonly and starts series from that number', function () {
    // Clear out any uncollected test orders so sequence starts clean
    LabOrder::where('status', \App\Domain\Lab\States\LabState::ORDERED->value)->delete();

    $setting = LabSetting::current();
    $setting->update([
        'is_sample_series_locked' => false,
        'sample_no_prefix' => null,
        'starting_sample_no' => null,
        'sample_no_digits' => 0,
        'sample_no_format' => 'prefix_unpadded',
    ]);

    // Perform one-time setup: prefix "LAB", sample_no 3000
    $response = $this->postJson(route('lab.settings.lock-sample-series'), [
        'sample_no' => 3000,
        'sample_no_prefix' => 'LAB',
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'is_locked' => true,
        'starting_sample_no' => 3000,
        'sample_no_prefix' => 'LAB',
    ]);

    $setting->refresh();
    expect($setting->is_sample_series_locked)->toBeTrue();
    expect($setting->starting_sample_no)->toBe(3000);
    expect($setting->sample_no_prefix)->toBe('LAB');

    // Generating barcode starts exactly at 3000
    $generator = app(AccessionGenerator::class);
    $firstBarcode = $generator->generateBarcode();
    expect($firstBarcode)->toBe('LAB-3000');

    // Next barcode automatically increments
    $secondBarcode = $generator->generateBarcode();
    expect($secondBarcode)->toBe('LAB-3001');

    // Attempting to lock again is rejected
    $secondLockAttempt = $this->postJson(route('lab.settings.lock-sample-series'), [
        'sample_no' => 4000,
        'sample_no_prefix' => 'DIFF',
    ]);
    $secondLockAttempt->assertStatus(422);

    // Attempting to change prefix via settings update is ignored/protected because it is readonly/locked
    $this->post(route('lab.settings.update'), [
        'lab_name' => 'Central Hospital Laboratory',
        'technician_name' => 'Anita',
        'technician_designation' => 'Lab Technician',
        'pathologist_name' => 'Dr. Sonali Kalyan',
        'pathologist_designation' => 'Consultant Pathologist',
        'sample_no_prefix' => 'TAMPERED',
        'sample_no' => 9999,
    ]);

    $setting->refresh();
    expect($setting->sample_no_prefix)->toBe('LAB');
    expect($setting->starting_sample_no)->toBe(3000);
    expect($setting->is_sample_series_locked)->toBeTrue();
});

