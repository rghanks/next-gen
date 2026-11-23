@extends('lab.layouts.app')

@section('title', 'Results Entry Worklist | ')
@section('context', 'results')

@push('styles')
<style>
    /* ══════════════════════════════════════════════════════════════ */
    /* FILTER CARD & INPUT STYLES (MATCHING LAB ORDERS THEME)         */
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

    /* Status & Priority Radio Pill Buttons */
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
    /* RESULTS WORKLIST TABLE - EXACT LINE HIGHLIGHTS & SPACING       */
    /* ══════════════════════════════════════════════════════════════ */
    .results-table-container {
        background: #ffffff;
        border: 2px solid #94a3b8 !important;
        border-radius: 6px;
        overflow-x: auto;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }

    .table-lab-results {
        width: 100%;
        margin-bottom: 0;
        border-collapse: collapse !important;
        border: 2px solid #94a3b8 !important;
        font-size: 12px;
    }

    /* Top Row (Header) - Heavy Border */
    .table-lab-results thead tr {
        border-top: 2.5px solid #0f172a !important;
        border-bottom: 2.5px solid #0f172a !important;
    }

    .table-lab-results thead th {
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
    .table-lab-results th,
    .table-lab-results td {
        border: 1px solid #cbd5e1 !important;
        padding: 7px 10px !important;
        vertical-align: middle !important;
        line-height: 1.35;
    }

    /* Bottom Row - Heavy Border */
    .table-lab-results tbody tr:last-child td {
        border-bottom: 2.5px solid #94a3b8 !important;
    }

    /* Row Background States & Prominent Hover Highlights */
    .table-lab-results tbody tr {
        transition: background-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .table-lab-results tbody tr.row-pending {
        background-color: #ffffff !important;
    }

    .table-lab-results tbody tr.row-pending:hover td {
        background-color: #e2e8f0 !important;
        cursor: pointer;
    }

    .table-lab-results tbody tr.row-collected {
        background-color: #f0fdfa !important;
    }

    .table-lab-results tbody tr.row-collected:hover td {
        background-color: #cffafe !important;
        cursor: pointer;
    }

    /* Soft mint green row background for completed tests */
    .table-lab-results tbody tr.row-completed {
        background-color: #e8f8f0 !important;
    }

    .table-lab-results tbody tr.row-completed:hover td {
        background-color: #bbf7d0 !important;
        cursor: pointer;
    }

    .table-lab-results tbody tr.selected td {
        background-color: #dbeafe !important;
        box-shadow: inset 0 0 0 1px #3b82f6;
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
    .btn-sample-id {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%) !important;
        border: 1px solid #16a34a !important;
        color: #ffffff !important;
        font-weight: 700;
        font-size: 11px;
        padding: 3px 10px !important;
        border-radius: 4px;
        white-space: nowrap;
        text-align: center;
        box-shadow: 0 1px 3px rgba(22,163,74,0.3);
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .btn-sample-id:hover {
        background: #15803d !important;
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
        padding: 4px 11px !important;
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
        color: #ffffff !important;
        box-shadow: 0 2px 5px rgba(15,23,42,0.45);
        transform: translateY(-1px);
    }

    /* Status Badges */
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

    .status-badge-resulted {
        background-color: #e0e7ff !important;
        color: #4338ca !important;
        border: 1px solid #c7d2fe !important;
        box-shadow: 0 1px 2px rgba(67,56,202,0.12);
    }

    .status-badge-pending {
        background-color: #f1f5f9 !important;
        color: #64748b !important;
        border: 1px solid #cbd5e1 !important;
    }

    /* Action Buttons */
    .btn-action-preview {
        background-color: #0284c7 !important;
        border: 1px solid #0369a1 !important;
        color: #ffffff !important;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 9px !important;
        border-radius: 4px;
        box-shadow: 0 1px 2px rgba(2,132,199,0.2);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        transition: all 0.15s ease;
    }

    .btn-action-preview:hover {
        background-color: #0369a1 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 4px rgba(2,132,199,0.35);
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

    .badge-stat {
        background-color: #ef4444 !important;
        color: #ffffff !important;
        font-weight: 700;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 4px;
        letter-spacing: 0.5px;
    }

    .badge-routine {
        background-color: #f1f5f9;
        color: #475569;
        font-size: 10px;
        font-weight: 500;
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px solid #cbd5e1;
    }

    .investigation-pill {
        display: inline-block;
        font-size: 11px;
        font-weight: 600;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        color: #1e293b;
        padding: 2px 7px;
        border-radius: 4px;
        margin: 1px 2px 1px 0;
        white-space: nowrap;
    }

    .kbd-hint {
        display: inline-block;
        padding: 1px 5px;
        font-size: 11px;
        font-family: monospace;
        background-color: #f1f5f9;
        border: 1px solid #cbd5e1;
        border-radius: 3px;
        color: #475569;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-3 py-2">

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- TOP FILTER SECTION (EXACT BENCHMARK LAYOUT)                    -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <div class="card filter-card mb-3">
        <div class="card-body p-2">
            <form id="results-filter-form" method="GET" action="{{ route('lab.results.index') }}">
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
                                        <input type="text" id="filter_bill_id" name="bill_id" class="form-control" placeholder="Bill ID / Acc #" value="{{ $billId ?? '' }}">
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

                    <!-- Right: Status Radio Pills, Priority Radio Pills & Action Controls -->
                    <div class="col-xl-7 col-lg-7 col-12">
                        <div class="d-flex flex-column gap-1">

                            <!-- Row 1: Status Lifecycle Filter Pills -->
                            <div class="btn-group btn-group-sm w-100 btn-filter-group" role="group">
                                <input type="radio" class="btn-check" name="status" id="btnStatusAll" value="all" autocomplete="off" {{ ($status ?? 'pending') === 'all' ? 'checked' : '' }} onchange="$('#results-filter-form').submit();">
                                <label class="btn {{ ($status ?? 'pending') === 'all' ? 'btn-success text-white' : 'btn-outline-success' }} px-3" for="btnStatusAll">
                                    All <span class="badge bg-light text-dark ms-1">{{ $counts['all'] ?? 0 }}</span>
                                </label>

                                <input type="radio" class="btn-check" name="status" id="btnStatusPending" value="pending" autocomplete="off" {{ ($status ?? 'pending') === 'pending' ? 'checked' : '' }} onchange="$('#results-filter-form').submit();">
                                <label class="btn {{ ($status ?? 'pending') === 'pending' ? 'btn-info text-white' : 'btn-outline-info' }} px-3" for="btnStatusPending">
                                    Reporting Pending <span class="badge bg-light text-dark ms-1">{{ $counts['pending'] ?? 0 }}</span>
                                </label>

                                <input type="radio" class="btn-check" name="status" id="btnStatusResulted" value="resulted" autocomplete="off" {{ ($status ?? 'pending') === 'resulted' ? 'checked' : '' }} onchange="$('#results-filter-form').submit();">
                                <label class="btn {{ ($status ?? 'pending') === 'resulted' ? 'btn-warning text-dark' : 'btn-outline-warning' }} px-3" for="btnStatusResulted">
                                    Awaiting Verification <span class="badge bg-light text-dark ms-1">{{ $counts['resulted'] ?? 0 }}</span>
                                </label>

                                <input type="radio" class="btn-check" name="status" id="btnStatusCompleted" value="completed" autocomplete="off" {{ ($status ?? 'pending') === 'completed' ? 'checked' : '' }} onchange="$('#results-filter-form').submit();">
                                <label class="btn {{ ($status ?? 'pending') === 'completed' ? 'btn-success text-white' : 'btn-outline-success' }} px-3" for="btnStatusCompleted">
                                    Completed / Verified <span class="badge bg-light text-dark ms-1">{{ $counts['completed'] ?? 0 }}</span>
                                </label>
                            </div>

                            <!-- Row 2: Priority Filter Pills (All / Routine / STAT) -->
                            <div class="d-flex align-items-center">
                                <div class="btn-group btn-group-sm btn-filter-group" role="group">
                                    <input type="radio" class="btn-check" name="priority" id="btnPriorityAll" value="" autocomplete="off" {{ empty($priority) ? 'checked' : '' }} onchange="$('#results-filter-form').submit();">
                                    <label class="btn {{ empty($priority) ? 'btn-success text-white' : 'btn-outline-success' }} px-4" for="btnPriorityAll">All Priorities</label>

                                    <input type="radio" class="btn-check" name="priority" id="btnPriorityRoutine" value="routine" autocomplete="off" {{ ($priority ?? '') === 'routine' ? 'checked' : '' }} onchange="$('#results-filter-form').submit();">
                                    <label class="btn {{ ($priority ?? '') === 'routine' ? 'btn-info text-white' : 'btn-outline-info' }} px-4" for="btnPriorityRoutine">Routine</label>

                                    <input type="radio" class="btn-check" name="priority" id="btnPriorityStat" value="stat" autocomplete="off" {{ ($priority ?? '') === 'stat' ? 'checked' : '' }} onchange="$('#results-filter-form').submit();">
                                    <label class="btn {{ ($priority ?? '') === 'stat' ? 'btn-danger text-white' : 'btn-outline-danger' }} px-4" for="btnPriorityStat">
                                        <i class="uil uil-exclamation-triangle me-1"></i> STAT (Emergency)
                                    </label>
                                </div>
                            </div>

                            <!-- Row 3: Select Services Dropdown + Search + Clear Filter + Refresh -->
                            <div class="d-flex align-items-center gap-2 flex-wrap pt-1">
                                <div style="flex: 1; min-width: 200px;">
                                    <select name="service_id" id="filter_service_id" class="form-select form-select-sm" style="height: 31px; font-size: 11.5px;">
                                        <option value="">Select Test / Investigation</option>
                                        @foreach(($services ?? []) as $srv)
                                            <option value="{{ $srv->id }}" {{ (string)($serviceId ?? '') === (string)$srv->id ? 'selected' : '' }}>
                                                {{ $srv->service }} {{ $srv->short_code ? '('.$srv->short_code.')' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div style="flex: 1; min-width: 150px;">
                                    <input type="search" name="q" value="{{ $term ?? '' }}" class="form-control form-control-sm" placeholder="Quick Search Keyword..." style="height: 31px; font-size: 11.5px;">
                                </div>
                                <button type="submit" class="btn btn-sm text-white px-3" style="background-color: #252f4a; height: 31px; font-size: 11.5px; font-weight: 600;">
                                    <i class="uil uil-search me-1"></i> Search
                                </button>
                                <a href="{{ route('lab.results.index') }}" class="btn btn-sm btn-outline-danger px-2" style="height: 31px; font-size: 11.5px; display: inline-flex; align-items: center;">
                                    <i class="uil uil-times me-1"></i> Clear Filter
                                </a>
                                <button type="button" class="btn btn-sm text-white px-3" style="background-color: #0284c7; height: 31px; font-size: 11.5px; font-weight: 500;" onclick="window.location.reload();">
                                    <i class="uil uil-refresh me-1"></i> Refresh
                                </button>
                            </div>

                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- RESULTS WORKLIST TABLE (HIGH CONTRAST & CRISP BORDERS)         -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <div class="results-table-container">
        <table class="table table-lab-results" id="results-worklist-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 75px;">Bill ID</th>
                    <th style="min-width: 140px;">Patient</th>
                    <th class="text-center" style="width: 60px;">UHID</th>
                    <th style="min-width: 130px;">Doctor</th>
                    <th style="width: 125px;">Bill Date</th>
                    <th style="min-width: 170px;">Investigation</th>
                    <th class="text-center" style="width: 105px;">Sample ID</th>
                    <th class="text-center" style="width: 75px;">Priority</th>
                    <th class="text-center" style="width: 130px;">TAT Due</th>
                    <th class="text-center" style="width: 125px;">Status</th>
                    <th class="text-center" style="min-width: 135px;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $idx => $order)
                    @php
                        $patient = $order->patient;
                        $stateVal = $order->status->value ?? 'ordered';
                        $isCompleted = in_array($stateVal, ['verified', 'released'], true);
                        $isResulted = $stateVal === 'resulted';
                        $isInProcess = $stateVal === 'in_process';
                        $isCollected = in_array($stateVal, ['collected', 'received', 'in_process', 'resulted', 'verified', 'released'], true);
                        $rowClass = $isCompleted ? 'row-completed' : ($isCollected ? 'row-collected' : 'row-pending');
                        $doctorName = $patient?->referring_doctor ?: 'Dr. Self';
                        $items = $order->items;
                        $specimens = $order->specimens;
                        $firstBarcode = $specimens->first()?->barcode ?? ($order->accession_no ?? '-');

                        $isStat = $order->priority === 'stat';
                        $earliestDue = $order->items->min('tat_due_at');
                        $isOverdue = $earliestDue && \Carbon\Carbon::now()->isAfter($earliestDue);
                        $isDueSoon = $earliestDue && ! $isOverdue && \Carbon\Carbon::now()->diffInMinutes($earliestDue) <= 60;
                    @endphp

                    <tr class="{{ $rowClass }}" data-index="{{ $idx }}" data-url="{{ route('lab.results.entry', $order->id) }}">
                        <!-- 1. Bill ID / Accession -->
                        <td class="text-center fw-bold">
                            <a href="{{ route('lab.results.entry', $order->id) }}" class="bill-id-link">
                                {{ $order->bill_id ?: $order->accession_no }}
                            </a>
                        </td>

                        <!-- 2. Patient -->
                        <td>
                            <div class="patient-name-text">
                                {{ $patient?->full_name ?: ($patient?->Fname . ' ' . $patient?->Lname) ?: 'Unknown Patient' }}
                            </div>
                            <div class="text-muted" style="font-size: 11px;">
                                @if($patient?->age) {{ $patient->age }}Y @endif
                                @if($patient?->sex) / {{ strtoupper($patient->sex) }} @endif
                                @if($patient?->mobile_no) • {{ $patient->mobile_no }} @endif
                            </div>
                        </td>

                        <!-- 3. UHID -->
                        <td class="text-center fw-medium">
                            <span class="text-primary fw-bold">{{ $patient?->uhid ?: ($patient?->id ?: '-') }}</span>
                        </td>

                        <!-- 4. Doctor -->
                        <td class="text-nowrap">
                            <span class="doctor-name-text">{{ $doctorName }}</span>
                        </td>

                        <!-- 5. Bill Date -->
                        <td class="text-nowrap">
                            <span class="bill-date-text">
                                {{ $order->created_at ? $order->created_at->format('d/m/y h:i A') : '-' }}
                            </span>
                        </td>

                        <!-- 6. Investigation / Tests -->
                        <td>
                            @forelse($items as $item)
                                <span class="investigation-pill">
                                    {{ $item->service?->service ?: 'Test #'.$item->service_id }}
                                </span>
                            @empty
                                <span class="text-muted" style="font-size: 11px;">Investigation #{{ $order->accession_no }}</span>
                            @endforelse
                        </td>

                        <!-- 7. Sample ID / Barcode -->
                        <td class="text-center">
                            @if($specimens->isNotEmpty())
                                <button type="button" class="btn btn-sample-id" onclick="event.stopPropagation(); printDirectPreview('{{ route('lab.billing.barcode', $order->id) }}');" title="Click to preview & print tube barcode label">
                                    <i class="uil uil-tag-alt me-1"></i>{{ $firstBarcode }}
                                </button>
                            @else
                                <span class="badge bg-light text-secondary border" style="font-size: 10px;">{{ $order->accession_no }}</span>
                            @endif
                        </td>

                        <!-- 8. Priority -->
                        <td class="text-center">
                            @if($isStat)
                                <span class="badge-stat">STAT</span>
                            @else
                                <span class="badge-routine">Routine</span>
                            @endif
                        </td>

                        <!-- 9. TAT Due -->
                        <td class="text-center text-nowrap">
                            @if($earliestDue)
                                <span style="font-size: 11px; font-weight: 600; color: {{ $isOverdue ? '#dc2626' : ($isDueSoon ? '#d97706' : '#1e293b') }};">
                                    {{ $earliestDue->format('d/m H:i') }}
                                    @if($isOverdue)
                                        <div class="text-danger fw-bold" style="font-size: 10px;"><i class="uil uil-exclamation-triangle"></i> OVERDUE</div>
                                    @elseif($isDueSoon)
                                        <div class="text-warning fw-bold" style="font-size: 10px;">({{ \Carbon\Carbon::now()->diffInMinutes($earliestDue) }}m left)</div>
                                    @endif
                                </span>
                            @else
                                <span class="text-muted" style="font-size: 11px;">-</span>
                            @endif
                        </td>

                        <!-- 10. Status -->
                        <td class="text-center">
                            @if($isCompleted)
                                <span class="status-badge status-badge-completed">
                                    <i class="uil uil-check-circle me-1"></i>Completed
                                </span>
                            @elseif($isResulted)
                                <span class="status-badge status-badge-resulted">
                                    <i class="uil uil-file-check-alt me-1"></i>Resulted
                                </span>
                            @elseif($isInProcess)
                                <span class="status-badge status-badge-inprocess">
                                    <i class="uil uil-hourglass me-1"></i>In-Process
                                </span>
                            @elseif($isCollected)
                                <span class="status-badge status-badge-collected">
                                    <i class="uil uil-flask me-1"></i>Sample Collected
                                </span>
                            @else
                                <span class="status-badge status-badge-pending">
                                    <i class="uil uil-clock me-1"></i>Pending
                                </span>
                            @endif
                        </td>

                        <!-- 11. Action Buttons -->
                        <td class="text-center text-nowrap">
                            <div class="d-inline-flex align-items-center gap-1">
                                <!-- Enter Results Button -->
                                <a href="{{ route('lab.results.entry', $order->id) }}" class="btn btn-start-reporting" title="Open Results Entry Desk">
                                    ➔ Enter Results
                                </a>

                                <!-- Preview Report Button (if resulted or completed) -->
                                @if($isResulted || $isCompleted)
                                    <button type="button" class="btn btn-action-preview" onclick="event.stopPropagation(); printDirectPreview('{{ route('lab.reports.view', $order->id) }}');" title="Direct Report Preview">
                                        <i class="uil uil-file-alt me-1"></i>Preview
                                    </button>
                                @endif

                                <!-- Quick Bill Link -->
                                <button type="button" class="btn btn-mini-receipt" onclick="event.stopPropagation(); printDirectPreview('{{ route('lab.billing.receipt', $order->id) }}');" title="Preview Bill Receipt">
                                    <i class="uil uil-receipt"></i>
                                </button>

                                <!-- Quick Tube Barcode Print -->
                                <button type="button" class="btn btn-mini-barcode" onclick="event.stopPropagation(); printDirectPreview('{{ route('lab.billing.barcode', $order->id) }}');" title="Print Barcode Label">
                                    <i class="uil uil-tag-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="text-center py-4 text-muted">
                            <div class="mb-2"><i class="uil uil-clipboard-blank" style="font-size: 28px; color: #94a3b8;"></i></div>
                            <div class="fw-semibold">No lab test orders found matching your filters.</div>
                            <div class="small text-muted mt-1">Try clearing some filter fields or switching between tabs.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Table Footer with Shortcuts & Pagination -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2 px-1">
        <div class="text-muted" style="font-size: 11.5px;">
            <span class="kbd-hint">J</span> / <span class="kbd-hint">K</span> or <span class="kbd-hint">↑</span> / <span class="kbd-hint">↓</span> Navigate &nbsp;•&nbsp; 
            <span class="kbd-hint">Enter</span> Open Results &nbsp;•&nbsp; 
            Showing <strong>{{ $orders->firstItem() ?? 0 }}</strong> to <strong>{{ $orders->lastItem() ?? 0 }}</strong> of <strong>{{ $orders->total() }}</strong> records
        </div>
        <div>
            {{ $orders->withQueryString()->links() }}
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
$(function() {
    let currentIndex = 0;
    const rows = $('#results-worklist-table tbody tr[data-url]');

    function highlightRow(index) {
        if (!rows.length) return;
        rows.removeClass('selected');
        const target = rows.eq(index);
        target.addClass('selected');
        target[0]?.scrollIntoView({ block: 'nearest' });
    }

    if (rows.length) highlightRow(0);

    // Keyboard J / K or Arrow navigation & Enter to open
    $(document).on('keydown', function(e) {
        const tag = (e.target.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select') return;

        if (e.key === 'j' || e.key === 'ArrowDown') {
            e.preventDefault();
            if (currentIndex < rows.length - 1) {
                currentIndex++;
                highlightRow(currentIndex);
            }
        } else if (e.key === 'k' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (currentIndex > 0) {
                currentIndex--;
                highlightRow(currentIndex);
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const url = rows.eq(currentIndex).data('url');
            if (url) window.location.href = url;
        }
    });

    rows.on('click', function(e) {
        // Only select row if not clicking directly on button or link
        if ($(e.target).closest('button, a, input').length === 0) {
            currentIndex = $(this).data('index');
            highlightRow(currentIndex);
        }
    });

    // Double-click row opens results
    rows.on('dblclick', function(e) {
        if ($(e.target).closest('button, a, input').length === 0) {
            const url = $(this).data('url');
            if (url) window.location.href = url;
        }
    });

    // ══════════════════════════════════════════════════════════════
    // Direct Browser Print Preview (Single Print, Zero Modals)
    // ══════════════════════════════════════════════════════════════
    let isPrinting = false;
    window.printDirectPreview = function(url) {
        if (!url) return;
        if (isPrinting) return;
        isPrinting = true;

        $('#direct-print-frame').remove();

        const iframe = $('<iframe>', {
            id: 'direct-print-frame',
            style: 'position: fixed; right: 0; bottom: 0; width: 0; height: 0; border: 0; visibility: hidden;'
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
                } finally {
                    setTimeout(() => { isPrinting = false; }, 1000);
                }
            }, 120);
        });

        $('body').append(iframe);
        iframe.attr('src', url);
    };
});
</script>
@endpush
