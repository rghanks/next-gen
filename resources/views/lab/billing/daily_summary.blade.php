@extends('lab.layouts.app')

@section('title', 'All Bills & Revenue Report | LIS')
@section('context', 'billing')
@section('page-title', 'All Bills & Daily Summary')
@section('page-description', 'Comprehensive billing ledger, revenue analytics, payment settlements, and collections report.')

@push('styles')
<style>
    /* Top Header Card */
    .summary-header-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        padding: 10px 14px;
        margin-bottom: 8px;
    }

    /* KPI Cards */
    .kpi-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 10px 14px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .kpi-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 3px 6px rgba(0,0,0,0.08);
    }

    .kpi-title {
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .kpi-value {
        font-size: 19px;
        font-weight: 800;
        color: #0f172a;
        margin-top: 2px;
        line-height: 1.2;
    }

    .kpi-icon-wrap {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    /* Filter & Tab Card */
    .summary-filter-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        padding: 8px 12px;
        margin-bottom: 8px;
    }

    .filter-tab-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        font-size: 11.5px;
        font-weight: 600;
        border-radius: 5px;
        text-decoration: none;
        border: 1px solid #cbd5e1;
        background-color: #ffffff;
        color: #334155;
        transition: all 0.15s ease;
    }

    .filter-tab-pill:hover {
        background-color: #f1f5f9;
        color: #0f172a;
        border-color: #94a3b8;
    }

    .filter-tab-pill.active {
        background-color: #0284c7 !important;
        border-color: #0369a1 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 4px rgba(2,132,199,0.25);
    }

    .filter-tab-pill .pill-badge {
        font-size: 10px;
        padding: 1.5px 6px;
        border-radius: 10px;
        background: #e2e8f0;
        color: #1e293b;
        font-weight: 700;
    }

    .filter-tab-pill.active .pill-badge {
        background: rgba(255, 255, 255, 0.28);
        color: #ffffff;
    }

    /* Quick Date Range Buttons */
    .btn-quick-date {
        font-size: 10.5px;
        padding: 2px 7px;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        color: #475569;
        border-radius: 4px;
        text-decoration: none;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .btn-quick-date:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    /* Main Bills Table */
    .bills-table-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.04);
        overflow: hidden;
    }

    .table-container {
        max-height: calc(100vh - 280px);
        overflow-y: auto;
    }

    .table-bills {
        width: 100%;
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .table-bills thead th {
        background-color: #252f4a !important;
        color: #ffffff !important;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.3px;
        text-transform: uppercase;
        padding: 7px 8px;
        vertical-align: middle;
        border-top: none;
        border-bottom: 2px solid #1e293b;
        border-right: 1px solid rgba(255, 255, 255, 0.12);
        position: sticky;
        top: 0;
        z-index: 2;
        white-space: nowrap;
    }

    .table-bills thead th:last-child {
        border-right: none;
    }

    .table-bills tbody td {
        padding: 5px 8px;
        font-size: 11.5px;
        vertical-align: middle;
        border-bottom: 1px solid #e2e8f0;
        border-right: 1px solid #f1f5f9;
        color: #1e293b;
    }

    .table-bills tbody td:last-child {
        border-right: none;
    }

    .table-bills tbody tr:nth-child(even) {
        background-color: #f8fafc;
    }

    .table-bills tbody tr:hover {
        background-color: #e0f2fe !important;
        transition: background-color 0.12s ease;
    }

    /* Bill ID Link */
    .bill-link {
        font-weight: 700;
        color: #0284c7;
        text-decoration: none;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    }

    .bill-link:hover {
        color: #0369a1;
        text-decoration: underline;
    }

    /* Status Badges */
    .status-badge-paid {
        background-color: #dcfce7 !important;
        color: #15803d !important;
        border: 1px solid #86efac !important;
        font-size: 10.5px;
        font-weight: 700;
        padding: 2.5px 8px;
        border-radius: 4px;
        white-space: nowrap;
    }

    .status-badge-partial {
        background-color: #fef3c7 !important;
        color: #b45309 !important;
        border: 1px solid #fcd34d !important;
        font-size: 10.5px;
        font-weight: 700;
        padding: 2.5px 8px;
        border-radius: 4px;
        white-space: nowrap;
    }

    .status-badge-due {
        background-color: #fee2e2 !important;
        color: #b91c1c !important;
        border: 1px solid #fca5a5 !important;
        font-size: 10.5px;
        font-weight: 700;
        padding: 2.5px 8px;
        border-radius: 4px;
        white-space: nowrap;
    }

    /* Action Buttons */
    .btn-action-edit {
        background-color: #0284c7 !important;
        border: 1px solid #0369a1 !important;
        color: #ffffff !important;
        font-size: 11px;
        font-weight: 600;
        padding: 2.5px 8px !important;
        border-radius: 4px;
        box-shadow: 0 1px 2px rgba(2,132,199,0.2);
        cursor: pointer;
        transition: all 0.15s ease;
        white-space: nowrap;
    }

    .btn-action-edit:hover {
        background-color: #0369a1 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 4px rgba(2,132,199,0.35);
    }

    .btn-action-print {
        background-color: #1e293b !important;
        border: 1px solid #0f172a !important;
        color: #ffffff !important;
        font-size: 11px;
        font-weight: 600;
        padding: 2.5px 8px !important;
        border-radius: 4px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        cursor: pointer;
        transition: all 0.15s ease;
        white-space: nowrap;
    }

    .btn-action-print:hover {
        background-color: #0f172a !important;
        color: #ffffff !important;
    }

    .btn-mini-barcode {
        background-color: #0d9488 !important;
        border: 1px solid #0f766e !important;
        color: #ffffff !important;
        font-size: 11px;
        font-weight: 600;
        padding: 2.5px 7px !important;
        border-radius: 4px;
        box-shadow: 0 1px 2px rgba(13,148,136,0.2);
        cursor: pointer;
        transition: all 0.15s ease;
        white-space: nowrap;
    }

    .btn-mini-barcode:hover {
        background-color: #0f766e !important;
        color: #ffffff !important;
    }

    .service-badge {
        font-size: 10.5px;
        background: #f1f5f9;
        color: #1e293b;
        border: 1px solid #cbd5e1;
        padding: 1.5px 6px;
        border-radius: 4px;
        display: inline-block;
        margin: 1px;
        max-width: 170px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        vertical-align: middle;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-2 py-1">

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- 1. HEADER SECTION & MAIN ACTIONS                               --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="summary-header-card d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #e0f2fe; color: #0284c7;">
                <i class="uil uil-bill fs-5"></i>
            </div>
            <div>
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <span>All Bills & Revenue Report</span>
                    <span class="badge bg-primary text-white" style="font-size: 11px;">{{ number_format($bills->total()) }} Records</span>
                </h6>
                <div class="text-muted" style="font-size: 11px;">Financial ledger, collection analytics, pending dues, and real-time settlement desk.</div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('lab.billing.index') }}" class="btn btn-sm btn-success text-white fw-semibold shadow-sm" style="background-color: #16a34a; border-color: #15803d; font-size: 11.5px; padding: 4px 12px;">
                <i class="uil uil-plus-circle me-1"></i> New OPD Bill
            </a>
            <a href="{{ route('lab.billing.daily-summary') }}" class="btn btn-sm btn-outline-secondary" style="font-size: 11.5px; padding: 4px 10px;" title="Reset filters & reload report">
                <i class="uil uil-refresh me-1"></i> Refresh
            </a>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- 2. KPI SUMMARY CARDS                                           --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="row g-2 mb-2">
        <!-- 1. Total Bills Count -->
        <div class="col-sm-6 col-lg-3">
            <div class="kpi-card">
                <div>
                    <div class="kpi-title">Total Bills</div>
                    <div class="kpi-value text-dark">{{ number_format($countAll) }}</div>
                    <div class="text-muted small" style="font-size: 10px;">Transactions in period</div>
                </div>
                <div class="kpi-icon-wrap" style="background-color: #e0f2fe; color: #0284c7;">
                    <i class="uil uil-receipt"></i>
                </div>
            </div>
        </div>

        <!-- 2. Total Billed Net -->
        <div class="col-sm-6 col-lg-3">
            <div class="kpi-card">
                <div>
                    <div class="kpi-title">Total Net Billed</div>
                    <div class="kpi-value text-dark">₹{{ number_format($totalBilledNet, 2) }}</div>
                    <div class="text-muted small" style="font-size: 10px;">Gross revenue after discounts</div>
                </div>
                <div class="kpi-icon-wrap" style="background-color: #ede9fe; color: #7c3aed;">
                    <i class="uil uil-money-bill"></i>
                </div>
            </div>
        </div>

        <!-- 3. Total Collected -->
        <div class="col-sm-6 col-lg-3">
            <div class="kpi-card">
                <div>
                    <div class="kpi-title">Total Collected</div>
                    <div class="kpi-value text-success">₹{{ number_format($totalCollected, 2) }}</div>
                    <div class="text-muted small" style="font-size: 10px;">Cash, UPI & card settled</div>
                </div>
                <div class="kpi-icon-wrap" style="background-color: #dcfce7; color: #16a34a;">
                    <i class="uil uil-check-circle"></i>
                </div>
            </div>
        </div>

        <!-- 4. Total Balance Due -->
        <div class="col-sm-6 col-lg-3">
            <div class="kpi-card">
                <div>
                    <div class="kpi-title">Balance Due</div>
                    <div class="kpi-value {{ $totalDue > 0 ? 'text-danger' : 'text-success' }}">₹{{ number_format($totalDue, 2) }}</div>
                    <div class="text-muted small" style="font-size: 10px;">Pending collection balance</div>
                </div>
                <div class="kpi-icon-wrap" style="background-color: #fee2e2; color: #dc2626;">
                    <i class="uil uil-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- 3. FILTER TABS & DATE RANGE CONTROLS                           --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="summary-filter-card">
        <form method="GET" action="{{ route('lab.billing.daily-summary') }}" id="filter-form">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                {{-- Payment Status Filter Tabs --}}
                <div class="d-flex align-items-center flex-wrap gap-1">
                    <a href="{{ route('lab.billing.daily-summary', ['from_date' => $fromDate, 'to_date' => $toDate, 'q' => $search, 'status' => 'all']) }}"
                       class="filter-tab-pill {{ $status === 'all' ? 'active' : '' }}">
                        <i class="uil uil-apps"></i>
                        <span>All Bills</span>
                        <span class="pill-badge">{{ $countAll }}</span>
                    </a>
                    <a href="{{ route('lab.billing.daily-summary', ['from_date' => $fromDate, 'to_date' => $toDate, 'q' => $search, 'status' => 'paid']) }}"
                       class="filter-tab-pill {{ $status === 'paid' ? 'active' : '' }}">
                        <i class="uil uil-check-circle text-success"></i>
                        <span>Fully Paid</span>
                        <span class="pill-badge">{{ $countPaid }}</span>
                    </a>
                    <a href="{{ route('lab.billing.daily-summary', ['from_date' => $fromDate, 'to_date' => $toDate, 'q' => $search, 'status' => 'due']) }}"
                       class="filter-tab-pill {{ $status === 'due' ? 'active' : '' }}">
                        <i class="uil uil-clock text-danger"></i>
                        <span>Due / Partial</span>
                        <span class="pill-badge">{{ $countDue }}</span>
                    </a>
                </div>

                {{-- Date Presets & Date Pickers --}}
                <div class="d-flex align-items-center flex-wrap gap-1">
                    <span class="text-muted small me-1" style="font-size: 11px;">Quick Dates:</span>
                    <button type="button" class="btn-quick-date" onclick="setDates('today')">Today</button>
                    <button type="button" class="btn-quick-date" onclick="setDates('yesterday')">Yesterday</button>
                    <button type="button" class="btn-quick-date" onclick="setDates('month')">This Month</button>
                    <button type="button" class="btn-quick-date" onclick="setDates('all')">All Time</button>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2 pt-2 border-top">
                <input type="hidden" name="status" value="{{ $status }}">

                {{-- Date Range Controls --}}
                <div class="d-flex align-items-center gap-1 flex-wrap">
                    <span class="text-muted fw-semibold small" style="font-size: 11px;">From:</span>
                    <input type="date" name="from_date" id="input-from-date" value="{{ $fromDate }}" class="form-control form-control-sm" style="height: 30px; font-size: 11px; width: 135px;">

                    <span class="text-muted fw-semibold small ms-1" style="font-size: 11px;">To:</span>
                    <input type="date" name="to_date" id="input-to-date" value="{{ $toDate }}" class="form-control form-control-sm" style="height: 30px; font-size: 11px; width: 135px;">
                </div>

                {{-- Search Box & Filter Buttons --}}
                <div class="d-flex align-items-center gap-1 flex-wrap">
                    <div class="input-group input-group-sm" style="width: 290px;">
                        <span class="input-group-text bg-light border-end-0" style="height: 30px; font-size: 11px;">
                            <i class="uil uil-search text-muted"></i>
                        </span>
                        <input type="text" name="q" id="search-input" value="{{ $search }}" class="form-control border-start-0" placeholder="Patient name, mobile, bill no, UHID... (/)" style="height: 30px; font-size: 11.5px;">
                    </div>

                    <button type="submit" class="btn btn-sm btn-primary fw-semibold" style="height: 30px; font-size: 11px; padding: 0 10px;">
                        <i class="uil uil-filter me-1"></i>Filter
                    </button>

                    @if($search || $status !== 'all' || $fromDate !== Carbon\Carbon::now()->startOfMonth()->toDateString())
                        <a href="{{ route('lab.billing.daily-summary') }}" class="btn btn-sm btn-outline-danger" style="height: 30px; font-size: 11px; padding: 0 8px;" title="Reset filters">
                            <i class="uil uil-times me-1"></i>Clear
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- 4. BILLS REPORT TABLE                                          --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="bills-table-card">
        <div class="table-container">
            <table class="table-bills" id="bills-report-table">
                <thead>
                    <tr>
                        <th style="width: 75px; text-align: center;">Bill ID</th>
                        <th style="width: 125px;">Date & Time</th>
                        <th>Patient Details</th>
                        <th style="width: 140px;">Doctor / Referral</th>
                        <th>Investigations Billed</th>
                        <th style="width: 90px; text-align: right;">Billed (₹)</th>
                        <th style="width: 100px; text-align: right;">Paid (₹)</th>
                        <th style="width: 90px; text-align: right;">Due (₹)</th>
                        <th style="width: 85px; text-align: center;">Status</th>
                        <th style="width: 155px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bills as $b)
                    @php
                        $items = $pageItems->get($b->bill_id, collect());
                        $payments = $pagePayments->get($b->bill_id, collect());

                        // Calculate Billed Net
                        $billedAmount = $items->sum(fn($it) => ((float)$it->charges * (float)$it->qty) - (float)$it->discount);
                        $billedAmount = max(0, $billedAmount - (float)($b->discount ?? 0));
                        $paidAmount = (float)$payments->sum('amount');
                        $dueAmount = max(0, round($billedAmount - $paidAmount, 2));

                        // Payment Status
                        $paymentStatus = 'Paid';
                        if ($billedAmount > 0) {
                            if ($paidAmount >= $billedAmount) {
                                $paymentStatus = 'Paid';
                            } elseif ($paidAmount > 0) {
                                $paymentStatus = 'Partial';
                            } else {
                                $paymentStatus = 'Due';
                            }
                        }

                        $paymentModes = $payments->pluck('payment_mode')->filter()->unique()->map(fn($m) => strtoupper($m))->implode(', ');
                        $servicesList = $items->pluck('service_name')->filter()->unique()->values();

                        $patientName = trim(($b->Fname ?? '') . ' ' . ($b->Lname ?? ''));
                        if ($patientName === '') {
                            $patientName = 'Patient #' . ($b->uhid ?? $b->patient_id);
                        }

                        $ageGender = '';
                        if ($b->age) {
                            $sexShort = in_array(strtoupper($b->sex ?? ''), ['M', 'MALE']) ? 'M' : 'F';
                            $ageGender = "{$b->age}Y / {$sexShort}";
                        }

                        $targetOrderKey = $b->order_id ?: $b->bill_id;
                        $receiptUrl = route('lab.billing.receipt', ['order' => $targetOrderKey]);
                        $barcodeUrl = $b->order_id ? route('lab.billing.barcode', $b->order_id) : null;
                        $doctorName = $b->ref_by ?: ($b->referring_doctor ?: 'Dr Gurchand');
                    @endphp
                    <tr id="row-bill-{{ $b->bill_id }}" data-id="{{ $b->bill_id }}">
                        <!-- 1. Bill ID & Number -->
                        <td style="text-align: center;">
                            <a href="javascript:void(0)" onclick="printDirectPreview('{{ $receiptUrl }}')" class="bill-link" title="Click to print bill receipt">
                                {{ $b->bill_number ? 'Bill #' . $b->bill_number : '#' . $b->bill_id }}
                            </a>
                            <div class="text-muted" style="font-size: 9.5px;">ID: {{ $b->bill_id }}</div>
                        </td>

                        <!-- 2. Date & Time -->
                        <td style="white-space: nowrap;">
                            <div class="fw-semibold text-dark" style="font-size: 11px;">
                                {{ $b->created_at ? Carbon\Carbon::parse($b->created_at)->format('d-m-Y') : '-' }}
                            </div>
                            <div class="text-muted" style="font-size: 10px;">
                                {{ $b->created_at ? Carbon\Carbon::parse($b->created_at)->format('h:i A') : '' }}
                            </div>
                        </td>

                        <!-- 3. Patient Details -->
                        <td>
                            <div class="fw-bold text-dark" style="font-size: 12px;">{{ $patientName }}</div>
                            <div class="d-flex align-items-center gap-1 flex-wrap mt-0.5">
                                <span class="badge bg-light text-dark border font-monospace" style="font-size: 9.5px;">UHID: {{ $b->uhid ?: ($b->patient_id ?: '-') }}</span>
                                @if($ageGender)
                                    <span class="badge bg-light text-secondary border" style="font-size: 9.5px;">{{ $ageGender }}</span>
                                @endif
                                @if($b->mobile_no)
                                    <span class="text-muted" style="font-size: 10.5px;"><i class="uil uil-phone me-0.5"></i>{{ $b->mobile_no }}</span>
                                @endif
                            </div>
                        </td>

                        <!-- 4. Doctor / Referral -->
                        <td style="white-space: nowrap;">
                            <div class="fw-semibold text-secondary" style="font-size: 11px;">
                                <i class="uil uil-stethoscope me-1 text-muted"></i>{{ $doctorName }}
                            </div>
                        </td>

                        <!-- 5. Investigations Billed -->
                        <td>
                            @forelse($servicesList as $srv)
                                <span class="service-badge" title="{{ $srv }}">{{ $srv }}</span>
                            @empty
                                <span class="text-muted small">No items</span>
                            @endforelse
                        </td>

                        <!-- 6. Total Billed (₹) -->
                        <td style="text-align: right; font-weight: 700; color: #0f172a;">
                            ₹{{ number_format($billedAmount, 2) }}
                        </td>

                        <!-- 7. Paid (₹) -->
                        <td style="text-align: right;">
                            <div class="fw-bold text-success">₹{{ number_format($paidAmount, 2) }}</div>
                            @if($paymentModes)
                                <div class="text-muted font-monospace" style="font-size: 9px;">{{ $paymentModes }}</div>
                            @endif
                        </td>

                        <!-- 8. Due (₹) -->
                        <td style="text-align: right;">
                            @if($dueAmount > 0)
                                <span class="fw-bold text-danger">₹{{ number_format($dueAmount, 2) }}</span>
                            @else
                                <span class="text-success fw-semibold" style="font-size: 11px;">₹0.00</span>
                            @endif
                        </td>

                        <!-- 9. Status -->
                        <td style="text-align: center;">
                            @if($paymentStatus === 'Paid')
                                <span class="status-badge-paid"><i class="uil uil-check-circle me-1"></i>Paid</span>
                            @elseif($paymentStatus === 'Partial')
                                <span class="status-badge-partial"><i class="uil uil-clock me-1"></i>Partial</span>
                            @else
                                <span class="status-badge-due"><i class="uil uil-exclamation-circle me-1"></i>Due</span>
                            @endif
                        </td>

                        <!-- 10. Actions (Edit Bill, Receipt, Barcode) -->
                        <td style="text-align: center;">
                            <div class="d-inline-flex align-items-center gap-1">
                                <button type="button" class="btn btn-action-edit" onclick="openEditBillModal({{ $b->bill_id }})" title="Edit bill details, referral & settle due balance">
                                    <i class="uil uil-edit me-1"></i>Edit
                                </button>
                                <button type="button" class="btn btn-action-print" onclick="printDirectPreview('{{ $receiptUrl }}')" title="Print Bill Receipt">
                                    <i class="uil uil-print"></i>
                                </button>
                                @if($barcodeUrl)
                                    <button type="button" class="btn btn-mini-barcode" onclick="printDirectPreview('{{ $barcodeUrl }}')" title="Print Barcode">
                                        <i class="uil uil-barcode"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="text-center py-5 text-muted">
                            <i class="uil uil-bill fs-1 text-secondary opacity-50 d-block mb-2"></i>
                            <div class="fw-semibold">No billing records found for the selected dates and criteria.</div>
                            <div class="small">Try selecting "All Time" or adjusting your search keywords.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- 5. FOOTER & PAGINATION                                         --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-2 px-3 border-top bg-light">
            <div class="text-muted" style="font-size: 11.5px;">
                <span class="badge bg-white text-dark border"><kbd>/</kbd></span> Quick Search &nbsp;•&nbsp; 
                Showing <strong>{{ $bills->firstItem() ?? 0 }}</strong> to <strong>{{ $bills->lastItem() ?? 0 }}</strong> of <strong>{{ $bills->total() }}</strong> bills
            </div>
            <div>
                {{ $bills->withQueryString()->links() }}
            </div>
        </div>
    </div>

