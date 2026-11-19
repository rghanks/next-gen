<?php

declare(strict_types=1);

use App\Domain\Lab\Models\LabOrder;
use App\Domain\Lab\States\LabState;
use App\Models\Legacy\HospitalService;
use App\Models\Legacy\Patient;

beforeEach(function () {
    $this->deskPatient = Patient::firstOrFail();
    $this->deskPatient->update(['Fname' => 'DeskFilterAlice', 'Lname' => 'Worklist']);
    $this->deskOrder = LabOrder::create([
        'patient_id' => $this->deskPatient->id,
        'accession_no' => 'DESK-FILTER-MATCH',
        'priority' => 'stat',
        'status' => LabState::RECEIVED,
        'created_at' => '2099-05-15 12:00:00',
    ]);
});

test('worklist combines search priority date and status without broadening the queue', function () {
    foreach ([
        ['accession_no' => 'DESK-FILTER-WRONG-PRIORITY', 'priority' => 'routine'],
        ['accession_no' => 'DESK-FILTER-WRONG-STATUS', 'status' => LabState::RESULTED],
        ['accession_no' => 'DESK-FILTER-WRONG-DATE', 'created_at' => '2099-05-14 12:00:00'],
    ] as $overrides) {
        LabOrder::create(array_merge([
            'patient_id' => $this->deskPatient->id,
            'priority' => 'stat',
            'status' => LabState::RECEIVED,
            'created_at' => '2099-05-15 12:00:00',
        ], $overrides));
    }

    $this->get(route('lab.results.index', [
        'q' => 'DESK-FILTER', 'status' => 'pending', 'priority' => 'stat',
        'date_from' => '2099-05-15', 'date_to' => '2099-05-15',
    ]))->assertOk()->assertViewHas('orders', function ($orders) {
        return $orders->total() === 1 && $orders->first()->id === $this->deskOrder->id;
    });
});

test('worklist finds a patient or an investigation through their related records', function () {
    $service = HospitalService::where('flag', 'test')->firstOrFail();
    $service->update(['service' => 'DeskFilterInvestigation']);
    $this->deskOrder->items()->create(['service_id' => $service->id, 'status' => LabState::RECEIVED]);

    foreach (['DeskFilterAlice', 'DeskFilterInvestigation'] as $term) {
        $this->get(route('lab.results.index', ['q' => $term, 'date_from' => '2099-05-15']))
            ->assertOk()->assertViewHas('orders', fn ($orders) => $orders->total() === 1 && $orders->first()->id === $this->deskOrder->id);
    }
});

test('all orders includes other states while the pending queue stays restricted', function () {
    $this->deskOrder->update(['status' => LabState::ORDERED]);

    $this->get(route('lab.results.index', ['q' => 'DESK-FILTER-MATCH', 'status' => 'pending']))
        ->assertOk()->assertViewHas('orders', fn ($orders) => $orders->total() === 0);
    $this->get(route('lab.results.index', ['q' => 'DESK-FILTER-MATCH', 'status' => 'all']))
        ->assertOk()->assertViewHas('orders', fn ($orders) => $orders->total() === 1);
});

test('worklist rejects unsupported filter values and malformed dates', function () {
    $this->getJson(route('lab.results.index', ['status' => 'unknown', 'priority' => 'anything', 'date_from' => 'invalid']))
        ->assertUnprocessable()->assertJsonValidationErrors(['status', 'priority', 'date_from']);
});

test('unified lab orders desk renders with status lifecycle filter counters and collects sample in-place', function () {
    // 1. Visit orders desk
    $response = $this->get(route('lab.orders.index', ['date_from' => '2099-05-15', 'date_to' => '2099-05-15']));
    $response->assertOk();
    $response->assertViewHas('counts');
    $response->assertViewHas('orders');

    // 2. Create an ordered lab order with specimen
    $order = LabOrder::create([
        'patient_id' => $this->deskPatient->id,
        'accession_no' => 'UNIFIED-COLLECT-TEST',
        'priority' => 'routine',
        'status' => LabState::ORDERED,
    ]);
    $specimen = $order->specimens()->create([
        'barcode' => 'BC-UNIFIED-999',
        'specimen_type' => 'Blood',
        'container' => 'EDTA Tube',
    ]);

    // 3. Test in-place sample collection API
    $collectResponse = $this->postJson(route('lab.orders.collect-sample'), [
        'order_id' => $order->id,
        'barcode' => $specimen->barcode,
    ]);
    $collectResponse->assertOk();
    $collectResponse->assertJson(['success' => true]);

    $order->refresh();
    expect($order->status)->toBe(LabState::RECEIVED);
});

