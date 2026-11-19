<?php

declare(strict_types=1);

use App\Domain\Lab\Models\LabParameter;
use App\Domain\Lab\Models\LabPriceList;
use App\Domain\Lab\Models\LabPriceListItem;
use App\Domain\Lab\Models\LabReferenceRange;
use App\Models\Legacy\HospitalService;
use App\Models\Legacy\HospitalServiceCharge;
use App\Models\Legacy\InvestigationGroupTestIndex;

beforeEach(function () {
    // Clean up test services created during tests
    HospitalService::where('service', 'like', 'TEST_%')->delete();
});

test('1. test master index lists single tests and group profiles', function () {
    $response = $this->get(route('lab.masters.tests.index'));
    $response->assertOk();
    $response->assertSee('Lab Test');

    // Test filter tabs
    $singleResponse = $this->get(route('lab.masters.tests.index', ['tab' => 'single']));
    $singleResponse->assertOk();

    $groupResponse = $this->get(route('lab.masters.tests.index', ['tab' => 'group']));
    $groupResponse->assertOk();
});

test('2. create single test with strict dual-write to legacy tables and domain models', function () {
    $payload = [
        'name' => 'TEST_Serum Ferritin',
        'short_code' => 'TEST_FERR',
        'display_name' => 'Serum Ferritin Assay',
        'department' => 'Biochemistry',
        'is_group' => false,
        'tat' => 45,
        'report_time' => 12,
        'loinc_code' => '2276-4',
        'specimen_type' => 'SERUM',
        'container' => 'PLAIN_GEL',
        'sample_quantity' => 2.5,
        'sample_unit' => 'mL',
        'storage_instruction' => 'Refrigerate 2-8 C',
        'base_charge' => 480.00,
        'item_cost' => 85.00,
    ];

    $response = $this->postJson(route('lab.masters.tests.store'), $payload);
    $response->assertOk();
    $response->assertJson(['success' => true]);

    // 1. Check legacy hospital_services
    $service = HospitalService::where('service', 'TEST_Serum Ferritin')->first();
    expect($service)->not->toBeNull();
    expect($service->short_code)->toBe('TEST_FERR');
    expect($service->flag)->toBe('test');
    expect((bool) $service->is_group)->toBeFalse();
    expect((bool) $service->active)->toBeTrue();

    // Group attributes payload
    $attrs = is_array($service->group_attributes) ? $service->group_attributes : json_decode((string) $service->group_attributes, true);
    expect($attrs['tat'])->toBe(45);
    expect($attrs['sample']['vial'][0])->toBe('PLAIN_GEL');

    // 2. Check domain lab_parameters auto-provision
    $param = LabParameter::where('service_id', $service->id)->first();
    expect($param)->not->toBeNull();
    expect($param->name)->toBe('TEST_Serum Ferritin');
    expect($param->short_code)->toBe('TEST_FERR');

    // 3. Check legacy hospital_service_charges
    $charge = HospitalServiceCharge::where('service', $service->id)->first();
    expect($charge)->not->toBeNull();
    expect((float) $charge->charges)->toBe(480.00);

    // 4. Check domain lab_price_list_items
    $generalList = LabPriceList::where('payer_category', 'general')->first();
    expect($generalList)->not->toBeNull();
    $item = LabPriceListItem::where('price_list_id', $generalList->id)->where('service_id', $service->id)->first();
    expect($item)->not->toBeNull();
    expect((float) $item->charge)->toBe(480.00);
});

test('3. create group panel test with child components and sequence indexing', function () {
    // Pick two existing single tests
    $child1 = HospitalService::where('flag', 'test')->where('is_group', 0)->firstOrFail();
    $child2 = HospitalService::where('flag', 'test')->where('is_group', 0)->where('id', '!=', $child1->id)->firstOrFail();

    $payload = [
        'name' => 'TEST_Renal Function Panel',
        'short_code' => 'TEST_RFT',
        'department' => 'Biochemistry',
        'is_group' => true,
        'tat' => 90,
        'report_time' => 24,
        'specimen_type' => 'SERUM',
        'container' => 'PLAIN_GEL',
        'base_charge' => 650.00,
        'component_ids' => [$child1->id, $child2->id],
    ];

    $response = $this->postJson(route('lab.masters.tests.store'), $payload);
    $response->assertOk();

    $group = HospitalService::where('service', 'TEST_Renal Function Panel')->first();
    expect($group)->not->toBeNull();
    expect((bool) $group->is_group)->toBeTrue();
    expect($group->flag)->toBe('lab_package');

    // Check table_investigation_group_test_index
    $indexes = InvestigationGroupTestIndex::where('group_id', $group->id)->orderBy('sequence_no')->get();
    expect($indexes)->toHaveCount(2);
    expect($indexes[0]->test_id)->toBe($child1->id);
    expect($indexes[0]->sequence_no)->toBe(1);
    expect($indexes[1]->test_id)->toBe($child2->id);
    expect($indexes[1]->sequence_no)->toBe(2);
});