</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- 6. EDIT BILL & SETTLEMENT MODAL                                --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="editBillModal" tabindex="-1" aria-labelledby="editBillModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border: 2px solid #252f4a; border-radius: 8px;">
            <!-- Modal Header -->
            <div class="modal-header py-2 px-3" style="background-color: #252f4a; color: #ffffff;">
                <div class="d-flex align-items-center gap-2">
                    <i class="uil uil-edit text-info fs-4"></i>
                    <div>
                        <h6 class="modal-title mb-0 fw-bold text-white d-flex align-items-center gap-2">
                            <span>Edit Bill & Ledger Management</span>
                            <span class="badge bg-primary text-white" id="modalBillBadge">Bill #0</span>
                            <span id="modalStatusBadge"></span>
                        </h6>
                        <div class="text-white-50 small" style="font-size: 11.5px;">
                            Patient: <strong class="text-white" id="modalHeaderPatientName">-</strong> &nbsp;•&nbsp; 
                            UHID: <strong class="text-white" id="modalHeaderUhid">-</strong> &nbsp;•&nbsp; 
                            Date: <strong class="text-white" id="modalHeaderBillDate">-</strong>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-3 bg-light">
                <div id="modalLoadingSpinner" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading bill details...</span>
                    </div>
                    <div class="mt-2 text-muted fw-semibold">Loading Bill Details...</div>
                </div>

                <div id="modalContentBody" style="display: none;">
                    <!-- OPD Patient Information Bar (Same Component as /lab/billing) -->
                    <div class="patient-details-below mt-0 mb-3" id="patient_info_bar" style="background: #ffffff; border: 1px solid var(--brand-border); border-radius: 6px; padding: 7px 14px;">
                        <div class="detail-cell">
                            <span class="detail-label">UHID:</span>
                            <span class="detail-val text-teal fw-bold" id="modalUhid">-</span>
                        </div>
                        <div class="detail-cell">
                            <span class="detail-label">Patient Name:</span>
                            <span class="detail-val fw-bold text-dark" id="modalPatientName">-</span>
                        </div>
                        <div class="detail-cell">
                            <span class="detail-label">Age / Sex:</span>
                            <span class="detail-val" id="modalAgeSex">-</span>
                        </div>
                        <div class="detail-cell">
                            <span class="detail-label">Mobile:</span>
                            <span class="detail-val" id="modalMobileNo">-</span>
                        </div>
                        <div class="detail-cell">
                            <span class="detail-label">Sample No:</span>
                            <span class="detail-val badge bg-light text-dark border" id="modalSampleNo">-</span>
                        </div>
                    </div>

                    <!-- Main Billing Split Grid (Exact Billing Workspace Layout) -->
                    <div class="billing-split-grid">
                        <!-- Left: Investigation Table & Catalog (Exact Billing Component) -->
                        <div class="pro-card investigations-card">
                            <!-- Table of Billed Services -->
                            <div class="investigations-table-container">
                                <table class="investigations-table" id="cart-table">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width: 44px;">#</th>
                                            <th class="text-center" style="width: 65px;">ID</th>
                                            <th>Investigation / Service Name</th>
                                            <th class="text-end" style="width: 85px;">Rate (₹)</th>
                                            <th class="text-center" style="width: 65px;">Qty</th>
                                            <th class="text-end" style="width: 85px;">Disc (₹)</th>
                                            <th class="text-end" style="width: 95px;">Amount (₹)</th>
                                            <th class="text-center" style="width: 44px;">Del</th>
                                        </tr>
                                    </thead>
                                    <tbody id="cart-tbody">
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted" style="font-style: italic;">
                                                No investigations selected. Type below to search and add tests.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Full-Width Investigation Search Bar (Same as /lab/billing) -->
                            <div class="service-search-box w-100">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span style="font-size: 12px; font-weight: 700; color: var(--brand-slate);">
                                        <i class="uil uil-search text-teal me-1"></i> Add Investigation / Procedure:
                                    </span>
                                    <span style="font-size: 11px; color: var(--brand-text-muted);">
                                        Use &uarr; &darr; keys, <kbd style="font-size: 9px; padding: 1px 4px; background: #e2e8f0; color: #475569; border-radius: 3px;">Enter</kbd> to add
                                    </span>
                                </div>
                                <div class="input-group mb-2 flex-nowrap">
                                    <span class="input-group-text">
                                        <i class="uil uil-search font-size-16"></i>
                                    </span>
                                    <input type="text"
                                           id="edit_search_service"
                                           class="form-control"
                                           placeholder="Type investigation name or code (e.g. LFT, ESR, Blood Sugar, CBC, USG)..."
                                           autocomplete="off">
                                </div>
                                <ul id="edit_search_results" class="investigation-list-results w-100"></ul>
                            </div>

                            <!-- Cancelled / Soft-Deleted Services section -->
                            <div class="card border p-2 bg-white shadow-sm mt-3" id="cardCancelledSection" style="display: none;">
                                <h6 class="fw-bold text-danger border-bottom pb-1 mb-1 d-flex justify-content-between align-items-center" style="font-size: 11.5px;">
                                    <span><i class="uil uil-trash-alt me-1"></i>Cancelled / Soft-Deleted Tests</span>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle" id="modalCancelledCount">0</span>
                                </h6>
                                <div style="max-height: 100px; overflow-y: auto;">
                                    <table class="table table-sm table-borderless mb-0 text-muted" style="font-size: 10.5px;">
                                        <tbody id="modalCancelledTbody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Payment & Settlement Panel (Exact Billing Component) -->
                        <div class="payment-panel">
                            <div class="payment-panel-header">
                                <h6 class="payment-panel-title mb-0">Payment Settlement</h6>
                            </div>

                            <div class="payment-form-grid">
                                <!-- Sub Total -->
                                <div class="payment-row">
                                    <label>Sub Total</label>
                                    <input type="text" class="payment-input-readonly" id="lbl-subtotal" value="₹0.00" readonly>
                                </div>

                                <!-- Doctor / Ref By -->
                                <div class="payment-row">
                                    <label for="editReferralDoctor">Ref By</label>
                                    <select id="editReferralDoctor" class="form-select payment-input-editable" style="font-size: 12px; height: 32px;">
                                        @foreach($referrals as $ref)
                                            <option value="{{ $ref->name }}">{{ $ref->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Final Discount (%) -->
                                <div class="payment-row">
                                    <label for="edit_discount_percent">Discount (%)</label>
                                    <div class="input-group" style="max-width: 120px;">
                                        <input type="number"
                                               step="0.5"
                                               min="0"
                                               max="100"
                                               class="form-control payment-input-editable text-end"
                                               id="edit_discount_percent"
                                               placeholder="0"
                                               value=""
                                               style="height: 32px; font-size: 13px;">
                                        <span class="input-group-text bg-light text-muted px-2" style="font-size: 12px; height: 32px; border-color: var(--brand-border);">%</span>
                                    </div>
                                </div>

                                <!-- Discount (₹) -->
                                <div class="payment-row">
                                    <label for="editBillDiscount">Discount (₹)</label>
                                    <input type="number" step="0.01" min="0" class="form-control payment-input-editable text-end text-danger fw-bold" id="editBillDiscount" value="0.00" style="height: 32px; font-size: 13px;">
                                </div>

                                <!-- Net Payable Amount -->
                                <div class="payment-row">
                                    <label style="font-weight: 700; color: var(--brand-teal-dark);">Net Payable</label>
                                    <input type="text" class="payment-input-readonly net-payable" id="lbl-total" value="₹0.00" readonly>
                                </div>

                                <hr class="my-1" style="border-color: #edf2f7;">

                                <!-- Payment Tenders -->
                                <!-- Cash -->
                                <div class="payment-row">
                                    <label for="edit_payment_cash"><i class="uil uil-money-bill text-success me-1"></i> Cash</label>
                                    <input type="number" step="0.01" min="0" class="payment-input-editable text-end edit-payment-field" id="edit_payment_cash" data-mode="cash" placeholder="0.00" value="">
                                </div>

                                <!-- UPI / QR -->
                                <div class="payment-row multi-col">
                                    <label for="edit_payment_upi"><i class="uil uil-qrcode-scan text-teal me-1"></i> UPI / QR</label>
                                    <input type="number" step="0.01" min="0" class="payment-input-editable text-end edit-payment-field" id="edit_payment_upi" data-mode="upi" placeholder="0.00" value="">
                                    <input type="text" class="payment-input-editable" id="edit_tx_upi" placeholder="UTR / TRX ID">
                                </div>

                                <!-- Card / POS -->
                                <div class="payment-row multi-col">
                                    <label for="edit_payment_card"><i class="uil uil-credit-card text-primary me-1"></i> Card / POS</label>
                                    <input type="number" step="0.01" min="0" class="payment-input-editable text-end edit-payment-field" id="edit_payment_card" data-mode="card" placeholder="0.00" value="">
                                    <input type="text" class="payment-input-editable" id="edit_tx_card" placeholder="Slip Ref #">
                                </div>

                                <!-- Settlement Status Summary (Stacked 2 Lines) -->
                                <div class="settlement-summary-stacked">
                                    <div class="settlement-line">
                                        <span class="label">Total Tendered:</span>
                                        <span class="amount text-paid" id="lbl-total-paid">₹0.00</span>
                                    </div>
                                    <div class="settlement-line">
                                        <span class="label">Balance Due:</span>
                                        <span class="amount text-due" id="lbl-due-amount">₹0.00</span>
                                    </div>
                                </div>

                                <!-- Prior Recorded Payments Section -->
                                <div class="mt-2" id="existingPaymentsContainer" style="display: none;">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-muted fw-bold" style="font-size: 10px; text-transform: uppercase;">Prior Recorded Payments</span>
                                        <span class="badge bg-light text-secondary border" id="modalTotalPaidBadge">Paid: ₹0.00</span>
                                    </div>
                                    <div style="max-height: 90px; overflow-y: auto;" class="border rounded bg-white p-1">
                                        <table class="table table-sm table-borderless mb-0" style="font-size: 10.5px;">
                                            <tbody id="modalPaymentsTbody"></tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Action Buttons Footer inside Panel -->
                                <div class="payment-actions-footer mt-2">
                                    <button type="button" class="btn-generate-bill w-100 justify-content-center" id="btnSaveFullBillPanel" title="Save Bill Changes">
                                        <i class="uil uil-check-circle me-1"></i> Save Bill Changes
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer py-2 px-3 d-flex justify-content-between align-items-center" style="background-color: #f1f5f9; border-top: 1px solid #cbd5e1;">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-dark btn-sm" id="btnModalPrintReceipt">
                        <i class="uil uil-print me-1"></i> Print Receipt
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnModalPrintBarcode">
                        <i class="uil uil-barcode me-1"></i> Barcode
                    </button>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success btn-sm px-3 fw-bold shadow-sm" id="btnSaveFullBill">
                        <i class="uil uil-check-circle me-1"></i> Save Bill Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function($) {
    'use strict';

    // 1. Direct Browser Print Preview Helper
    window.printDirectPreview = function(url) {
        if (!url) return;
        let iframe = document.getElementById('billingPrintFrame');
        if (!iframe) {
            iframe = document.createElement('iframe');
            iframe.id = 'billingPrintFrame';
            iframe.style.position = 'fixed';
            iframe.style.right = '0';
            iframe.style.bottom = '0';
            iframe.style.width = '0';
            iframe.style.height = '0';
            iframe.style.border = '0';
            document.body.appendChild(iframe);
        }
        iframe.src = url;
        iframe.onload = function() {
            setTimeout(function() {
                try {
                    iframe.contentWindow.focus();
                    iframe.contentWindow.print();
                } catch(e) {
                    window.open(url, '_blank');
                }
            }, 500);
        };
    };

    // 2. Quick Date Presets
    window.setDates = function(type) {
        const today = new Date();
        const formatDate = (d) => {
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        let fromStr = '';
        let toStr = formatDate(today);

        if (type === 'today') {
            fromStr = toStr;
        } else if (type === 'yesterday') {
            const y = new Date();
            y.setDate(y.getDate() - 1);
            fromStr = formatDate(y);
            toStr = fromStr;
        } else if (type === 'month') {
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            fromStr = formatDate(firstDay);
        } else if (type === 'all') {
            fromStr = '2025-01-01';
        }

        $('#input-from-date').val(fromStr);
        $('#input-to-date').val(toStr);
        $('#filter-form').submit();
    };

    // 3. Edit Bill Modal State Management
    let activeModalBillId = null;
    let activeModalReceiptUrl = '';
    let activeModalBarcodeUrl = '';
    let servicesDraft = [];
    let cancelledDraft = [];
    let paymentsDraft = [];

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    const billDetailsUrlTemplate = "{{ route('lab.billing.details', ['bill' => '__BILL_ID__']) }}";
    const billSaveFullUrlTemplate = "{{ route('lab.billing.save-full', ['bill' => '__BILL_ID__']) }}";

    window.openEditBillModal = function(billId) {
        activeModalBillId = billId;
        const modal = new bootstrap.Modal(document.getElementById('editBillModal'));
        modal.show();

        $('#modalLoadingSpinner').show();
        $('#modalContentBody').hide();
        $('#serviceSearchDropdown').hide();

        const detailsUrl = billDetailsUrlTemplate.replace('__BILL_ID__', billId);
        $.get(detailsUrl, function(res) {
            if (!res.success) {
                alert('Could not load bill details.');
                return;
            }

            activeModalReceiptUrl = res.bill.receipt_url;
            activeModalBarcodeUrl = res.bill.barcode_url;

            $('#modalBillBadge').text(res.bill.bill_number ? 'Bill #' + res.bill.bill_number : '#' + res.bill.id);
            $('#modalHeaderPatientName').text(res.patient.name);
            $('#modalHeaderUhid').text(res.patient.uhid);
            $('#modalHeaderBillDate').text(res.bill.created_at);

            $('#modalUhid').text(res.patient.uhid);
            $('#modalPatientName').text(res.patient.name);
            $('#modalAgeSex').text(res.patient.age);
            $('#modalMobileNo').text(res.patient.mobile_no);
            $('#modalSampleNo').text(res.bill.sample_no || '-');

            // Reset search & tender inputs
            $('#edit_search_service').val('');
            $('#edit_search_results').empty().hide();
            $('#edit_payment_cash').val('');
            $('#edit_payment_upi').val('');
            $('#edit_tx_upi').val('');
            $('#edit_payment_card').val('');
            $('#edit_tx_card').val('');

            // Populate Referrals
            if (res.referral_doctor) {
                if ($('#editReferralDoctor option').filter(function() { return $(this).val() === res.referral_doctor; }).length === 0) {
                    $('#editReferralDoctor').append(new Option(res.referral_doctor, res.referral_doctor));
                }
                $('#editReferralDoctor').val(res.referral_doctor);
            }

            // Sync discount & calculate %
            const gross = parseFloat(res.financials.gross_amount || 0);
            const disc = parseFloat(res.financials.bill_discount || 0);
            $('#editBillDiscount').val(disc.toFixed(2));
            const discPer = gross > 0 ? (disc / gross) * 100 : 0;
            $('#edit_discount_percent').val(discPer > 0 ? discPer.toFixed(1) : '');

            // Load Items into draft array
            servicesDraft = (res.items || []).map(it => ({
                id: it.id,
                service_id: it.service_id,
                service_name: it.service_name,
                short_code: it.short_code,
                charges: parseFloat(it.charges || 0),
                qty: parseFloat(it.qty || 1),
                discount: parseFloat(it.discount || 0),
                is_removed: false
            }));

            // Cancelled items
            cancelledDraft = res.cancelled_items || [];

            // Load Payments into draft array
            paymentsDraft = (res.payments || []).map(p => ({
                id: p.id,
                amount: parseFloat(p.amount || 0),
                payment_mode: (p.payment_mode || 'cash').toLowerCase(),
                transaction_id: p.transaction_id || '',
                date: p.date,
                is_deleted: false
            }));

            // Render all sections
            renderServicesTable();
            renderCancelledTable();
            renderPaymentsTable();
            calculateFinancials();

            $('#modalLoadingSpinner').hide();
            $('#modalContentBody').show();
        }).fail(function() {
            alert('Error communicating with server.');
            $('#modalLoadingSpinner').hide();
        });
    };

    // Render Active Services Table (Exact Billing investigations-table layout with live editable test names)
    function renderServicesTable() {
        const activeItems = servicesDraft.filter(it => !it.is_removed);

        let html = '';
        if (activeItems.length === 0) {
            html = `<tr><td colspan="8" class="text-center py-4 text-muted" style="font-style: italic;">No investigations selected. Type below to search and add tests.</td></tr>`;
        } else {
            servicesDraft.forEach((it, idx) => {
                if (it.is_removed) return;
                const net = Math.max(0, (it.charges * it.qty) - it.discount);
                html += `
                    <tr data-index="${idx}" data-service-id="${it.service_id || ''}">
                        <td class="text-center serial-no" style="vertical-align: middle;">${idx + 1}</td>
                        <td class="text-center text-muted" style="vertical-align: middle; font-size: 11px;">${it.service_id || '-'}</td>
                        <td class="position-relative" style="vertical-align: middle;">
                            <div class="d-flex align-items-center gap-1">
                                <input type="text"
                                       class="cell-input service-name-input fw-medium"
                                       data-idx="${idx}"
                                       value="${escapeHtml(it.service_name)}"
                                       placeholder="Test name..."
                                       autocomplete="off"
                                       style="font-size: 12px; height: 30px; border: 1px solid var(--brand-border); border-radius: 4px; padding: 2px 6px;">
                                ${it.short_code ? `<span class="badge bg-light text-secondary border font-monospace flex-shrink-0" style="font-size: 9px;">${escapeHtml(it.short_code)}</span>` : ''}
                                ${!it.id ? `<span class="badge bg-success-subtle text-success border border-success-subtle flex-shrink-0" style="font-size: 8.5px;">NEW</span>` : ''}
                            </div>
                            <div class="row-service-dropdown shadow" style="display: none; position: absolute; top: 100%; left: 0; min-width: 280px; max-width: 360px; z-index: 1060; background: #fff; border: 1px solid #cbd5e1; border-radius: 4px; max-height: 180px; overflow-y: auto;"></div>
                        </td>
                        <td style="vertical-align: middle;">
                            <input type="number" step="0.01" min="0" class="cell-input text-end item-charge" data-idx="${idx}" value="${it.charges.toFixed(2)}" title="Edit rate" style="font-size: 12px; height: 30px; border: 1px solid var(--brand-border); border-radius: 4px;">
                        </td>
                        <td style="vertical-align: middle;">
                            <input type="number" step="1" min="1" class="cell-input text-center item-qty" data-idx="${idx}" value="${it.qty}" title="Edit quantity" style="font-size: 12px; height: 30px; border: 1px solid var(--brand-border); border-radius: 4px;">
                        </td>
                        <td style="vertical-align: middle;">
                            <input type="number" step="0.01" min="0" class="cell-input text-end item-discount" data-idx="${idx}" value="${it.discount.toFixed(2)}" title="Edit discount" style="font-size: 12px; height: 30px; border: 1px solid var(--brand-border); border-radius: 4px;">
                        </td>
                        <td style="vertical-align: middle;">
                            <input type="number" step="0.01" class="cell-input readonly-bg text-end item-amount fw-bold text-dark" value="${net.toFixed(2)}" readonly style="font-size: 12px; height: 30px; background-color: #f8fafc; border: 1px solid var(--brand-border); border-radius: 4px;">
                        </td>
                        <td class="text-center" style="vertical-align: middle;">
                            <button type="button" class="btn-trash-outline btn-remove-item" data-idx="${idx}" title="Remove test">
                                <i class="uil uil-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        }
        $('#cart-tbody').html(html);
    }

    // Render Cancelled / Soft-Deleted Services
    function renderCancelledTable() {
        if (!cancelledDraft || cancelledDraft.length === 0) {
            $('#cardCancelledSection').hide();
            return;
        }
        $('#cardCancelledSection').show();
        $('#modalCancelledCount').text(cancelledDraft.length);

        let html = '';
        cancelledDraft.forEach(it => {
            html += `
                <tr>
                    <td style="text-decoration: line-through;">${escapeHtml(it.service_name)}</td>
                    <td class="text-end">₹${parseFloat(it.charges).toFixed(2)}</td>
                    <td class="text-end text-danger" style="font-size: 9.5px;">
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Cancelled</span>
                        ${it.cancelled_at || ''}
                    </td>
                </tr>
            `;
        });
        $('#modalCancelledTbody').html(html);
    }

    // Render Prior Payments Table
    function renderPaymentsTable() {
        const activePayments = paymentsDraft.filter(p => !p.is_deleted);
        if (activePayments.length === 0) {
            $('#existingPaymentsContainer').hide();
            $('#modalPaymentsTbody').empty();
            return;
        }

        $('#existingPaymentsContainer').show();
        let html = '';
        paymentsDraft.forEach((p, idx) => {
            if (p.is_deleted) return;
            html += `
                <tr data-p-idx="${idx}">
                    <td class="align-middle fw-bold text-uppercase text-dark" style="width: 70px;">${escapeHtml(p.payment_mode)}</td>
                    <td class="align-middle text-end fw-bold text-success" style="width: 75px;">₹${p.amount.toFixed(2)}</td>
                    <td class="align-middle text-muted" style="font-size: 9.5px;">${escapeHtml(p.transaction_id || p.date || '')}</td>
                    <td class="align-middle text-center" style="width: 30px;">
                        <button type="button" class="btn btn-link text-danger p-0 btn-void-payment" data-idx="${idx}" title="Void this payment">
                            <i class="uil uil-times-circle"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        $('#modalPaymentsTbody').html(html);
    }

    // Dynamic Financial Calculations (Exact Billing Settlement Engine)
    function calculateFinancials() {
        let gross = 0.0;
        servicesDraft.forEach(it => {
            if (!it.is_removed) {
                gross += Math.max(0, (it.charges * it.qty) - it.discount);
            }
        });

        const billDiscount = parseFloat($('#editBillDiscount').val()) || 0.0;
        const net = Math.max(0, gross - billDiscount);

        let priorPaid = 0.0;
        paymentsDraft.forEach(p => {
            if (!p.is_deleted) {
                priorPaid += p.amount;
            }
        });

        const newCash = parseFloat($('#edit_payment_cash').val()) || 0.0;
        const newUpi = parseFloat($('#edit_payment_upi').val()) || 0.0;
        const newCard = parseFloat($('#edit_payment_card').val()) || 0.0;
        const newTenders = newCash + newUpi + newCard;

        const totalTendered = priorPaid + newTenders;
        const due = Math.max(0, Math.round((net - totalTendered) * 100) / 100);

        $('#lbl-subtotal').val('₹' + gross.toFixed(2));
        $('#lbl-total').val('₹' + net.toFixed(2));
        $('#lbl-total-paid').text('₹' + totalTendered.toFixed(2));
        $('#modalTotalPaidBadge').text('Paid: ₹' + priorPaid.toFixed(2));
        $('#lbl-due-amount').text('₹' + due.toFixed(2));

        if (due <= 0) {
            $('#lbl-due-amount').removeClass('text-due').css('color', 'var(--brand-teal)');
            $('#modalStatusBadge').html('<span class="badge bg-success ms-1"><i class="uil uil-check-circle me-1"></i>Fully Paid</span>');
        } else if (totalTendered > 0) {
            $('#lbl-due-amount').addClass('text-due').css('color', '');
            $('#modalStatusBadge').html('<span class="badge bg-warning text-dark ms-1"><i class="uil uil-clock me-1"></i>Partially Paid</span>');
        } else {
            $('#lbl-due-amount').addClass('text-due').css('color', '');
            $('#modalStatusBadge').html('<span class="badge bg-danger ms-1"><i class="uil uil-exclamation-circle me-1"></i>Due / Unpaid</span>');
        }
    }

    // 4. Investigation Catalog Live Search with Keyboard Navigation (Same as /lab/billing)
    let editSearchTimer = null;
    let editResultIndex = -1;

    $('#edit_search_service').on('input', function() {
        const query = $(this).val().trim();
        clearTimeout(editSearchTimer);
        editResultIndex = -1;

        if (query.length < 1) {
            $('#edit_search_results').empty().hide();
            return;
        }

        editSearchTimer = setTimeout(function() {
            $.get("{{ route('lab.api.services.search') }}", { q: query }, function(data) {
                const list = data.results || data || [];
                const ul = $('#edit_search_results').empty();
                if (list.length === 0) {
                    ul.append('<li style="color: #64748b; font-style: italic; padding: 10px;">No matching investigation found in catalog.</li>').show();
                    return;
                }

                list.forEach(function(item) {
                    const li = $('<li></li>');
                    li.data('service', item);

                    let displayTitle = item.name || item.service;
                    if (item.short_code || item.code) {
                        displayTitle += ` <span style="color: #64748b; font-size: 11px;">(${item.short_code || item.code})</span>`;
                    }

                    let content = `<span>${escapeHtml(displayTitle)}</span>`;
                    content += `<div class="d-flex align-items-center gap-2">`;
                    if (item.is_outsource) {
                        content += `<span class="badge-outsource">Outsource</span>`;
                    }
                    const price = parseFloat(item.price || item.charge || item.charges || 0);
                    content += `<span class="badge-rate">₹${price.toFixed(0)}</span>`;
                    content += `</div>`;

                    li.html(content);
                    ul.append(li);
                });
                ul.show();
            });
        }, 160);
    });

    $('#edit_search_service').on('keydown', function(e) {
        const items = $('#edit_search_results li:not(:first-child:contains("No matching"))');
        if (!items.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            editResultIndex = (editResultIndex + 1) % items.length;
            items.removeClass('active');
            const activeEl = items.eq(editResultIndex).addClass('active');
            const container = $('#edit_search_results');
            const scrollPos = activeEl.position().top + container.scrollTop();
            if (scrollPos < container.scrollTop() || scrollPos > container.scrollTop() + container.height()) {
                container.scrollTop(scrollPos - 40);
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            editResultIndex = (editResultIndex - 1 + items.length) % items.length;
            items.removeClass('active');
            const activeEl = items.eq(editResultIndex).addClass('active');
            const container = $('#edit_search_results');
            const scrollPos = activeEl.position().top + container.scrollTop();
            if (scrollPos < container.scrollTop() || scrollPos > container.scrollTop() + container.height()) {
                container.scrollTop(scrollPos - 40);
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (editResultIndex >= 0 && editResultIndex < items.length) {
                items.eq(editResultIndex).trigger('click');
            } else if (items.length > 0) {
                items.first().trigger('click');
            }
        }
    });

    // Add test to bill when clicked from results list
    $(document).on('click', '#edit_search_results li', function() {
        const s = $(this).data('service');
        if (s) {
            const charge = parseFloat(s.price || s.charge || s.charges || 0);
            servicesDraft.push({
                id: null,
                service_id: s.id,
                service_name: s.name || s.service,
                short_code: s.short_code || s.code || '',
                charges: charge,
                qty: 1,
                discount: 0,
                is_removed: false
            });

            $('#edit_search_service').val('').focus();
            $('#edit_search_results').empty().hide();

            renderServicesTable();
            calculateFinancials();
        }
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#edit_search_service, #edit_search_results').length) {
            $('#edit_search_results').hide();
        }
    });

    // 5. Autocomplete for editable Service Name in table rows
    let rowSearchTimer = null;
    $(document).on('input', '.service-name-input', function() {
        const input = $(this);
        const idx = input.data('idx');
        const query = input.val().trim();
        const dropdown = input.closest('td').find('.row-service-dropdown');

        servicesDraft[idx].service_name = input.val();

        clearTimeout(rowSearchTimer);
        if (query.length < 1) {
            dropdown.hide();
            return;
        }

        rowSearchTimer = setTimeout(function() {
            $.get("{{ route('lab.api.services.search') }}", { q: query }, function(data) {
                const list = data.results || data || [];
                if (list.length === 0) {
                    dropdown.html('<div class="p-2 text-muted" style="font-size:10.5px;">No tests found.</div>').show();
                    return;
                }

                let itemsHtml = '';
                list.forEach(srv => {
                    const price = parseFloat(srv.charge || srv.charges || srv.price || 0).toFixed(2);
                    itemsHtml += `
                        <div class="px-2 py-1.5 row-service-item" style="cursor: pointer; border-bottom: 1px solid #f1f5f9; font-size: 11px;" 
                             data-row-idx="${idx}" data-id="${srv.id}" data-name="${escapeHtml(srv.name || srv.service)}" data-charge="${price}" data-code="${escapeHtml(srv.short_code || srv.code || '')}">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong>${escapeHtml(srv.name || srv.service)}</strong>
                                <span class="badge bg-primary text-white">₹${price}</span>
                            </div>
                            <div class="text-muted" style="font-size: 9.5px;">Code: ${escapeHtml(srv.short_code || srv.code || '-')}</div>
                        </div>
                    `;
                });
                dropdown.html(itemsHtml).show();
            });
        }, 200);
    });

    // Select test from row autocomplete dropdown -> auto-load rate & recalculate
    $(document).on('click', '.row-service-item', function() {
        const rowIdx = $(this).data('row-idx');
        const srvId = $(this).data('id');
        const srvName = $(this).data('name');
        const srvCharge = parseFloat($(this).data('charge')) || 0;
        const srvCode = $(this).data('code') || '';

        // Update draft model
        servicesDraft[rowIdx].service_id = srvId;
        servicesDraft[rowIdx].service_name = srvName;
        servicesDraft[rowIdx].short_code = srvCode;
        servicesDraft[rowIdx].charges = srvCharge;

        // Update DOM row values directly
        const tr = $(`#cart-tbody tr[data-index="${rowIdx}"]`);
        tr.attr('data-service-id', srvId);
        tr.find('td:nth-child(2)').text(srvId);
        tr.find('.service-name-input').val(srvName);
        tr.find('.item-charge').val(srvCharge.toFixed(2));
        tr.find('.row-service-dropdown').hide();

        const net = Math.max(0, (srvCharge * servicesDraft[rowIdx].qty) - servicesDraft[rowIdx].discount);
        tr.find('.item-amount').val(net.toFixed(2));

        calculateFinancials();
    });

    // Close row dropdown on click outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.service-name-input, .row-service-dropdown').length) {
            $('.row-service-dropdown').hide();
        }
    });

    // 6. Inline Service Edits (Rate, Qty, Discount)
    $(document).on('input', '.item-charge', function() {
        const idx = $(this).data('idx');
        servicesDraft[idx].charges = parseFloat($(this).val()) || 0;
        const net = Math.max(0, (servicesDraft[idx].charges * servicesDraft[idx].qty) - servicesDraft[idx].discount);
        $(this).closest('tr').find('.item-amount').val(net.toFixed(2));
        calculateFinancials();
    });

    $(document).on('input', '.item-qty', function() {
        const idx = $(this).data('idx');
        servicesDraft[idx].qty = parseFloat($(this).val()) || 1;
        const net = Math.max(0, (servicesDraft[idx].charges * servicesDraft[idx].qty) - servicesDraft[idx].discount);
        $(this).closest('tr').find('.item-amount').val(net.toFixed(2));
        calculateFinancials();
    });

    $(document).on('input', '.item-discount', function() {
        const idx = $(this).data('idx');
        servicesDraft[idx].discount = parseFloat($(this).val()) || 0;
        const net = Math.max(0, (servicesDraft[idx].charges * servicesDraft[idx].qty) - servicesDraft[idx].discount);
        $(this).closest('tr').find('.item-amount').val(net.toFixed(2));
        calculateFinancials();
    });

    // 7. Remove Service (Marks as Cancelled and Soft-Deletes)
    $(document).on('click', '.btn-remove-item', function() {
        const idx = $(this).data('idx');
        const item = servicesDraft[idx];

        if (confirm(`Are you sure you want to remove and cancel "${item.service_name}"?`)) {
            if (item.id) {
                item.is_removed = true;
                cancelledDraft.unshift({
                    id: item.id,
                    service_name: item.service_name,
                    charges: item.charges,
                    cancelled_at: 'Just now'
                });
            } else {
                servicesDraft.splice(idx, 1);
            }

            renderServicesTable();
            renderCancelledTable();
            calculateFinancials();
        }
    });

    // 8. Void Prior Payment
    $(document).on('click', '.btn-void-payment', function() {
        const idx = $(this).data('idx');
        const p = paymentsDraft[idx];

        if (confirm(`Void payment of ₹${p.amount.toFixed(2)} (${p.payment_mode.toUpperCase()})?`)) {
            if (p.id) {
                p.is_deleted = true;
            } else {
                paymentsDraft.splice(idx, 1);
            }

            renderPaymentsTable();
            calculateFinancials();
        }
    });

    // 9. Discount Synchronizations
    $('#edit_discount_percent').on('input', function() {
        let gross = 0.0;
        servicesDraft.forEach(it => {
            if (!it.is_removed) gross += Math.max(0, (it.charges * it.qty) - it.discount);
        });
        const percent = Math.min(100, Math.max(0, parseFloat($(this).val()) || 0));
        const disc = (gross * percent) / 100;
        $('#editBillDiscount').val(disc.toFixed(2));
        calculateFinancials();
    });

    $('#editBillDiscount').on('input', function() {
        let gross = 0.0;
        servicesDraft.forEach(it => {
            if (!it.is_removed) gross += Math.max(0, (it.charges * it.qty) - it.discount);
        });
        const disc = Math.max(0, parseFloat($(this).val()) || 0);
        const percent = gross > 0 ? (disc / gross) * 100 : 0;
        $('#edit_discount_percent').val(percent > 0 ? percent.toFixed(1) : '');
        calculateFinancials();
    });

    $('.edit-payment-field').on('input', function() {
        calculateFinancials();
    });

    // 10. Save Full Bill Changes (Both Panel button & Footer button)
    function handleSaveFullBill(btn) {
        if (!activeModalBillId) return;

        btn.prop('disabled', true).html('<i class="uil uil-spinner uil-spin me-1"></i> Saving Bill...');

        // Append any new tenders entered
        const finalPayments = paymentsDraft.slice();
        const newCash = parseFloat($('#edit_payment_cash').val()) || 0;
        const newUpi = parseFloat($('#edit_payment_upi').val()) || 0;
        const newCard = parseFloat($('#edit_payment_card').val()) || 0;
        const txUpi = $('#edit_tx_upi').val().trim();
        const txCard = $('#edit_tx_card').val().trim();

        if (newCash > 0) {
            finalPayments.push({
                id: null,
                amount: newCash,
                payment_mode: 'cash',
                transaction_id: '',
                is_deleted: false
            });
        }
        if (newUpi > 0) {
            finalPayments.push({
                id: null,
                amount: newUpi,
                payment_mode: 'upi',
                transaction_id: txUpi,
                is_deleted: false
            });
        }
        if (newCard > 0) {
            finalPayments.push({
                id: null,
                amount: newCard,
                payment_mode: 'card',
                transaction_id: txCard,
                is_deleted: false
            });
        }

        const payload = {
            referral_doctor: $('#editReferralDoctor').val(),
            bill_discount: parseFloat($('#editBillDiscount').val()) || 0,
            services: servicesDraft,
            payments: finalPayments
        };

        const saveUrl = billSaveFullUrlTemplate.replace('__BILL_ID__', activeModalBillId);

        $.ajax({
            url: saveUrl,
            type: 'POST',
            data: JSON.stringify(payload),
            contentType: 'application/json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(res) {
                if (res.success) {
                    const modalEl = document.getElementById('editBillModal');
                    const modalInst = bootstrap.Modal.getInstance(modalEl);
                    if (modalInst) {
                        modalInst.hide();
                    }
                    if (window.lisToast) {
                        window.lisToast(res.message, 'success');
                    }
                    setTimeout(function() {
                        window.location.reload();
                    }, 400);
                }
            },
            error: function(xhr) {
                const msg = xhr.responseJSON?.message || 'Failed to save bill changes.';
                alert(msg);
                btn.prop('disabled', false).html('<i class="uil uil-check-circle me-1"></i> Save Bill Changes');
            }
        });
    }

    $('#btnSaveFullBill, #btnSaveFullBillPanel').on('click', function() {
        handleSaveFullBill($(this));
    });

    // 10. Modal Print Buttons
    $('#btnModalPrintReceipt').on('click', function() {
        if (activeModalReceiptUrl) printDirectPreview(activeModalReceiptUrl);
    });

    $('#btnModalPrintBarcode').on('click', function() {
        if (activeModalBarcodeUrl) printDirectPreview(activeModalBarcodeUrl);
    });

    // 11. Hotkey '/' to focus search input
    $(document).on('keydown', function(e) {
        if (e.key === '/' && !$(e.target).is('input, textarea, select')) {
            e.preventDefault();
            $('#search-input').focus().select();
        }
    });

})(jQuery);
</script>
@endpush