test('lab orders desk table renders with benchmark columns, line highlights, and per-item rows', function () {
    // Create an order with 2 investigations
    $order = LabOrder::create([
        'patient_id' => $this->deskPatient->id,
        'accession_no' => 'BENCHMARK-ROWS-TEST',
        'priority' => 'routine',
        'status' => LabState::VERIFIED,
    ]);

    $service1 = App\Models\Legacy\HospitalService::where('service', 'LIVER FUNCTION TEST')->first();
    if (! $service1) {
        $service1 = App\Models\Legacy\HospitalService::create([
            'service' => 'BENCHMARK LFT',
            'short_code' => 'B_LFT',
            'flag' => 'test',
            'is_group' => 0,
            'active' => 1,
            'spec_id' => 1,
            'comment' => '',
            'brief_des' => 'b_lft',
            'category_id' => 9,
        ]);
    }

    $service2 = App\Models\Legacy\HospitalService::create([
        'service' => 'BENCHMARK VITAMIN D OUTSOURCE',
        'short_code' => 'B_VITD',
        'flag' => 'test',
        'is_group' => 0,
        'active' => 1,
        'spec_id' => 1,
        'comment' => '',
        'brief_des' => 'b_vitd',
        'category_id' => 9,
    ]);

    $order->items()->create(['service_id' => $service1->id, 'status' => LabState::VERIFIED]);
    $order->items()->create(['service_id' => $service2->id, 'status' => LabState::VERIFIED, 'is_outsource' => true]);

    $response = $this->get(route('lab.orders.index', [
        'name' => $this->deskPatient->Fname,
        'date_from' => now()->subDay()->toDateString(),
        'date_to' => now()->addDay()->toDateString(),
    ]));

    $response->assertOk();
    $response->assertSee('table-lab-orders');
    $response->assertSee('Bill ID');
    $response->assertSee('Patient');
    $response->assertSee('UHID');
    $response->assertSee('Doctor');
    $response->assertSee('Bill Date');
    $response->assertSee('Investigation');
    $response->assertSee('Sample ID');
    $response->assertSee('Reporting');
    $response->assertSee('Approval');
    $response->assertSee('Report Status');
    $response->assertSee('Action');
    $response->assertSee($service1->service);
    $response->assertSee($service2->service);
    $response->assertSee('Outsourced');
    $response->assertSee('row-completed');
});

test('lab orders desk provides in-place report preview modal, approval, and rejection workflow', function () {
    // 1. Check modal markup exists on lab.orders.index
    $viewResponse = $this->get(route('lab.orders.index'));
    $viewResponse->assertOk();
    $viewResponse->assertSee('reportApprovalModal');
    $viewResponse->assertSee('btnModalApproveReport');
    $viewResponse->assertSee('btnModalRejectReport');
    $viewResponse->assertSee('btnModalEditReport');

    // 2. Create order in resulted state
    $order = LabOrder::create([
        'patient_id' => $this->deskPatient->id,
        'accession_no' => 'INPLACE-APPROVE-TEST',
        'priority' => 'routine',
        'status' => LabState::RESULTED,
    ]);

    // 3. Test approveOrder API
    $approveRes = $this->postJson(route('lab.orders.approve'), ['order_id' => $order->id]);
    $approveRes->assertOk();
    $approveRes->assertJson([
        'success' => true,
        'status' => 'completed',
    ]);
    expect($order->fresh()->status)->toBe(LabState::RELEASED);

    // 4. Test rejectOrder API
    $rejectRes = $this->postJson(route('lab.orders.reject'), [
        'order_id' => $order->id,
        'remarks' => 'Quality check parameter mismatch',
    ]);
    $rejectRes->assertOk();
    $rejectRes->assertJson([
        'success' => true,
        'status' => 'in_process',
    ]);
    expect($order->fresh()->status)->toBe(LabState::IN_PROCESS);
});