test('4. save parameters, formulas, and age-sex reference range matrix with dual-write', function () {
    $service = HospitalService::create([
        'service' => 'TEST_Glycated Hemoglobin',
        'short_code' => 'TEST_HBA1C',
        'flag' => 'test',
        'is_group' => 0,
        'active' => 1,
        'spec_id' => 1,
        'comment' => '',
        'brief_des' => '',
        'category_id' => 9,
    ]);

    $payload = [
        'name' => 'HbA1c Glycosylated Hemoglobin',
        'short_code' => 'HBA1C',
        'unit' => '%',
        'method' => 'HPLC Immunoassay',
        'input_type' => 'numeric',
        'decimal_places' => 1,
        'ranges' => [
            [
                'sex' => 'male',
                'group_name' => 'Adult',
                'age_from_days' => 4016,
                'age_to_days' => 43800,
                'low' => 4.0,
                'high' => 5.6,
                'critical_low' => 3.5,
                'critical_high' => 9.0,
            ],
            [
                'sex' => 'female',
                'group_name' => 'Adult',
                'age_from_days' => 4016,
                'age_to_days' => 43800,
                'low' => 4.0,
                'high' => 5.6,
                'critical_low' => 3.5,
                'critical_high' => 9.0,
            ],
        ],
    ];

    $response = $this->postJson(route('lab.masters.tests.parameters', $service->id), $payload);
    $response->assertOk();
    $response->assertJson(['success' => true]);

    // Check domain lab_parameters
    $param = LabParameter::where('service_id', $service->id)->first();
    expect($param)->not->toBeNull();
    expect($param->unit)->toBe('%');
    expect($param->decimal_places)->toBe(1);

    // Check domain lab_reference_ranges
    $ranges = LabReferenceRange::where('parameter_id', $param->id)->get();
    expect($ranges)->toHaveCount(2);

    $maleRange = $ranges->firstWhere('sex', 'male');
    expect((float) $maleRange->low)->toBe(4.0);
    expect((float) $maleRange->high)->toBe(5.6);
    expect((float) $maleRange->critical_high)->toBe(9.0);

    // Check legacy input_attributes dual write
    $service->refresh();
    $inputAttrs = is_array($service->input_attributes) ? $service->input_attributes : json_decode((string) $service->input_attributes, true);
    expect($inputAttrs)->toBeArray();
    expect($inputAttrs['unit'])->toBe('%');
    expect($inputAttrs['biological_reference']['Male']['Adult']['min'])->toBe('4');
    expect($inputAttrs['biological_reference']['Female']['Adult']['max'])->toBe('5.6');
});

test('5. save pricing across multiple payer categories with dual-write to hospital_service_charges', function () {
    $service = HospitalService::create([
        'service' => 'TEST_Thyroid Profile Total',
        'short_code' => 'TEST_TFT',
        'flag' => 'test',
        'is_group' => 1,
        'active' => 1,
        'spec_id' => 1,
        'comment' => '',
        'brief_des' => '',
        'category_id' => 9,
    ]);

    $payload = [
        'pricing' => [
            'general' => 500.00,
            'tpa' => 420.00,
            'cghs' => 380.00,
            'ayushman' => 300.00,
        ],
        'cost' => 110.00,
    ];

    $response = $this->postJson(route('lab.masters.tests.pricing', $service->id), $payload);
    $response->assertOk();
    $response->assertJson(['success' => true]);

    // Check legacy hospital_service_charges
    $charge = HospitalServiceCharge::where('service', $service->id)->first();
    expect($charge)->not->toBeNull();
    expect((float) $charge->charges)->toBe(500.00);
    expect((float) $charge->opd)->toBe(500.00);

    // Check domain lab_price_lists and items
    foreach (['general' => 500.00, 'tpa' => 420.00, 'cghs' => 380.00, 'ayushman' => 300.00] as $cat => $rate) {
        $pList = LabPriceList::where('payer_category', $cat)->first();
        expect($pList)->not->toBeNull();
        $pItem = LabPriceListItem::where('price_list_id', $pList->id)->where('service_id', $service->id)->first();
        expect($pItem)->not->toBeNull();
        expect((float) $pItem->charge)->toBe($rate);
    }
});

test('6. toggle active status of a test', function () {
    $service = HospitalService::create([
        'service' => 'TEST_Urine Microalbumin',
        'short_code' => 'TEST_MALB',
        'flag' => 'test',
        'is_group' => 0,
        'active' => 1,
        'spec_id' => 1,
        'comment' => '',
        'brief_des' => '',
        'category_id' => 9,
    ]);

    $response = $this->postJson(route('lab.masters.tests.toggle', $service->id));
    $response->assertOk();
    $response->assertJson(['success' => true, 'active' => false]);

    $service->refresh();
    expect($service->active)->toBe(0);

    // Toggle back
    $response2 = $this->postJson(route('lab.masters.tests.toggle', $service->id));
    $response2->assertOk();
    $response2->assertJson(['success' => true, 'active' => true]);

    $service->refresh();
    expect($service->active)->toBe(1);
});

test('7. edit view renders smoothly for single tests and group profiles without lazy loading issues', function () {
    $singleTest = HospitalService::where('flag', 'test')->where('is_group', 0)->firstOrFail();
    $response1 = $this->get(route('lab.masters.tests.edit', $singleTest->id));
    $response1->assertOk();
    $response1->assertSee($singleTest->service);

    $groupTest = HospitalService::where('flag', 'lab_package')->orWhere('is_group', 1)->first();
    if ($groupTest) {
        $response2 = $this->get(route('lab.masters.tests.edit', $groupTest->id));
        $response2->assertOk();
        $response2->assertSee($groupTest->service);
    }
});
