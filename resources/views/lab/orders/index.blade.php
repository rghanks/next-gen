@extends('lab.layouts.app')

@section('title', 'Lab Test Orders & Worklist | Rahul Clinic & Laboratory')
@section('context', 'orders')

@push('styles')
<style>
    /* ══════════════════════════════════════════════════════════════ */
    /* FILTER CARD & INPUT STYLES                                     */
    /* ══════════════════════════════════════════════════════════════ */
    .filter-card {
        background: #ffffff;
        border: 1px solid #dcdfe4;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }

    .filter-grid-table {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
    }

    .filter-grid-table td {
        padding: 3px 4px;
        vertical-align: middle;
        border: none !important;
    }

    .filter-grid-table .filter-label {
        font-size: 11.5px;
        font-weight: 600;
        color: #475569;
        white-space: nowrap;
        margin: 0;
    }

    .filter-grid-table .form-control,
    .filter-grid-table .form-select {
        height: 31px;
        font-size: 11.5px;
        border-color: #cbd5e1;
        padding: 3px 8px;
        border-radius: 4px;
    }

    .filter-grid-table .form-control:focus,
    .filter-grid-table .form-select:focus {
        border-color: #0284c7;
        box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.15);
    }

    /* Status & Source Radio Pill Buttons */
    .btn-filter-group .btn {
        font-size: 11px;
        padding: 4px 9px;
        font-weight: 500;
        white-space: nowrap;
        border-color: #cbd5e1;
        line-height: 1.3;
    }

    .btn-filter-group .btn-check:checked + .btn-outline-success,
    .btn-filter-group .btn-check:checked + .btn {
        background-color: #10b981 !important;
        border-color: #10b981 !important;
        color: #ffffff !important;
        font-weight: 600;
    }

    /* ══════════════════════════════════════════════════════════════ */
    /* LAB ORDERS WORKLIST TABLE - EXACT LINE HIGHLIGHTS & SPACING   */
    /* ══════════════════════════════════════════════════════════════ */
    .orders-table-container {
        background: #ffffff;
        border: 2px solid #94a3b8 !important;
        border-radius: 6px;
        overflow-x: auto;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }

    .table-lab-orders {
        width: 100%;
        margin-bottom: 0;
        border-collapse: collapse !important;
        border: 2px solid #94a3b8 !important;
        font-size: 12px;
    }

    /* Top Row (Header) - Heavy Border */
    .table-lab-orders thead tr {
        border-top: 2.5px solid #0f172a !important;
        border-bottom: 2.5px solid #0f172a !important;
    }

    .table-lab-orders thead th {
        background-color: #252f4a !important;
        color: #ffffff !important;
        font-weight: 700 !important;
        font-size: 12px !important;
        letter-spacing: 0.3px;
        border: 1px solid #475569 !important;
        border-top: 2.5px solid #0f172a !important;
        border-bottom: 2.5px solid #0f172a !important;
        padding: 8px 10px !important;
        white-space: nowrap;
    }

    /* All cells - Crisp Solid Borders */
    .table-lab-orders th,
    .table-lab-orders td {
        border: 1px solid #cbd5e1 !important;
        padding: 7px 10px !important;
        vertical-align: middle !important;
        line-height: 1.35;
    }

    /* Bottom Row - Heavy Border */
    .table-lab-orders tbody tr:last-child td {
        border-bottom: 2.5px solid #94a3b8 !important;
    }

    /* Row Background States & Prominent Hover Highlights */
    .table-lab-orders tbody tr {
        transition: background-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .table-lab-orders tbody tr.row-pending {
        background-color: #ffffff !important;
    }

    .table-lab-orders tbody tr.row-pending:hover td {
        background-color: #e2e8f0 !important;
        cursor: pointer;
    }

    .table-lab-orders tbody tr.row-collected {
        background-color: #f0fdfa !important;
    }

    .table-lab-orders tbody tr.row-collected:hover td {
        background-color: #cffafe !important;
        cursor: pointer;
    }

    /* Soft mint green row background for completed tests */
    .table-lab-orders tbody tr.row-completed {
        background-color: #e8f8f0 !important;
    }

    .table-lab-orders tbody tr.row-completed:hover td {
        background-color: #bbf7d0 !important;
        cursor: pointer;
    }

    /* Links & Typography */
    .bill-id-link {
        color: #2563eb !important;
        font-weight: 700;
        text-decoration: none;
        font-size: 12px;
    }

    .bill-id-link:hover {
        text-decoration: underline;
        color: #1d4ed8 !important;
    }

    .patient-name-text {
        font-weight: 600;
        color: #1e293b;
        font-size: 12px;
    }

    .doctor-name-text {
        font-size: 11.5px;
        color: #334155;
    }

    .bill-date-text {
        font-size: 11px;
        color: #475569;
        white-space: nowrap;
    }

    /* Vibrant Buttons Styling */
    .btn-generate-sample {
        background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%) !important;
        border: 1px solid #0284c7 !important;
        color: #ffffff !important;
        font-weight: 600;
        font-size: 11px;
        padding: 4px 10px !important;
        border-radius: 4px;
        white-space: nowrap;
        text-align: center;
        box-shadow: 0 1px 3px rgba(2,132,199,0.3);
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .btn-generate-sample:hover {
        background: #0369a1 !important;
        border-color: #0369a1 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 5px rgba(2,132,199,0.45);
        transform: translateY(-1px);
    }

    .btn-sample-id {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%) !important;
        border: 1px solid #16a34a !important;
        color: #ffffff !important;
        font-weight: 700;
        font-size: 11px;
        padding: 4px 12px !important;
        border-radius: 4px;
        white-space: nowrap;
        text-align: center;
        box-shadow: 0 1px 3px rgba(22,163,74,0.3);
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .btn-sample-id:hover {
        background: #15803d !important;
        border-color: #15803d !important;
        color: #ffffff !important;
        box-shadow: 0 2px 5px rgba(22,163,74,0.45);
        transform: translateY(-1px);
    }

    .btn-start-reporting {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;
        border: 1px solid #1e293b !important;
        color: #ffffff !important;
        font-weight: 700;
        font-size: 11px;
        padding: 4px 12px !important;
        border-radius: 4px;
        white-space: nowrap;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        box-shadow: 0 1px 3px rgba(15,23,42,0.3);
        transition: all 0.15s ease;
    }

    .btn-start-reporting:hover {
        background: #0f172a !important;
        border-color: #0f172a !important;
        color: #ffffff !important;
        box-shadow: 0 2px 5px rgba(15,23,42,0.45);
        transform: translateY(-1px);
    }

    /* Status Labels (Badges) */
    .status-badge {
        font-size: 11px;
        font-weight: 600;
        padding: 3px 9px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        white-space: nowrap;
        letter-spacing: 0.1px;
    }

    .status-badge-completed {
        background-color: #dcfce7 !important;
        color: #15803d !important;
        border: 1px solid #86efac !important;
        box-shadow: 0 1px 2px rgba(22,163,74,0.12);
    }

    .status-badge-collected {
        background-color: #e0f2fe !important;
        color: #0369a1 !important;
        border: 1px solid #7dd3fc !important;
        box-shadow: 0 1px 2px rgba(2,132,199,0.12);
    }

    .status-badge-inprocess {
        background-color: #fef3c7 !important;
        color: #b45309 !important;
        border: 1px solid #fcd34d !important;
        box-shadow: 0 1px 2px rgba(180,83,9,0.12);
    }

    .status-badge-approval {
        background-color: #ffedd5 !important;
        color: #c2410c !important;
        border: 1px solid #fdba74 !important;
        box-shadow: 0 1px 2px rgba(194,65,12,0.12);
    }

    .status-badge-pending {
        background-color: #f1f5f9 !important;
        color: #64748b !important;
        border: 1px solid #cbd5e1 !important;
    }

    /* Action Buttons */
    .btn-action-print {
        background-color: #1e293b !important;
        border: 1px solid #0f172a !important;
        color: #ffffff !important;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 9px !important;
        border-radius: 4px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        transition: all 0.15s ease;
    }

    .btn-action-print:hover {
        background-color: #0f172a !important;
        color: #ffffff !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .btn-action-download {
        background-color: #0284c7 !important;
        border: 1px solid #0369a1 !important;
        color: #ffffff !important;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 9px !important;
        border-radius: 4px;
        box-shadow: 0 1px 2px rgba(2,132,199,0.2);
        text-decoration: none;
        transition: all 0.15s ease;
    }

    .btn-action-download:hover {
        background-color: #0369a1 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 4px rgba(2,132,199,0.35);
    }

    .btn-action-disapprove {
        background-color: #d97706 !important;
        border: 1px solid #b45309 !important;
        color: #ffffff !important;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 9px !important;
        border-radius: 4px;
        box-shadow: 0 1px 2px rgba(217,119,6,0.2);
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .btn-action-disapprove:hover {
        background-color: #b45309 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 4px rgba(217,119,6,0.35);
    }

    .btn-mini-barcode {
        background-color: #0d9488 !important;
        border: 1px solid #0f766e !important;
        color: #ffffff !important;
        font-size: 11px;
        padding: 2.5px 7px !important;
        border-radius: 4px;
        box-shadow: 0 1px 2px rgba(13,148,136,0.2);
        transition: all 0.15s ease;
    }

    .btn-mini-barcode:hover {
        background-color: #0f766e !important;
        color: #ffffff !important;
    }

    .btn-mini-receipt {
        background-color: #6366f1 !important;
        border: 1px solid #4f46e5 !important;
        color: #ffffff !important;
        font-size: 11px;
        padding: 2.5px 7px !important;
        border-radius: 4px;
        box-shadow: 0 1px 2px rgba(99,102,241,0.2);
        transition: all 0.15s ease;
    }

    .btn-mini-receipt:hover {
        background-color: #4f46e5 !important;
        color: #ffffff !important;
    }
</style>
@endpush

@section('content')
@php
    $labSetting = \App\Models\LabSetting::current();
@endphp
<div class="container-fluid px-3 py-2">

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- TOP FILTER SECTION (EXACT BENCHMARK LAYOUT)                    -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <div class="card filter-card mb-3">
        <div class="card-body p-2">
            <form id="orders-filter-form" method="GET" action="{{ route('lab.orders.index') }}">
                <div class="row gx-2 gy-2 align-items-center">

                    <!-- Left: 3-Row Filter Grid (UHID/Bill ID, Patient/From Date, Mobile/To Date) -->
                    <div class="col-xl-5 col-lg-5 col-12">
                        <table class="filter-grid-table">
                            <tbody>
                                <tr>
                                    <td style="width: 85px;">
                                        <label class="filter-label" for="filter_uhid">UHID</label>
                                    </td>
                                    <td>
                                        <input type="text" id="filter_uhid" name="uhid" class="form-control" placeholder="UHID" value="{{ $uhid ?? '' }}">
                                    </td>
                                    <td style="width: 70px; padding-left: 8px;">
                                        <label class="filter-label" for="filter_bill_id">Bill ID</label>
                                    </td>
                                    <td>
                                        <input type="text" id="filter_bill_id" name="bill_id" class="form-control" placeholder="Bill ID" value="{{ $billId ?? '' }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <label class="filter-label" for="filter_name">Patient Name</label>
                                    </td>
                                    <td>
                                        <input type="text" id="filter_name" name="name" class="form-control" placeholder="Patient Name" value="{{ $patientName ?? '' }}">
                                    </td>
                                    <td style="padding-left: 8px;">
                                        <label class="filter-label" for="filter_date_from">From Date</label>
                                    </td>
                                    <td>
                                        <input type="date" id="filter_date_from" name="date_from" class="form-control" value="{{ $dateFrom ?? '' }}">
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <label class="filter-label" for="filter_mobile">Mobile Number</label>
                                    </td>
                                    <td>
                                        <input type="text" id="filter_mobile" name="mobile" class="form-control" placeholder="Patient Mobile Number" value="{{ $mobileNo ?? '' }}">
                                    </td>
                                    <td style="padding-left: 8px;">
                                        <label class="filter-label" for="filter_date_to">To Date</label>
                                    </td>
                                    <td>
                                        <input type="date" id="filter_date_to" name="date_to" class="form-control" value="{{ $dateTo ?? '' }}">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Right: Status Radio Pills, Source Radio Pills & Action Controls -->
                    <div class="col-xl-7 col-lg-7 col-12">
                        <div class="d-flex flex-column gap-1">

                            <!-- Row 1: Status Lifecycle Filter Pills -->
                            <div class="btn-group btn-group-sm w-100 btn-filter-group" role="group">
                                <input type="radio" class="btn-check" name="status" id="btnStatusAll" value="all" autocomplete="off" {{ ($status ?? 'all') === 'all' ? 'checked' : '' }} onchange="$('#orders-filter-form').submit();">
                                <label class="btn {{ ($status ?? 'all') === 'all' ? 'btn-success text-white' : 'btn-outline-success' }} px-3" for="btnStatusAll">All</label>

                                <input type="radio" class="btn-check" name="status" id="btnStatusSamplePending" value="sample_pending" autocomplete="off" {{ ($status ?? 'all') === 'sample_pending' ? 'checked' : '' }} onchange="$('#orders-filter-form').submit();">
                                <label class="btn {{ ($status ?? 'all') === 'sample_pending' ? 'btn-secondary text-white' : 'btn-outline-secondary' }}" for="btnStatusSamplePending">Sample Collection Pending</label>

                                <input type="radio" class="btn-check" name="status" id="btnStatusReportPending" value="report_pending" autocomplete="off" {{ in_array(($status ?? 'all'), ['report_pending', 'collected'], true) ? 'checked' : '' }} onchange="$('#orders-filter-form').submit();">
                                <label class="btn {{ in_array(($status ?? 'all'), ['report_pending', 'collected'], true) ? 'btn-info text-white' : 'btn-outline-info' }}" for="btnStatusReportPending">Reporting Pending</label>

                                <input type="radio" class="btn-check" name="status" id="btnStatusReportInProcess" value="in_process" autocomplete="off" {{ ($status ?? 'all') === 'in_process' ? 'checked' : '' }} onchange="$('#orders-filter-form').submit();">
                                <label class="btn {{ ($status ?? 'all') === 'in_process' ? 'btn-info text-white' : 'btn-outline-info' }}" for="btnStatusReportInProcess">Reporting In-process</label>

                                <input type="radio" class="btn-check" name="status" id="btnStatusApprovalPending" value="approval_pending" autocomplete="off" {{ in_array(($status ?? 'all'), ['approval_pending', 'resulted'], true) ? 'checked' : '' }} onchange="$('#orders-filter-form').submit();">
                                <label class="btn {{ in_array(($status ?? 'all'), ['approval_pending', 'resulted'], true) ? 'btn-warning text-dark' : 'btn-outline-warning' }}" for="btnStatusApprovalPending">Approval Pending</label>

                                <input type="radio" class="btn-check" name="status" id="btnStatusCompleted" value="completed" autocomplete="off" {{ ($status ?? 'all') === 'completed' ? 'checked' : '' }} onchange="$('#orders-filter-form').submit();">
                                <label class="btn {{ ($status ?? 'all') === 'completed' ? 'btn-success text-white' : 'btn-outline-success' }} px-3" for="btnStatusCompleted">Completed</label>
                            </div>

                            <!-- Row 2: Source Filter Pills (All / In-House / Outsourced) -->
                            <div class="d-flex align-items-center">
                                <div class="btn-group btn-group-sm btn-filter-group" role="group">
                                    <input type="radio" class="btn-check" name="source" id="btnSourceAll" value="all" autocomplete="off" {{ ($source ?? 'all') === 'all' ? 'checked' : '' }} onchange="$('#orders-filter-form').submit();">
                                    <label class="btn {{ ($source ?? 'all') === 'all' ? 'btn-success text-white' : 'btn-outline-success' }} px-4" for="btnSourceAll">All</label>

                                    <input type="radio" class="btn-check" name="source" id="btnSourceInhouse" value="inhouse" autocomplete="off" {{ ($source ?? 'all') === 'inhouse' ? 'btn-info text-white' : 'btn-outline-info' }} px-4" for="btnSourceInhouse">In-House</label>

                                    <input type="radio" class="btn-check" name="source" id="btnSourceOutsource" value="outsource" autocomplete="off" {{ ($source ?? 'all') === 'outsource' ? 'btn-warning text-dark' : 'btn-outline-warning' }} px-4" for="btnSourceOutsource">Outsourced</label>
                                </div>
                            </div>

                            <!-- Row 3: Select Services Dropdown + Search + Clear Filter + Refresh -->
                            <div class="d-flex align-items-center gap-2 flex-wrap pt-1">
                                <div style="flex: 1; min-width: 220px;">
                                    <select name="service_id" id="filter_service_id" class="form-select form-select-sm" style="height: 31px; font-size: 11.5px;">
                                        <option value="">Select Services</option>
                                        @foreach(($services ?? []) as $srv)
                                            <option value="{{ $srv->id }}" {{ (string)($serviceId ?? '') === (string)$srv->id ? 'selected' : '' }}>
                                                {{ $srv->service }} {{ $srv->short_code ? '('.$srv->short_code.')' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-sm text-white px-3" style="background-color: #252f4a; height: 31px; font-size: 11.5px; font-weight: 600;">
                                    <i class="uil uil-search me-1"></i> Search
                                </button>
                                <a href="{{ route('lab.orders.index') }}" class="btn btn-sm btn-outline-danger px-2" style="height: 31px; font-size: 11.5px; display: inline-flex; align-items: center;">
                                    <i class="uil uil-times me-1"></i> Clear Filter
                                </a>
                                <button type="button" class="btn btn-sm text-white px-3" style="background-color: #0284c7; height: 31px; font-size: 11.5px; font-weight: 500;" onclick="window.location.reload();">
                                    <i class="uil uil-refresh me-1"></i> Refresh
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-dark px-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#printSettingsModal" style="height: 31px; font-size: 11.5px; display: inline-flex; align-items: center;" title="Configure Print Margins and Letterhead Header">
                                    <i class="uil uil-sliders-v-alt me-1 text-primary"></i> Print Settings
                                </button>
                                <span class="badge bg-light text-primary border px-2 py-1 fw-semibold d-inline-flex align-items-center" style="height: 31px; font-size: 11.5px;" title="Sample Number Series configured in Lab Settings">
                                    <i class="uil uil-tag-alt me-1 text-teal"></i> Next Sample: <span class="badge bg-primary text-white ms-1" id="topbar-next-sample-badge">{{ $nextSampleBarcode ?? 'SMP-000001' }}</span>
                                </span>
                            </div>

                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- LAB TEST ORDERS TABLE (EXACT BENCHMARK LINES & HIGHLIGHTS)     -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <div class="orders-table-container">
        <div class="table-responsive">
            <table class="table-lab-orders" id="lab-orders-table">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 70px;">Bill ID</th>
                        <th style="min-width: 140px;">Patient</th>
                        <th class="text-center" style="width: 55px;">UHID</th>
                        <th style="min-width: 130px;">Doctor</th>
                        <th style="width: 125px;">Bill Date</th>
                        <th style="min-width: 170px;">Investigation</th>
                        <th class="text-center" style="width: 135px;">Sample ID</th>
                        <th class="text-center" style="width: 115px;">Reporting</th>
                        <th class="text-center" style="width: 115px;">Approval</th>
                        <th class="text-center" style="width: 120px;">Report Status</th>
                        <th class="text-center" style="width: 155px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $ord)
                        @php
                            $patient = $ord->patient;
                            $firstSpecimen = $ord->specimens->first();
                            $stateVal = $ord->status->value ?? 'ordered';
                            $isCompleted = in_array($stateVal, ['verified', 'released', 'delivered'], true);
                            $isResulted = $stateVal === 'resulted';
                            $isInProcess = $stateVal === 'in_process';
                            $isCollected = ($firstSpecimen && $firstSpecimen->collected_at) || in_array($stateVal, ['collected', 'received', 'in_process', 'resulted', 'verified', 'released', 'delivered'], true);
                            $rowClass = $isCompleted ? 'row-completed' : ($isCollected ? 'row-collected' : 'row-pending');
                            $doctorName = $patient?->referring_doctor ?: 'Dr. Self';
                            $items = $ord->items;
                        @endphp

                        @if($items->isNotEmpty())
                            @foreach($items as $item)
                                @php
                                    $isOutsourced = $item->is_outsource || str_contains(strtolower($item->service?->service ?? ''), 'outsource');
                                    $receiptUrl = route('lab.billing.receipt', $ord->id);
                                    $barcodeUrl = route('lab.billing.barcode', $ord->id);
                                    $resultUrl = route('lab.results.entry', $ord->id);
                                    $reportUrl = route('lab.reports.view', $ord->id);
                                    $printPdfUrl = route('lab.reports.print', $ord->id);
                                    $downloadPdfUrl = route('lab.reports.pdf', $ord->id);
                                @endphp
                                <tr class="order-item-row {{ $rowClass }}" data-order-id="{{ $ord->id }}" data-item-id="{{ $item->id }}" data-url="{{ $resultUrl }}">
                                    <!-- 1. Bill ID -->
                                    <td class="text-center text-nowrap">
                                        <a href="javascript:void(0)" onclick="printDirectPreview('{{ $receiptUrl }}')" class="bill-id-link" title="Click to view & print bill receipt">
                                            {{ $ord->bill?->bill_number ?: ($ord->bill_id ?: $ord->id) }}
                                        </a>
                                    </td>

                                    <!-- 2. Patient -->
                                    <td class="text-nowrap">
                                        <span class="patient-name-text">
                                            {{ $patient?->full_name ?: ('Patient #'.$ord->patient_id) }}
                                        </span>
                                    </td>

                                    <!-- 3. UHID -->
                                    <td class="text-center fw-medium">
                                        {{ $patient?->uhid ?: $patient?->id ?: '-' }}
                                    </td>

                                    <!-- 4. Doctor -->
                                    <td class="text-nowrap">
                                        <span class="doctor-name-text">{{ $doctorName }}</span>
                                    </td>

                                    <!-- 5. Bill Date -->
                                    <td class="text-nowrap">
                                        <span class="bill-date-text">{{ $ord->created_at ? $ord->created_at->format('d/m/y h:i A') : '-' }}</span>
                                    </td>

                                    <!-- 6. Investigation -->
                                    <td>
                                        <span class="fw-semibold text-dark">{{ $item->service?->service ?: 'Investigation #'.$item->service_id }}</span>
                                        @if($isOutsourced)
                                            <span class="badge bg-danger ms-1" style="font-size: 9.5px; font-weight: 500; vertical-align: middle;">Outsourced</span>
                                        @endif
                                    </td>

                                    <!-- 7. Sample ID -->
                                    <td class="text-center text-nowrap sample-col-{{ $ord->id }}">
                                        @php
                                            $sampleBarcode = $firstSpecimen?->barcode ?: $ord->accession_no;
                                        @endphp
                                        @if($isCollected && $firstSpecimen)
                                            <button type="button" class="btn btn-sample-id" onclick="printDirectPreview('{{ $barcodeUrl }}')" title="Click to print tube barcode label">
                                                {{ $sampleBarcode }}
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-generate-sample" onclick="collectSampleQuick({{ $ord->id }})" title="Click to collect sample {{ $sampleBarcode }}">
                                                <i class="uil uil-flask me-1"></i>{{ $sampleBarcode }}
                                            </button>
                                        @endif
                                    </td>

                                    <!-- 8. Reporting -->
                                    <td class="text-center text-nowrap reporting-col-{{ $ord->id }}">
                                        @if($isCompleted)
                                            <span class="bill-date-text">{{ $ord->updated_at ? $ord->updated_at->format('d/m/y h:i A') : '-' }}</span>
                                        @elseif($isResulted)
                                            <a href="{{ $resultUrl }}" class="btn btn-sm btn-outline-primary" style="font-size: 11px; font-weight: 600; padding: 2px 7px;" title="Edit Results">
                                                Edit Results
                                            </a>
                                        @elseif($isCollected)
                                            <a href="{{ $resultUrl }}" class="btn btn-start-reporting" title="Enter Results Desk">
                                                ➔ Start
                                            </a>
                                        @else
                                            <a href="{{ $resultUrl }}" class="btn btn-start-reporting" style="opacity: 0.85;" title="Direct Result Entry">
                                                ➔ Start
                                            </a>
                                        @endif
                                    </td>

                                    <!-- 9. Approval -->
                                    <td class="text-center text-nowrap approval-col-{{ $ord->id }}">
                                        @if($isCompleted)
                                            <button type="button" class="btn btn-sm btn-outline-success" style="font-size: 11px; padding: 2px 8px;" onclick="openApprovalModal({{ $ord->id }}, '{{ $ord->accession_no }}', '{{ addslashes($patient?->full_name ?: ($patient?->Fname.' '.$patient?->Lname)) }}', '{{ $patient?->uhid ?: $patient?->id }}', '{{ addslashes($doctorName) }}', '{{ $reportUrl }}', '{{ $resultUrl }}', 'completed')" title="Click to view approved report">
                                                <i class="uil uil-check-circle text-success me-1"></i>{{ $ord->updated_at ? $ord->updated_at->format('d/m/y h:i A') : 'Approved' }}
                                            </button>
                                        @elseif($isResulted)
                                            <button type="button" class="btn btn-sm text-white" style="background-color: #d97706; font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 4px;" onclick="openApprovalModal({{ $ord->id }}, '{{ $ord->accession_no }}', '{{ addslashes($patient?->full_name ?: ($patient?->Fname.' '.$patient?->Lname)) }}', '{{ $patient?->uhid ?: $patient?->id }}', '{{ addslashes($doctorName) }}', '{{ $reportUrl }}', '{{ $resultUrl }}', 'resulted')" title="Review report & approve">
                                                <i class="uil uil-check-circle me-1"></i>Approve
                                            </button>
                                        @elseif($isInProcess || $isCollected)
                                            <button type="button" class="btn btn-sm btn-outline-secondary" style="font-size: 10.5px; padding: 2px 7px;" onclick="openApprovalModal({{ $ord->id }}, '{{ $ord->accession_no }}', '{{ addslashes($patient?->full_name ?: ($patient?->Fname.' '.$patient?->Lname)) }}', '{{ $patient?->uhid ?: $patient?->id }}', '{{ addslashes($doctorName) }}', '{{ $reportUrl }}', '{{ $resultUrl }}', 'in_process')" title="Preview report draft">
                                                <i class="uil uil-eye me-1"></i>Preview
                                            </button>
                                        @else
                                            <span></span>
                                        @endif
                                    </td>

                                    <!-- 10. Report Status -->
                                    <td class="text-center text-nowrap status-col-{{ $ord->id }}">
                                        @if($isCompleted)
                                            <span class="status-badge status-badge-completed">
                                                <i class="uil uil-check-circle me-1"></i>Completed
                                            </span>
                                        @elseif($isResulted)
                                            <span class="status-badge status-badge-approval">
                                                <i class="uil uil-file-check-alt me-1"></i>Approval Pending
                                            </span>
                                        @elseif($isInProcess)
                                            <span class="status-badge status-badge-inprocess">
                                                <i class="uil uil-sync me-1"></i>In-Process
                                            </span>
                                        @elseif($isCollected)
                                            <span class="status-badge status-badge-collected">
                                                <i class="uil uil-flask me-1"></i>Sample Collected
                                            </span>
                                        @else
                                            <span class="status-badge status-badge-pending">
                                                <i class="uil uil-hourglass me-1"></i>Pending
                                            </span>
                                        @endif
                                    </td>

                                    <!-- 11. Action -->
                                    <td class="text-center text-nowrap action-col-{{ $ord->id }}">
                                        @if($isCompleted)
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <a href="{{ $printPdfUrl }}" target="_blank" class="btn btn-action-print" title="Print Report (PDF)">
                                                    <i class="uil uil-print me-1"></i>Print
                                                </a>
                                                <a href="{{ $downloadPdfUrl }}" target="_blank" class="btn btn-action-download" title="Download Report (with Header)">
                                                    <i class="uil uil-file-download me-1"></i>Download
                                                </a>
                                                <button type="button" class="btn btn-action-disapprove" onclick="disapproveOrder({{ $ord->id }}, this)" title="Disapprove Report">
                                                    <i class="uil uil-times me-1"></i>Disapprove
                                                </button>
                                            </div>
                                        @elseif($isResulted)
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <button type="button" class="btn btn-sm text-white" style="background-color: #16a34a; font-size: 11px; font-weight: 600; padding: 3px 9px;" onclick="openApprovalModal({{ $ord->id }}, '{{ $ord->accession_no }}', '{{ addslashes($patient?->full_name ?: ($patient?->Fname.' '.$patient?->Lname)) }}', '{{ $patient?->uhid ?: $patient?->id }}', '{{ addslashes($doctorName) }}', '{{ $reportUrl }}', '{{ $resultUrl }}', 'resulted')" title="Preview Report & Approve">
                                                    <i class="uil uil-check-circle me-1"></i>Approve
                                                </button>
                                                <button type="button" class="btn btn-action-download" onclick="openApprovalModal({{ $ord->id }}, '{{ $ord->accession_no }}', '{{ addslashes($patient?->full_name ?: ($patient?->Fname.' '.$patient?->Lname)) }}', '{{ $patient?->uhid ?: $patient?->id }}', '{{ addslashes($doctorName) }}', '{{ $reportUrl }}', '{{ $resultUrl }}', 'resulted')" title="Preview Draft Report">
                                                    <i class="uil uil-file-alt me-1"></i>Preview
                                                </button>
                                                <button type="button" class="btn btn-mini-barcode" onclick="printDirectPreview('{{ $barcodeUrl }}')" title="Print Barcode">
                                                    <i class="uil uil-barcode me-1"></i>Barcode
                                                </button>
                                                <button type="button" class="btn btn-mini-receipt" onclick="printDirectPreview('{{ $receiptUrl }}')" title="Print Bill Receipt">
                                                    <i class="uil uil-receipt me-1"></i>Bill
                                                </button>
                                            </div>
                                        @else
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <a href="{{ $resultUrl }}" class="btn btn-start-reporting" title="Open Result Entry Desk">
                                                    ➔ Enter
                                                </a>
                                                <button type="button" class="btn btn-action-download" onclick="openApprovalModal({{ $ord->id }}, '{{ $ord->accession_no }}', '{{ addslashes($patient?->full_name ?: ($patient?->Fname.' '.$patient?->Lname)) }}', '{{ $patient?->uhid ?: $patient?->id }}', '{{ addslashes($doctorName) }}', '{{ $reportUrl }}', '{{ $resultUrl }}', 'in_process')" title="Preview Draft Report">
                                                    <i class="uil uil-file-alt me-1"></i>Preview
                                                </button>
                                                <button type="button" class="btn btn-mini-barcode" onclick="printDirectPreview('{{ $barcodeUrl }}')" title="Print Barcode">
                                                    <i class="uil uil-barcode me-1"></i>Barcode
                                                </button>
                                                <button type="button" class="btn btn-mini-receipt" onclick="printDirectPreview('{{ $receiptUrl }}')" title="Print Bill Receipt">
                                                    <i class="uil uil-receipt me-1"></i>Bill
                                                </button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            {{-- Fallback for order with no items --}}
                            @php
                                $receiptUrl = route('lab.billing.receipt', $ord->id);
                                $barcodeUrl = route('lab.billing.barcode', $ord->id);
                            @endphp
                            <tr class="order-item-row {{ $rowClass }}" data-order-id="{{ $ord->id }}">
                                <td class="text-center text-nowrap">
                                    <a href="javascript:void(0)" onclick="printDirectPreview('{{ $receiptUrl }}')" class="bill-id-link">
                                        {{ $ord->bill?->bill_number ?: ($ord->bill_id ?: $ord->id) }}
                                    </a>
                                </td>
                                <td class="text-nowrap">
                                    <span class="patient-name-text">{{ $patient?->full_name ?: ('Patient #'.$ord->patient_id) }}</span>
                                </td>
                                <td class="text-center fw-medium">{{ $patient?->uhid ?: $patient?->id ?: '-' }}</td>
                                <td class="text-nowrap"><span class="doctor-name-text">{{ $doctorName }}</span></td>
                                <td class="text-nowrap"><span class="bill-date-text">{{ $ord->created_at ? $ord->created_at->format('d/m/y h:i A') : '-' }}</span></td>
                                <td><span class="text-muted">Investigation #{{ $ord->accession_no }}</span></td>
                                <td class="text-center text-nowrap sample-col-{{ $ord->id }}">
                                    @php
                                        $sampleBarcode = $firstSpecimen?->barcode ?: $ord->accession_no;
                                    @endphp
                                    @if($isCollected && $firstSpecimen)
                                        <button type="button" class="btn btn-sample-id" onclick="printDirectPreview('{{ $barcodeUrl }}')" title="Click to print tube barcode label">
                                            {{ $sampleBarcode }}
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-generate-sample" onclick="collectSampleQuick({{ $ord->id }})" title="Click to collect sample {{ $sampleBarcode }}">
                                            <i class="uil uil-flask me-1"></i>{{ $sampleBarcode }}
                                        </button>
                                    @endif
                                </td>
                                <td class="text-center text-nowrap reporting-col-{{ $ord->id }}"></td>
                                <td class="text-center text-nowrap approval-col-{{ $ord->id }}"></td>
                                <td class="text-center text-nowrap status-col-{{ $ord->id }}">
                                    @if($isCompleted)
                                        <span class="status-badge status-badge-completed">
                                            <i class="uil uil-check-circle me-1"></i>Completed
                                        </span>
                                    @elseif($isCollected)
                                        <span class="status-badge status-badge-collected">
                                            <i class="uil uil-flask me-1"></i>Sample Collected
                                        </span>
                                    @else
                                        <span class="status-badge status-badge-pending">
                                            <i class="uil uil-hourglass me-1"></i>Pending
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center text-nowrap action-col-{{ $ord->id }}">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-mini-barcode" onclick="printDirectPreview('{{ $barcodeUrl }}')">
                                            <i class="uil uil-barcode me-1"></i>Barcode
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-5 text-muted">
                                <i class="uil uil-folder-open font-size-24 d-block mb-2 text-secondary"></i>
                                No lab orders found matching the selected filter criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination & Navigation Hint -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-2 px-3 border-top bg-light">
            <div class="text-muted" style="font-size: 11.5px;">
                <span class="badge bg-white text-dark border"><kbd>J</kbd> / <kbd>K</kbd></span> or <span class="badge bg-white text-dark border"><kbd>↑</kbd> / <kbd>↓</kbd></span> Navigate &nbsp;•&nbsp; 
                <span class="badge bg-white text-dark border"><kbd>Enter</kbd></span> Open Result Entry &nbsp;•&nbsp; 
                Showing <strong>{{ $orders->firstItem() ?? 0 }}</strong> to <strong>{{ $orders->lastItem() ?? 0 }}</strong> of <strong>{{ $orders->total() }}</strong> records
            </div>
            <div>
                {{ $orders->links() }}
            </div>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- REPORT PREVIEW & IN-PLACE APPROVAL MODAL                       -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="reportApprovalModal" tabindex="-1" aria-labelledby="reportApprovalModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" style="max-width: 92%;">
        <div class="modal-content" style="border: 2px solid #252f4a; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
            <!-- Modal Header -->
            <div class="modal-header py-2 px-3" style="background-color: #252f4a; color: #ffffff;">
                <div class="d-flex align-items-center gap-2">
                    <i class="uil uil-file-check-alt text-info fs-4"></i>
                    <div>
                        <h6 class="modal-title mb-0 fw-bold text-white d-flex align-items-center gap-2">
                            <span>Report Preview & Verification</span>
                            <span class="badge bg-primary text-white" id="modalAccBadge">ACC-000</span>
                            <span id="modalStatusBadge"></span>
                        </h6>
                        <div class="text-white-50 small" style="font-size: 11.5px;">
                            Patient: <strong class="text-white" id="modalPatientName">-</strong> &nbsp;•&nbsp; 
                            UHID: <strong class="text-white" id="modalUhid">-</strong> &nbsp;•&nbsp; 
                            Doctor: <strong class="text-white" id="modalDoctor">-</strong>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal Body (Live Preview Iframe) -->
            <div class="modal-body p-0 position-relative" style="background-color: #f8fafc; height: 75vh; min-height: 520px;">
                <div id="modalReportLoading" class="position-absolute top-50 start-50 translate-middle text-center" style="z-index: 5;">
                    <div class="spinner-border text-primary" role="status" style="width: 2.5rem; height: 2.5rem;">
                        <span class="visually-hidden">Loading report preview...</span>
                    </div>
                    <div class="mt-2 text-muted fw-semibold">Loading Live Report Preview...</div>
                </div>
                <iframe id="modalReportIframe" src="about:blank" style="width: 100%; height: 100%; border: none; display: block; opacity: 0; transition: opacity 0.2s ease;"></iframe>
            </div>

            <!-- Modal Footer with Approve, Reject, Edit, Print actions -->
            <div class="modal-footer py-2 px-3 d-flex justify-content-between align-items-center" style="background-color: #f1f5f9; border-top: 1px solid #cbd5e1;">
                <div class="d-flex align-items-center gap-2">
                    <a href="#" target="_blank" class="btn btn-outline-dark btn-sm" id="btnModalPrintReport" title="Print Report (PDF)">
                        <i class="uil uil-print me-1"></i> Print
                    </a>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnModalDirectPrint" title="Print the preview report directly using browser dialog">
                        <i class="uil uil-printer me-1"></i> Browser Print
                    </button>
                    <a href="#" target="_blank" class="btn btn-outline-primary btn-sm" id="btnModalDownloadReport" title="Download official PDF report (with Header)">
                        <i class="uil uil-file-download me-1"></i> Download
                    </a>
                    <a href="#" target="_blank" class="btn btn-outline-secondary btn-sm" id="btnModalNewTab" title="Open report in new browser tab">
                        <i class="uil uil-external-link-alt me-1"></i> Open Full View
                    </a>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <!-- Edit Button -->
                    <a href="#" class="btn btn-outline-primary btn-sm px-3 fw-semibold" id="btnModalEditReport" title="Edit parameter values">
                        <i class="uil uil-edit me-1"></i> Edit Results
                    </a>

                    <!-- Reject / Disapprove Button -->
                    <button type="button" class="btn btn-danger btn-sm px-3 fw-semibold" id="btnModalRejectReport" title="Disapprove and return for correction">
                        <i class="uil uil-times me-1"></i> Reject / Disapprove
                    </button>

                    <!-- Approve Button -->
                    <button type="button" class="btn btn-success btn-sm px-4 fw-bold shadow-sm" id="btnModalApproveReport" style="background-color: #16a34a; border-color: #15803d;" title="Authorize & release verified report">
                        <i class="uil uil-check-circle me-1"></i> Approve Report
                    </button>

                    <!-- Close Button -->
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- PRINT & MARGIN SETTINGS MODAL                                  -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="printSettingsModal" tabindex="-1" aria-labelledby="printSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border: 2px solid #252f4a; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
            <div class="modal-header py-2 px-3 text-white" style="background-color: #252f4a;">
                <h6 class="modal-title fw-bold mb-0 d-flex align-items-center gap-2" id="printSettingsModalLabel" style="font-size: 13.5px;">
                    <i class="uil uil-sliders-v-alt text-warning"></i>
                    <span>Report Print & Margin Settings</span>
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="quickPrintSettingsForm" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-3 bg-light">
                    <div class="alert alert-info py-2 px-3 mb-3" style="font-size: 11.5px;">
                        <i class="uil uil-info-circle me-1"></i>
                        <strong>Letterhead & Pre-printed Paper Configuration:</strong><br>
                        • <strong>Print Button:</strong> Generates PDF with custom Top & Bottom margins for printing directly on pre-printed letterhead paper.<br>
                        • <strong>Download Button:</strong> Generates PDF with your uploaded header banner image included.
                    </div>

                    <div class="card p-3 mb-3 border bg-white shadow-sm">
                        <div class="fw-bold text-dark mb-2 d-flex align-items-center gap-1" style="font-size: 12.5px;">
                            <i class="uil uil-ruler-combined text-primary"></i> Page Margins for Print (in Millimeters - mm)
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="filter-label fw-bold mb-1">Top Margin (mm) <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="print_top_margin" id="setting_top_margin" class="form-control" value="{{ $labSetting->print_top_margin ?? 35 }}" min="0" max="150" required>
                                    <span class="input-group-text bg-light text-muted">mm</span>
                                </div>
                                <small class="text-muted" style="font-size: 10px;">Blank space at top to prevent overlap with pre-printed header (recommended: 30 - 45 mm).</small>
                            </div>

                            <div class="col-md-6">
                                <label class="filter-label fw-bold mb-1">Bottom Margin (mm) <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="print_bottom_margin" id="setting_bottom_margin" class="form-control" value="{{ $labSetting->print_bottom_margin ?? 20 }}" min="0" max="150" required>
                                    <span class="input-group-text bg-light text-muted">mm</span>
                                </div>
                                <small class="text-muted" style="font-size: 10px;">Blank space at bottom for pre-printed footer (recommended: 15 - 25 mm).</small>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch mt-1">
                                    <input class="form-check-input" type="checkbox" role="switch" name="print_with_header" id="setting_print_with_header" value="1" {{ ($labSetting->print_with_header ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold text-dark" for="setting_print_with_header" style="font-size: 11.5px;">
                                        Print with Header on Blank Paper (leave unchecked if using pre-printed letterhead)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card p-3 mb-3 border bg-white shadow-sm">
                        <div class="fw-bold text-dark mb-2 d-flex align-items-center gap-1" style="font-size: 12.5px;">
                            <i class="uil uil-image text-success"></i> Header Banner Image (Used on Downloaded PDF Reports)
                        </div>
                        <div class="row g-3 align-items-center">
                            <div class="col-md-4 text-center">
                                <div class="border rounded p-2 bg-light d-flex align-items-center justify-content-center" style="min-height: 80px;">
                                    @if($labSetting->header_image_path && file_exists(public_path($labSetting->header_image_path)))
                                        <img src="{{ asset($labSetting->header_image_path) }}" alt="Current Header" id="modalHeaderPreview" style="max-height: 70px; max-width: 100%; object-fit: contain;">
                                    @else
                                        <div class="text-muted small" id="modalNoHeaderText"><i class="uil uil-image-slash fs-4 d-block"></i>No header banner uploaded</div>
                                        <img src="" alt="Header Preview" id="modalHeaderPreview" style="display: none; max-height: 70px; max-width: 100%;">
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-8">
                                <label class="filter-label fw-bold mb-1">Upload New Header Image</label>
                                <input type="file" name="header_image" id="setting_header_image" class="form-control form-control-sm mb-2" accept="image/*" onchange="previewImageFile(this, '#modalHeaderPreview', '#modalNoHeaderText')">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-muted small" style="font-size: 10.5px;">Banner Height:</span>
                                    <input type="number" name="header_height" id="setting_header_height" class="form-control form-control-sm text-center" value="{{ $labSetting->header_height ?? 120 }}" style="width: 75px; height: 28px;">
                                    <span class="text-muted small" style="font-size: 10.5px;">px</span>
                                </div>
                                <small class="text-muted d-block mt-1" style="font-size: 9.5px;">Supports PNG, JPG, JPEG, WEBP. This header will appear automatically on PDF downloads.</small>
                            </div>
                        </div>
                    </div>

                    <div class="card p-3 border bg-white shadow-sm">
                        <div class="fw-bold text-dark mb-2 d-flex align-items-center gap-1" style="font-size: 12.5px;">
                            <i class="uil uil-image-alt text-primary"></i> Footer Banner Image (Used on Downloaded PDF Reports)
                        </div>
                        <div class="row g-3 align-items-center">
                            <div class="col-md-4 text-center">
                                <div class="border rounded p-2 bg-light d-flex align-items-center justify-content-center" style="min-height: 80px;">
                                    @if($labSetting->footer_image_path && file_exists(public_path($labSetting->footer_image_path)))
                                        <img src="{{ asset($labSetting->footer_image_path) }}" alt="Current Footer" id="modalFooterPreview" style="max-height: 70px; max-width: 100%; object-fit: contain;">
                                    @else
                                        <div class="text-muted small" id="modalNoFooterText"><i class="uil uil-image-slash fs-4 d-block"></i>No footer banner uploaded</div>
                                        <img src="" alt="Footer Preview" id="modalFooterPreview" style="display: none; max-height: 70px; max-width: 100%;">
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-8">
                                <label class="filter-label fw-bold mb-1">Upload New Footer Image</label>
                                <input type="file" name="footer_image" id="setting_footer_image" class="form-control form-control-sm mb-2" accept="image/*" onchange="previewImageFile(this, '#modalFooterPreview', '#modalNoFooterText')">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-muted small" style="font-size: 10.5px;">Banner Height:</span>
                                    <input type="number" name="footer_height" id="setting_footer_height" class="form-control form-control-sm text-center" value="{{ $labSetting->footer_height ?? 50 }}" style="width: 75px; height: 28px;">
                                    <span class="text-muted small" style="font-size: 10.5px;">px</span>
                                </div>
                                <small class="text-muted d-block mt-1" style="font-size: 9.5px;">Supports PNG, JPG, JPEG, WEBP. Appears automatically at the bottom of downloaded PDF reports.</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2 px-3 d-flex justify-content-between align-items-center" style="background-color: #f1f5f9; border-top: 1px solid #cbd5e1;">
                    <a href="{{ route('lab.settings.index') }}" target="_blank" class="btn btn-sm btn-link text-decoration-none text-muted" style="font-size: 11px;">
                        <i class="uil uil-external-link-alt me-1"></i> Open All Lab Settings
                    </a>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-primary fw-bold px-3 shadow-sm" id="btnSavePrintSettings">
                            <i class="uil uil-save me-1"></i> Save Print Settings
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- 1. SAMPLE SERIES CONFIGURATION MODAL                           -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalSampleSeries" tabindex="-1" aria-labelledby="modalSampleSeriesLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border: 2px solid #0284c7; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
            <div class="modal-header py-2 px-3 text-white" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">
                <h6 class="modal-title fw-bold mb-0 d-flex align-items-center gap-2" id="modalSampleSeriesLabel" style="font-size: 13.5px;">
                    <i class="uil uil-sliders-v-alt"></i>
                    <span>Configure Sample Number Series</span>
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formSampleSeries" onsubmit="event.preventDefault(); submitSampleSeries();">
                <div class="modal-body p-3 bg-light">
                    <div class="p-3 bg-white border rounded mb-3">
                        <div class="text-muted small mb-1">Current Active Next Sample No:</div>
                        <div class="fs-5 fw-bold text-success font-monospace" id="modal_current_next_preview">
                            {{ $nextSampleBarcode ?? 'SMP-000001' }}
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold mb-1" style="font-size: 12px;">Start / Reset Series From Number <span class="text-danger">*</span></label>
                        <input type="number" id="series_next_number" class="form-control" min="1" placeholder="e.g. 100, 500, 1001" required>
                        <small class="text-muted" style="font-size: 10.5px;">Next generated sample will start from this number and count upwards sequentially.</small>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold mb-1" style="font-size: 12px;">Sample Prefix (Optional)</label>
                        <input type="text" id="series_prefix" class="form-control form-control-sm" value="{{ $labSetting->sample_no_prefix ?? 'SMP' }}" placeholder="e.g. SMP, LAB, PATH (or leave blank)">
                        <small class="text-muted" style="font-size: 10.5px;">Prefix attached to sample numbers (e.g. SMP &rarr; SMP-000500).</small>
                    </div>
                </div>
                <div class="modal-footer py-2 px-3 bg-white border-top">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold px-3" id="btnSaveSampleSeries">
                        <i class="uil uil-check me-1"></i> Start Series
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- 2. EDIT EXISTING SAMPLE NUMBER MODAL                           -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEditSample" tabindex="-1" aria-labelledby="modalEditSampleLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border: 2px solid #16a34a; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
            <div class="modal-header py-2 px-3 text-white" style="background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);">
                <h6 class="modal-title fw-bold mb-0 d-flex align-items-center gap-2" id="modalEditSampleLabel" style="font-size: 13.5px;">
                    <i class="uil uil-pen"></i>
                    <span>Edit Sample Number</span>
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formEditSample" onsubmit="event.preventDefault(); submitEditSample();">
                <input type="hidden" id="edit_sample_order_id">
                <div class="modal-body p-3 bg-light">
                    <div class="mb-3">
                        <label class="form-label fw-bold mb-1" style="font-size: 12px;">Custom Sample Number <span class="text-danger">*</span></label>
                        <input type="text" id="edit_sample_barcode" class="form-control fw-bold" placeholder="e.g. 500 or SMP-000500" required>
                        <small class="text-muted" style="font-size: 10.5px;">You can type just digits (e.g. 500) or a full barcode (e.g. SMP-000500).</small>
                    </div>

                    <div class="form-check form-switch p-2 bg-white border rounded">
                        <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="edit_sample_start_series" checked>
                        <label class="form-check-label fw-bold text-dark" for="edit_sample_start_series" style="font-size: 11.5px;">
                            Start subsequent series from this number
                        </label>
                        <div class="text-muted small ps-4" style="font-size: 10px;">Subsequent new samples will automatically count up from this number.</div>
                    </div>
                </div>
                <div class="modal-footer py-2 px-3 bg-white border-top">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success fw-bold px-3" id="btnSaveEditSample">
                        <i class="uil uil-save me-1"></i> Update Sample No.
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<!-- 3. CUSTOM SAMPLE COLLECTION MODAL                              -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalCustomCollect" tabindex="-1" aria-labelledby="modalCustomCollectLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border: 2px solid #0284c7; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
            <div class="modal-header py-2 px-3 text-white" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);">
                <h6 class="modal-title fw-bold mb-0 d-flex align-items-center gap-2" id="modalCustomCollectLabel" style="font-size: 13.5px;">
                    <i class="uil uil-flask"></i>
                    <span>Collect Specimen with Custom Sample No.</span>
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formCustomCollect" onsubmit="event.preventDefault(); submitCustomCollect();">
                <input type="hidden" id="custom_collect_order_id">
                <div class="modal-body p-3 bg-light">
                    <div class="p-2 px-3 bg-white border rounded mb-3 d-flex align-items-center justify-content-between">
                        <span class="text-muted small">Next auto in series:</span>
                        <span class="fw-bold text-success font-monospace" id="custom_collect_auto_preview">{{ $nextSampleBarcode ?? 'SMP-000001' }}</span>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold mb-1" style="font-size: 12px;">Enter Custom Sample Number <span class="text-danger">*</span></label>
                        <input type="text" id="custom_collect_input" class="form-control fw-bold" placeholder="e.g. 500 or SMP-000500" required>
                        <small class="text-muted" style="font-size: 10.5px;">The sample series will automatically start from this number for next samples.</small>
                    </div>
                </div>
                <div class="modal-footer py-2 px-3 bg-white border-top">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold px-3" id="btnSubmitCustomCollect">
                        <i class="uil uil-check-circle me-1"></i> Collect & Start Series
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function($) {
    'use strict';

    // ══════════════════════════════════════════════════════════════
    // 1. Direct Browser Print Preview (Single Execution Guard)
    // ══════════════════════════════════════════════════════════════
    let isPrinting = false;

    window.printDirectPreview = function(url) {
        if (!url || isPrinting) return;
        isPrinting = true;

        $('#desk-print-frame').remove();

        const iframe = $('<iframe id="desk-print-frame"></iframe>').css({
            position: 'fixed',
            left: '0',
            top: '0',
            width: '1px',
            height: '1px',
            opacity: '0.01',
            pointerEvents: 'none',
            border: '0'
        });

        let hasPrinted = false;
        iframe.on('load', function() {
            if (hasPrinted) return;
            hasPrinted = true;
            const frameEl = this;
            setTimeout(() => {
                try {
                    frameEl.contentWindow.focus();
                    frameEl.contentWindow.print();
                } catch (err) {
                    console.warn('Browser print preview error', err);
                    window.open(url, '_blank');
                } finally {
                    setTimeout(() => { isPrinting = false; }, 1000);
                }
            }, 250);
        });

        $('body').append(iframe);
        iframe.attr('src', url);
    };

    // ══════════════════════════════════════════════════════════════
    // 2. 1-Click Quick Sample ID Generation & Collection
    // ══════════════════════════════════════════════════════════════
    window.collectSampleQuick = function(orderId, customSampleNo) {
        if (!orderId) return;

        const sampleCols = $('.sample-col-' + orderId);
        sampleCols.find('.btn-generate-sample').prop('disabled', true).text('Generating...');

        const payload = {
            _token: $('meta[name="csrf-token"]').attr('content'),
            order_id: orderId
        };
        if (customSampleNo) {
            payload.custom_sample_no = customSampleNo;
        }

        $.ajax({
            url: "{{ route('lab.orders.collect-sample') }}",
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: function(res) {
                if (window.lisToast) {
                    window.lisToast(res.message, 'success');
                }

                // 1. Update all sample cells for this order to green Sample ID pill + Edit button
                sampleCols.html(`
                    <button type="button" class="btn btn-sample-id" onclick="printDirectPreview('${res.barcode_url}')" title="Click to print tube barcode label">
                        ${res.barcode}
                    </button>
                `);

                // Update topbar next sample badge
                if (res.next_sample_no) {
                    $('#topbar-next-sample-badge').text(res.next_sample_no);
                }

                // 2. Update Reporting cells with "➔ Start" button
                const reportingCols = $('.reporting-col-' + orderId);
                reportingCols.html(`
                    <a href="${res.result_entry_url}" class="btn btn-start-reporting">
                        ➔ Start
                    </a>
                `);

                // 3. Update Status cells to "Sample Collected" status badge
                const statusCols = $('.status-col-' + orderId);
                statusCols.html(`
                    <span class="status-badge status-badge-collected">
                        <i class="uil uil-flask me-1"></i>Sample Collected
                    </span>
                `);

                // 4. Update Row classes to row-collected
                $('tr[data-order-id="' + orderId + '"]').removeClass('row-pending').addClass('row-collected');

                // 5. Trigger Tube Barcode Direct Preview once
                if (res.barcode_url) {
                    printDirectPreview(res.barcode_url);
                }
            },
            error: function(xhr) {
                sampleCols.find('.btn-generate-sample').prop('disabled', false);
                let err = 'Failed to generate sample ID.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    err = xhr.responseJSON.message;
                }
                if (window.lisToast) {
                    window.lisToast(err, 'danger');
                } else {
                    alert(err);
                }
            }
        });
    };

    // ══════════════════════════════════════════════════════════════
    // Sample Number Series Management Functions
    // ══════════════════════════════════════════════════════════════
    window.openSampleSeriesModal = function() {
        $.get("{{ route('lab.orders.next-sample-no') }}", function(res) {
            if (res && res.next_sample_no) {
                $('#modal_current_next_preview').text(res.next_sample_no);
                $('#topbar-next-sample-badge').text(res.next_sample_no);
            }
        });
        $('#series_next_number').val('');
        const modal = new bootstrap.Modal(document.getElementById('modalSampleSeries'));
        modal.show();
    };

    window.submitSampleSeries = function() {
        const nextNum = parseInt($('#series_next_number').val());
        const prefix = $('#series_prefix').val().trim();
        if (!nextNum || nextNum <= 0) {
            if (window.lisToast) window.lisToast('Please enter a valid starting number.', 'warning');
            return;
        }

        const btn = $('#btnSaveSampleSeries').prop('disabled', true).text('Saving...');
        $.ajax({
            url: "{{ route('lab.orders.set-sample-series') }}",
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                _token: $('meta[name="csrf-token"]').attr('content'),
                next_number: nextNum,
                prefix: prefix
            }),
            success: function(res) {
                btn.prop('disabled', false).html('<i class="uil uil-check me-1"></i> Start Series');
                bootstrap.Modal.getInstance(document.getElementById('modalSampleSeries'))?.hide();
                if (res.next_sample_no) {
                    $('#topbar-next-sample-badge').text(res.next_sample_no);
                }
                if (window.lisToast) {
                    window.lisToast(res.message, 'success');
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html('<i class="uil uil-check me-1"></i> Start Series');
                let err = xhr.responseJSON?.message || 'Failed to update sample series.';
                if (window.lisToast) window.lisToast(err, 'danger');
            }
        });
    };

    window.openEditSampleModal = function(orderId, currentBarcode) {
        $('#edit_sample_order_id').val(orderId);
        $('#edit_sample_barcode').val(currentBarcode || '');
        const modal = new bootstrap.Modal(document.getElementById('modalEditSample'));
        modal.show();
    };

    window.submitEditSample = function() {
        const orderId = $('#edit_sample_order_id').val();
        const customBarcode = $('#edit_sample_barcode').val().trim();
        const startSeries = $('#edit_sample_start_series').is(':checked');

        if (!customBarcode) {
            if (window.lisToast) window.lisToast('Please enter custom sample number.', 'warning');
            return;
        }

        const btn = $('#btnSaveEditSample').prop('disabled', true).text('Saving...');
        $.ajax({
            url: "{{ route('lab.orders.update-sample-no') }}",
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                _token: $('meta[name="csrf-token"]').attr('content'),
                order_id: orderId,
                custom_sample_no: customBarcode,
                start_series: startSeries ? 1 : 0
            }),
            success: function(res) {
                btn.prop('disabled', false).html('<i class="uil uil-save me-1"></i> Update Sample No.');
                bootstrap.Modal.getInstance(document.getElementById('modalEditSample'))?.hide();
                
                // Update cells
                const sampleCols = $('.sample-col-' + orderId);
                sampleCols.html(`
                    <button type="button" class="btn btn-sample-id" onclick="printDirectPreview('${res.barcode_url}')" title="Click to print tube barcode label">
                        ${res.barcode}
                    </button>
                `);

                if (res.next_sample_no) {
                    $('#topbar-next-sample-badge').text(res.next_sample_no);
                }
                if (window.lisToast) {
                    window.lisToast(res.message, 'success');
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html('<i class="uil uil-save me-1"></i> Update Sample No.');
                let err = xhr.responseJSON?.message || 'Failed to update sample number.';
                if (window.lisToast) window.lisToast(err, 'danger');
            }
        });
    };

    window.openCustomCollectModal = function(orderId) {
        $('#custom_collect_order_id').val(orderId);
        $.get("{{ route('lab.orders.next-sample-no') }}", function(res) {
            if (res && res.next_sample_no) {
                $('#custom_collect_auto_preview').text(res.next_sample_no);
                $('#custom_collect_input').attr('placeholder', res.next_sample_no).val('');
            }
        });
        const modal = new bootstrap.Modal(document.getElementById('modalCustomCollect'));
        modal.show();
    };

    window.submitCustomCollect = function() {
        const orderId = $('#custom_collect_order_id').val();
        const customNo = $('#custom_collect_input').val().trim();
        if (!customNo) {
            if (window.lisToast) window.lisToast('Please enter a sample number.', 'warning');
            return;
        }
        bootstrap.Modal.getInstance(document.getElementById('modalCustomCollect'))?.hide();
        window.collectSampleQuick(orderId, customNo);
    };

    // ══════════════════════════════════════════════════════════════
    // 3. Report Preview & In-Place Approval Modal Logic
    // ══════════════════════════════════════════════════════════════
    const reportPrintUrlTemplate = "{{ route('lab.reports.print', 999999) }}";
    const reportPdfUrlTemplate = "{{ route('lab.reports.pdf', 999999) }}";
    const reportViewUrlTemplate = "{{ route('lab.reports.view', 999999) }}";
    const resultEntryUrlTemplate = "{{ route('lab.results.entry', 999999) }}";

    let currentModalOrderId = null;
    let currentModalReportUrl = '';
    let currentModalResultUrl = '';
    let currentModalStatus = '';

    window.openApprovalModal = function(orderId, accessionNo, patientName, uhid, doctor, reportUrl, resultUrl, status) {
        currentModalOrderId = orderId;
        currentModalReportUrl = reportUrl || reportViewUrlTemplate.replace('999999', orderId);
        currentModalResultUrl = resultUrl || resultEntryUrlTemplate.replace('999999', orderId);
        currentModalStatus = status;

        const printPdfUrl = reportPrintUrlTemplate.replace('999999', orderId);
        const downloadPdfUrl = reportPdfUrlTemplate.replace('999999', orderId);

        $('#modalAccBadge').text(accessionNo || 'ACC-' + orderId);
        $('#modalPatientName').text(patientName || 'Patient');
        $('#modalUhid').text(uhid || '-');
        $('#modalDoctor').text(doctor || '-');
        $('#btnModalEditReport').attr('href', currentModalResultUrl);
        $('#btnModalNewTab').attr('href', currentModalReportUrl);
        $('#btnModalPrintReport').attr('href', printPdfUrl);
        $('#btnModalDownloadReport').attr('href', downloadPdfUrl);

        // Status Badge inside modal
        let statusBadgeHtml = '';
        if (status === 'completed') {
            statusBadgeHtml = '<span class="badge bg-success ms-1"><i class="uil uil-check-circle me-1"></i>Completed / Approved</span>';
            $('#btnModalApproveReport').hide();
            $('#btnModalRejectReport').show().html('<i class="uil uil-times me-1"></i> Disapprove');
        } else if (status === 'resulted') {
            statusBadgeHtml = '<span class="badge bg-warning text-dark ms-1"><i class="uil uil-clock me-1"></i>Approval Pending</span>';
            $('#btnModalApproveReport').show().html('<i class="uil uil-check-circle me-1"></i> Approve Report').prop('disabled', false);
            $('#btnModalRejectReport').show().html('<i class="uil uil-times me-1"></i> Reject');
        } else {
            statusBadgeHtml = '<span class="badge bg-info ms-1"><i class="uil uil-sync me-1"></i>In-Process Draft</span>';
            $('#btnModalApproveReport').show().html('<i class="uil uil-check-circle me-1"></i> Approve Report').prop('disabled', false);
            $('#btnModalRejectReport').show().html('<i class="uil uil-times me-1"></i> Disapprove');
        }
        $('#modalStatusBadge').html(statusBadgeHtml);

        // Show loading spinner, reset iframe
        const iframe = document.getElementById('modalReportIframe');
        $('#modalReportLoading').show();
        iframe.style.opacity = '0';
        iframe.onload = function() {
            $('#modalReportLoading').hide();
            iframe.style.opacity = '1';
        };
        iframe.src = reportUrl;

        const modalEl = document.getElementById('reportApprovalModal');
        const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
        modalInstance.show();
    };

    // Modal Action buttons
    $('#btnModalDirectPrint').on('click', function(e) {
        e.preventDefault();
        try {
            const iframe = document.getElementById('modalReportIframe');
            if (iframe && iframe.contentWindow) {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
                return;
            }
        } catch (err) {
            console.warn('Direct iframe print error, opening PDF instead', err);
        }
        if (currentModalOrderId) {
            window.open(reportPrintUrlTemplate.replace('999999', currentModalOrderId), '_blank');
        }
    });

    $('#btnModalApproveReport').on('click', function() {
        if (!currentModalOrderId) return;
        executeApproveOrder(currentModalOrderId);
    });

    // Reject button inside modal
    $('#btnModalRejectReport').on('click', function() {
        if (!currentModalOrderId) return;
        executeRejectOrder(currentModalOrderId);
    });

    // ══════════════════════════════════════════════════════════════
    // 4. In-Place Approve Order AJAX Action
    // ══════════════════════════════════════════════════════════════
    window.executeApproveOrder = function(orderId) {
        if (!orderId) return;

        const approveBtn = $('#btnModalApproveReport');
        approveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Approving...');

        $.ajax({
            url: "{{ route('lab.orders.approve') }}",
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                _token: $('meta[name="csrf-token"]').attr('content'),
                order_id: orderId
            }),
            success: function(res) {
                approveBtn.prop('disabled', false).html('<i class="uil uil-check-circle me-1"></i> Approve Report');

                // Close modal
                const modalEl = document.getElementById('reportApprovalModal');
                const modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) modalInstance.hide();

                if (window.lisToast) {
                    window.lisToast(res.message, 'success');
                }

                // 1. Update Status cells to Completed badge
                $('.status-col-' + orderId).html(`
                    <span class="status-badge status-badge-completed">
                        <i class="uil uil-check-circle me-1"></i>Completed
                    </span>
                `);

                // 2. Update Reporting and Approval cells
                $('.reporting-col-' + orderId).html(`<span class="bill-date-text">${res.reporting_time}</span>`);
                $('.approval-col-' + orderId).html(`
                    <button type="button" class="btn btn-sm btn-outline-success" style="font-size: 11px; padding: 2px 8px;" onclick="openApprovalModal(${orderId}, '${res.accession_no}', '', '', '', '${res.report_url}', '${res.result_entry_url}', 'completed')" title="View Approved Report">
                        <i class="uil uil-check-circle text-success me-1"></i>${res.approval_time}
                    </button>
                `);

                // 3. Update Action cells to Completed action set
                const printPdfUrl = res.print_pdf_url || reportPrintUrlTemplate.replace('999999', orderId);
                const downloadPdfUrl = res.pdf_url || reportPdfUrlTemplate.replace('999999', orderId);
                $('.action-col-' + orderId).html(`
                    <div class="d-inline-flex align-items-center gap-1">
                        <a href="${printPdfUrl}" target="_blank" class="btn btn-action-print" title="Print Report (PDF)">
                            <i class="uil uil-print me-1"></i>Print
                        </a>
                        <a href="${downloadPdfUrl}" target="_blank" class="btn btn-action-download" title="Download Report (with Header)">
                            <i class="uil uil-file-download me-1"></i>Download
                        </a>
                        <button type="button" class="btn btn-action-disapprove" onclick="disapproveOrder(${orderId}, this)" title="Disapprove Report">
                            <i class="uil uil-times me-1"></i>Disapprove
                        </button>
                    </div>
                `);

                // 4. Update row classes
                $('tr[data-order-id="' + orderId + '"]').removeClass('row-pending row-collected').addClass('row-completed');
            },
            error: function(xhr) {
                approveBtn.prop('disabled', false).html('<i class="uil uil-check-circle me-1"></i> Approve Report');
                let err = 'Failed to approve report.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    err = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.critical) {
                    err = xhr.responseJSON.critical[0];
                }
                if (window.lisToast) {
                    window.lisToast(err, 'danger');
                } else {
                    alert(err);
                }
            }
        });
    };

    // ══════════════════════════════════════════════════════════════
    // 5. In-Place Reject / Disapprove Order AJAX Action
    // ══════════════════════════════════════════════════════════════
    window.executeRejectOrder = function(orderId, triggerBtn) {
        if (!orderId) return;

        const reason = prompt('Please enter remarks for rejection / disapproval:', 'Correction required in test parameters');
        if (reason === null) return; // user cancelled

        const modalRejectBtn = $('#btnModalRejectReport');
        const triggerEl = triggerBtn ? $(triggerBtn) : null;
        const oldTriggerHtml = triggerEl ? triggerEl.html() : '';

        modalRejectBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Disapproving...');
        if (triggerEl) {
            triggerEl.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>');
        }

        $.ajax({
            url: "{{ route('lab.orders.reject') }}",
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                _token: $('meta[name="csrf-token"]').attr('content'),
                order_id: orderId,
                remarks: reason
            }),
            success: function(res) {
                modalRejectBtn.prop('disabled', false).html('<i class="uil uil-times me-1"></i> Reject / Disapprove');
                if (triggerEl) triggerEl.prop('disabled', false).html(oldTriggerHtml);

                // Close modal
                const modalEl = document.getElementById('reportApprovalModal');
                const modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) modalInstance.hide();

                if (window.lisToast) {
                    window.lisToast(res.message, 'warning');
                }

                // 1. Update Status cells to In-Process badge
                $('.status-col-' + orderId).html(`
                    <span class="status-badge status-badge-inprocess">
                        <i class="uil uil-sync me-1"></i>In-Process
                    </span>
                `);

                // 2. Update Reporting and Approval cells
                $('.reporting-col-' + orderId).html(`
                    <a href="${res.result_entry_url}" class="btn btn-start-reporting" title="Enter Results Desk">
                        ➔ Start
                    </a>
                `);
                $('.approval-col-' + orderId).html(`
                    <button type="button" class="btn btn-sm btn-outline-secondary" style="font-size: 10.5px; padding: 2px 7px;" onclick="openApprovalModal(${orderId}, '${res.accession_no}', '', '', '', '${res.report_url}', '${res.result_entry_url}', 'in_process')" title="Preview Report Draft">
                        <i class="uil uil-eye me-1"></i>Preview
                    </button>
                `);

                // 3. Update Action cells to In-Process action set
                const barcodeUrl = "{{ route('lab.billing.barcode', 999999) }}".replace('999999', orderId);
                const receiptUrl = "{{ route('lab.billing.receipt', 999999) }}".replace('999999', orderId);

                $('.action-col-' + orderId).html(`
                    <div class="d-inline-flex align-items-center gap-1">
                        <a href="${res.result_entry_url}" class="btn btn-start-reporting" title="Open Result Entry Desk">
                            ➔ Enter
                        </a>
                        <button type="button" class="btn btn-action-download" onclick="openApprovalModal(${orderId}, '${res.accession_no}', '', '', '', '${res.report_url}', '${res.result_entry_url}', 'in_process')" title="Preview Draft Report">
                            <i class="uil uil-file-alt me-1"></i>Preview
                        </button>
                        <button type="button" class="btn btn-mini-barcode" onclick="printDirectPreview('${barcodeUrl}')" title="Print Barcode">
                            <i class="uil uil-barcode me-1"></i>Barcode
                        </button>
                        <button type="button" class="btn btn-mini-receipt" onclick="printDirectPreview('${receiptUrl}')" title="Print Bill Receipt">
                            <i class="uil uil-receipt me-1"></i>Bill
                        </button>
                    </div>
                `);

                // 4. Update row classes
                $('tr[data-order-id="' + orderId + '"]').removeClass('row-completed row-pending').addClass('row-collected');
            },
            error: function(xhr) {
                modalRejectBtn.prop('disabled', false).html('<i class="uil uil-times me-1"></i> Reject / Disapprove');
                if (triggerEl) triggerEl.prop('disabled', false).html(oldTriggerHtml);
                let err = 'Failed to disapprove order.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    err = xhr.responseJSON.message;
                }
                if (window.lisToast) {
                    window.lisToast(err, 'danger');
                } else {
                    alert(err);
                }
            }
        });
    };

    window.disapproveOrder = function(orderId, triggerBtn) {
        executeRejectOrder(orderId, triggerBtn);
    };

    // ══════════════════════════════════════════════════════════════
    // 6. Keyboard Navigation (J / K / Arrows / Enter) & Row Select
    // ══════════════════════════════════════════════════════════════
    let currentIdx = 0;
    const rows = $('.order-item-row[data-url]');

    function highlightRow(idx) {
        if (!rows.length) return;
        rows.removeClass('selected').css('outline', 'none');
        const target = rows.eq(idx);
        target.addClass('selected').css('outline', '2px solid #0284c7');
        target[0]?.scrollIntoView({ block: 'nearest' });
    }

    if (rows.length) highlightRow(0);

    $(document).on('keydown', function(e) {
        const tag = (e.target.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select') return;

        // Skip keyboard shortcuts if modal is open
        if ($('#reportApprovalModal').hasClass('show')) return;

        if (e.key === 'j' || e.key === 'ArrowDown') {
            e.preventDefault();
            if (currentIdx < rows.length - 1) {
                currentIdx++;
                highlightRow(currentIdx);
            }
        } else if (e.key === 'k' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (currentIdx > 0) {
                currentIdx--;
                highlightRow(currentIdx);
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const url = rows.eq(currentIdx).data('url');
            if (url) window.location.href = url;
        }
    });

    rows.on('click', function(e) {
        if ($(e.target).closest('button, a, input').length === 0) {
            currentIdx = rows.index(this);
            highlightRow(currentIdx);
        }
    });

    rows.on('dblclick', function(e) {
        if ($(e.target).closest('button, a, input').length === 0) {
            const url = $(this).data('url');
            if (url) window.location.href = url;
        }
    });

    // ══════════════════════════════════════════════════════════════
    // 7. Quick Print Settings AJAX Handler
    // ══════════════════════════════════════════════════════════════
    window.previewImageFile = function(input, previewSelector, noTextSelector) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $(previewSelector).attr('src', e.target.result).show();
                if (noTextSelector) $(noTextSelector).hide();
            };
            reader.readAsDataURL(input.files[0]);
        }
    };

    $('#quickPrintSettingsForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#btnSavePrintSettings');
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

        const formData = new FormData(this);

        $.ajax({
            url: "{{ route('lab.settings.update-print') }}",
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(res) {
                btn.prop('disabled', false).html('<i class="uil uil-save me-1"></i> Save Print Settings');
                if (res.success) {
                    if (window.lisToast) {
                        window.lisToast('Print margin and header/footer settings saved successfully!', 'success');
                    }
                    const modalEl = document.getElementById('printSettingsModal');
                    const modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) modalInstance.hide();
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html('<i class="uil uil-save me-1"></i> Save Print Settings');
                alert(xhr.responseJSON?.message || 'Error saving print settings.');
            }
        });
    });

})(jQuery);
</script>
@endpush
