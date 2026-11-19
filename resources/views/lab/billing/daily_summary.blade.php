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

                        $patientName = ($b->prefix ? $b->prefix . ' ' : '') . trim(($b->Fname ?? '') . ' ' . ($b->Lname ?? ''));
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
                            Patient: <strong class="text-white" id="modalPatientName">-</strong> &nbsp;•&nbsp; 
                            UHID: <strong class="text-white" id="modalUhid">-</strong> &nbsp;•&nbsp; 
                            Date: <strong class="text-white" id="modalBillDate">-</strong>
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
                    <div class="row g-3">
                        <!-- Left 7 Cols: Billed Investigations & Add Service -->
                        <div class="col-lg-7">
                            <!-- Add Investigation Card -->
                            <div class="card border p-2 bg-white mb-2 shadow-sm">
                                <h6 class="fw-bold text-primary border-bottom pb-1 mb-2 d-flex align-items-center gap-1" style="font-size: 12px;">
                                    <i class="uil uil-plus-circle"></i>
                                    <span>Add Investigation / Service</span>
                                </h6>
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-6 position-relative">
                                        <label class="form-label mb-1" style="font-size: 10.5px; font-weight: 600;">Search & Select Service</label>
                                        <input type="text" id="newServiceSearchInput" class="form-control form-control-sm" placeholder="Type test name or code..." autocomplete="off">
                                        <input type="hidden" id="newServiceSelectedId">
                                        <input type="hidden" id="newServiceSelectedName">
                                        <!-- Autocomplete Results Box -->
                                        <div id="serviceSearchDropdown" class="position-absolute bg-white border rounded shadow-lg w-100 mt-1 py-1" style="display: none; max-height: 200px; overflow-y: auto; z-index: 1055; font-size: 11px; left: 0;"></div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-1" style="font-size: 10.5px; font-weight: 600;">Rate (₹)</label>
                                        <input type="number" step="0.01" id="newServiceCharge" class="form-control form-control-sm text-end fw-semibold" placeholder="0.00">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-1" style="font-size: 10.5px; font-weight: 600;">Qty</label>
                                        <input type="number" step="1" min="1" id="newServiceQty" class="form-control form-control-sm text-center" value="1">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-sm btn-primary w-100 fw-bold" id="btnAddServiceRow" style="font-size: 11px;">
                                            <i class="uil uil-plus"></i> Add
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Billed Services Table Card -->
                            <div class="card border p-2 bg-white mb-2 shadow-sm">
                                <h6 class="fw-bold text-dark border-bottom pb-1 mb-2 d-flex justify-content-between align-items-center" style="font-size: 12px;">
                                    <span>Billed Investigations</span>
                                    <span class="badge bg-primary text-white" id="modalItemsCount">0 items</span>
                                </h6>
                                <div style="max-height: 220px; overflow-y: auto;">
                                    <table class="table table-sm table-bordered table-hover mb-0" style="font-size: 11px;">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th>Service Name</th>
                                                <th style="width: 75px; text-align: right;">Rate (₹)</th>
                                                <th style="width: 55px; text-align: center;">Qty</th>
                                                <th style="width: 70px; text-align: right;">Disc (₹)</th>
                                                <th style="width: 75px; text-align: right;">Net (₹)</th>
                                                <th style="width: 55px; text-align: center;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="modalItemsTbody"></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Cancelled / Soft-Deleted Services section -->
                            <div class="card border p-2 bg-white shadow-sm" id="cardCancelledSection" style="display: none;">
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

                        <!-- Right 5 Cols: Payments, Metadata, & Financial Ledger -->
                        <div class="col-lg-5">
                            <!-- Payment Details Card -->
                            <div class="card border p-2 bg-white mb-2 shadow-sm">
                                <h6 class="fw-bold text-dark border-bottom pb-1 mb-2 d-flex justify-content-between align-items-center" style="font-size: 12px;">
                                    <span class="d-flex align-items-center gap-1">
                                        <i class="uil uil-money-bill text-success"></i>
                                        <span>Payment Details</span>
                                    </span>
                                    <span class="badge bg-success text-white" id="modalTotalPaidBadge">Paid: ₹0.00</span>
                                </h6>

                                <!-- Existing Payments Table -->
                                <div style="max-height: 130px; overflow-y: auto;" class="mb-2">
                                    <table class="table table-sm table-bordered mb-0" style="font-size: 10.5px;">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 85px;">Mode</th>
                                                <th style="width: 80px; text-align: right;">Amount (₹)</th>
                                                <th>Txn / Ref</th>
                                                <th style="width: 40px; text-align: center;">Void</th>
                                            </tr>
                                        </thead>
                                        <tbody id="modalPaymentsTbody"></tbody>
                                    </table>
                                </div>

                                <!-- Add New Payment Row -->
                                <div class="bg-light p-2 rounded border">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-bold text-dark" style="font-size: 10.5px;">Record New Payment</span>
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="btnFillDueAmount" style="font-size: 10px;">Fill Balance Due</button>
                                    </div>
                                    <div class="row g-1 align-items-end">
                                        <div class="col-4">
                                            <label class="form-label mb-0" style="font-size: 9.5px; font-weight: 600;">Mode</label>
                                            <select id="newPaymentMode" class="form-select form-select-sm" style="font-size: 10.5px;">
                                                <option value="cash">Cash</option>
                                                <option value="upi">UPI / QR</option>
                                                <option value="card">Card</option>
                                                <option value="net_banking">Net Banking</option>
                                                <option value="cheque">Cheque</option>
                                            </select>
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label mb-0" style="font-size: 9.5px; font-weight: 600;">Amount (₹)</label>
                                            <input type="number" step="0.01" min="0" id="newPaymentAmount" class="form-control form-control-sm text-end fw-bold" style="font-size: 10.5px;">
                                        </div>
                                        <div class="col-4">
                                            <button type="button" class="btn btn-sm btn-success w-100 fw-bold" id="btnAddNewPaymentRow" style="font-size: 10.5px;">
                                                <i class="uil uil-plus"></i> Add
                                            </button>
                                        </div>
                                        <div class="col-12 mt-1">
                                            <input type="text" id="newPaymentTxnId" class="form-control form-control-sm" placeholder="Optional Transaction ID / Reference..." style="font-size: 10.5px;">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Financial Summary & Metadata Card -->
                            <div class="card border p-2 bg-white shadow-sm">
                                <h6 class="fw-bold text-dark border-bottom pb-1 mb-2" style="font-size: 12px;">Financial Summary & Doctor</h6>
                                
                                <div class="mb-2">
                                    <label class="form-label mb-1" style="font-size: 10.5px; font-weight: 600;">Referring Doctor</label>
                                    <select id="editReferralDoctor" class="form-select form-select-sm" style="font-size: 11px;">
                                        @foreach($referrals as $ref)
                                            <option value="{{ $ref->name }}">{{ $ref->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <table class="table table-sm table-borderless mb-0" style="font-size: 11.5px;">
                                    <tr>
                                        <td>Gross Amount:</td>
                                        <td class="text-end fw-bold text-dark" id="modalGross">₹0.00</td>
                                    </tr>
                                    <tr>
                                        <td class="align-middle">Bill Discount (₹):</td>
                                        <td class="text-end" style="width: 110px;">
                                            <input type="number" step="0.01" min="0" id="editBillDiscount" class="form-control form-control-sm text-end text-danger fw-bold" value="0.00" style="font-size: 11px; height: 26px;">
                                        </td>
                                    </tr>
                                    <tr class="border-top">
                                        <td><strong>Net Payable:</strong></td>
                                        <td class="text-end fw-bold text-primary" id="modalNet">₹0.00</td>
                                    </tr>
                                    <tr>
                                        <td>Total Collected:</td>
                                        <td class="text-end fw-bold text-success" id="modalPaid">₹0.00</td>
                                    </tr>
                                    <tr class="border-top">
                                        <td><strong>Balance Due:</strong></td>
                                        <td class="text-end fw-bold" id="modalDue" style="font-size: 13px;">₹0.00</td>
                                    </tr>
                                </table>
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

    window.openEditBillModal = function(billId) {
        activeModalBillId = billId;
        const modal = new bootstrap.Modal(document.getElementById('editBillModal'));
        modal.show();

        $('#modalLoadingSpinner').show();
        $('#modalContentBody').hide();
        $('#serviceSearchDropdown').hide();

        $.get(`/lab/billing/${billId}/details`, function(res) {
            if (!res.success) {
                alert('Could not load bill details.');
                return;
            }

            activeModalReceiptUrl = res.bill.receipt_url;
            activeModalBarcodeUrl = res.bill.barcode_url;

            $('#modalBillBadge').text(res.bill.bill_number ? 'Bill #' + res.bill.bill_number : '#' + res.bill.id);
            $('#modalPatientName').text(res.patient.name);
            $('#modalUhid').text(res.patient.uhid);
            $('#modalBillDate').text(res.bill.created_at);

            // Populate Referrals
            if (res.referral_doctor) {
                $('#editReferralDoctor').val(res.referral_doctor);
            }
            $('#editBillDiscount').val(parseFloat(res.financials.bill_discount || 0).toFixed(2));

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

    // Render Active Services Table
    function renderServicesTable() {
        const activeItems = servicesDraft.filter(it => !it.is_removed);
        $('#modalItemsCount').text(activeItems.length + ' items');

        let html = '';
        if (activeItems.length === 0) {
            html = `<tr><td colspan="6" class="text-center text-muted py-3">No active services on this bill. Add a service above.</td></tr>`;
        } else {
            servicesDraft.forEach((it, idx) => {
                if (it.is_removed) return;
                const net = Math.max(0, (it.charges * it.qty) - it.discount);
                html += `
                    <tr data-index="${idx}">
                        <td class="align-middle">
                            <strong class="text-dark">${it.service_name}</strong>
                            ${it.short_code ? `<span class="badge bg-light text-secondary border font-monospace ms-1" style="font-size: 9px;">${it.short_code}</span>` : ''}
                            ${!it.id ? `<span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 8.5px;">NEW</span>` : ''}
                        </td>
                        <td class="align-middle">
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end input-service-charge" data-idx="${idx}" value="${it.charges.toFixed(2)}" style="font-size: 11px; height: 26px;">
                        </td>
                        <td class="align-middle">
                            <input type="number" step="1" min="1" class="form-control form-control-sm text-center input-service-qty" data-idx="${idx}" value="${it.qty}" style="font-size: 11px; height: 26px;">
                        </td>
                        <td class="align-middle">
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end input-service-discount" data-idx="${idx}" value="${it.discount.toFixed(2)}" style="font-size: 11px; height: 26px;">
                        </td>
                        <td class="align-middle text-end fw-bold text-dark item-net-col">
                            ₹${net.toFixed(2)}
                        </td>
                        <td class="align-middle text-center">
                            <button type="button" class="btn btn-outline-danger btn-sm p-1 btn-remove-service" data-idx="${idx}" title="Cancel & remove test" style="line-height: 1; font-size: 11px;">
                                <i class="uil uil-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        }
        $('#modalItemsTbody').html(html);
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
                    <td style="text-decoration: line-through;">${it.service_name}</td>
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

    // Render Payments Table
    function renderPaymentsTable() {
        const activePayments = paymentsDraft.filter(p => !p.is_deleted);
        let html = '';

        if (activePayments.length === 0) {
            html = `<tr><td colspan="4" class="text-center text-muted py-2">No payments recorded.</td></tr>`;
        } else {
            paymentsDraft.forEach((p, idx) => {
                if (p.is_deleted) return;
                html += `
                    <tr data-p-idx="${idx}">
                        <td class="align-middle">
                            <select class="form-select form-select-sm select-payment-mode" data-idx="${idx}" style="font-size: 10px; height: 26px; padding: 2px 5px;">
                                <option value="cash" ${p.payment_mode === 'cash' ? 'selected' : ''}>Cash</option>
                                <option value="upi" ${p.payment_mode === 'upi' ? 'selected' : ''}>UPI / QR</option>
                                <option value="card" ${p.payment_mode === 'card' ? 'selected' : ''}>Card</option>
                                <option value="net_banking" ${p.payment_mode === 'net_banking' ? 'selected' : ''}>Net Banking</option>
                                <option value="cheque" ${p.payment_mode === 'cheque' ? 'selected' : ''}>Cheque</option>
                            </select>
                        </td>
                        <td class="align-middle">
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end fw-bold input-payment-amount" data-idx="${idx}" value="${p.amount.toFixed(2)}" style="font-size: 10.5px; height: 26px;">
                        </td>
                        <td class="align-middle">
                            <input type="text" class="form-control form-control-sm input-payment-txnid" data-idx="${idx}" value="${p.transaction_id || ''}" placeholder="Txn / Ref" style="font-size: 10px; height: 26px;">
                        </td>
                        <td class="align-middle text-center">
                            <button type="button" class="btn btn-outline-danger btn-sm p-1 btn-void-payment" data-idx="${idx}" title="Void this payment" style="line-height: 1; font-size: 10.5px;">
                                <i class="uil uil-times"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        }
        $('#modalPaymentsTbody').html(html);
    }

    // Dynamic Financial Calculations
    function calculateFinancials() {
        let gross = 0.0;
        servicesDraft.forEach(it => {
            if (!it.is_removed) {
                gross += Math.max(0, (it.charges * it.qty) - it.discount);
            }
        });

        const billDiscount = parseFloat($('#editBillDiscount').val()) || 0.0;
        const net = Math.max(0, gross - billDiscount);

        let totalPaid = 0.0;
        paymentsDraft.forEach(p => {
            if (!p.is_deleted) {
                totalPaid += p.amount;
            }
        });

        const due = Math.max(0, Math.round((net - totalPaid) * 100) / 100);

        $('#modalGross').text('₹' + gross.toFixed(2));
        $('#modalNet').text('₹' + net.toFixed(2));
        $('#modalPaid').text('₹' + totalPaid.toFixed(2));
        $('#modalTotalPaidBadge').text('Paid: ₹' + totalPaid.toFixed(2));
        $('#modalDue').text('₹' + due.toFixed(2));

        if (due > 0) {
            $('#modalDue').removeClass('text-success').addClass('text-danger');
            $('#modalStatusBadge').html('<span class="badge bg-danger ms-1"><i class="uil uil-exclamation-circle me-1"></i>Due / Unpaid</span>');
            $('#newPaymentAmount').val(due.toFixed(2));
        } else {
            $('#modalDue').removeClass('text-danger').addClass('text-success');
            $('#modalStatusBadge').html('<span class="badge bg-success ms-1"><i class="uil uil-check-circle me-1"></i>Fully Paid</span>');
            $('#newPaymentAmount').val('0.00');
        }
    }

    // 4. Service Autocomplete Live Search
    let searchDebounceTimer = null;
    $('#newServiceSearchInput').on('input', function() {
        const query = $(this).val().trim();
        clearTimeout(searchDebounceTimer);

        if (query.length < 1) {
            $('#serviceSearchDropdown').hide();
            return;
        }

        searchDebounceTimer = setTimeout(function() {
            $.get("{{ route('lab.api.services.search') }}", { q: query }, function(data) {
                const list = data.results || data || [];
                if (list.length === 0) {
                    $('#serviceSearchDropdown').html('<div class="p-2 text-muted">No investigations found.</div>').show();
                    return;
                }

                let itemsHtml = '';
                list.forEach(srv => {
                    const price = parseFloat(srv.charge || srv.charges || 0).toFixed(2);
                    itemsHtml += `
                        <div class="px-2 py-1.5 service-search-item" style="cursor: pointer; border-bottom: 1px solid #f1f5f9;" 
                             data-id="${srv.id}" data-name="${srv.name || srv.service}" data-charge="${price}">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong>${srv.name || srv.service}</strong>
                                <span class="badge bg-primary text-white">₹${price}</span>
                            </div>
                            <div class="text-muted" style="font-size: 9.5px;">Code: ${srv.short_code || srv.code || '-'}</div>
                        </div>
                    `;
                });
                $('#serviceSearchDropdown').html(itemsHtml).show();
            });
        }, 250);
    });

    // Select Service from Search Dropdown
    $(document).on('click', '.service-search-item', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const charge = $(this).data('charge');

        $('#newServiceSelectedId').val(id);
        $('#newServiceSelectedName').val(name);
        $('#newServiceSearchInput').val(name);
        $('#newServiceCharge').val(charge);
        $('#serviceSearchDropdown').hide();
    });

    // Close Dropdown on Click Outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#newServiceSearchInput, #serviceSearchDropdown').length) {
            $('#serviceSearchDropdown').hide();
        }
    });

    // 5. Add Service to Bill
    $('#btnAddServiceRow').on('click', function() {
        const sId = parseInt($('#newServiceSelectedId').val());
        const sName = $('#newServiceSelectedName').val() || $('#newServiceSearchInput').val().trim();
        const charge = parseFloat($('#newServiceCharge').val()) || 0;
        const qty = parseFloat($('#newServiceQty').val()) || 1;

        if (!sId || !sName) {
            alert('Please search and select a valid test/service first.');
            $('#newServiceSearchInput').focus();
            return;
        }

        // Add to services draft
        servicesDraft.push({
            id: null, // brand new service
            service_id: sId,
            service_name: sName,
            short_code: '',
            charges: charge,
            qty: qty,
            discount: 0,
            is_removed: false
        });

        // Reset add inputs
        $('#newServiceSelectedId').val('');
        $('#newServiceSelectedName').val('');
        $('#newServiceSearchInput').val('');
        $('#newServiceCharge').val('');
        $('#newServiceQty').val('1');

        renderServicesTable();
        calculateFinancials();
    });

    // 6. Inline Service Edits (Rate, Qty, Discount)
    $(document).on('input', '.input-service-charge', function() {
        const idx = $(this).data('idx');
        servicesDraft[idx].charges = parseFloat($(this).val()) || 0;
        calculateFinancials();
        renderServicesTable();
    });

    $(document).on('input', '.input-service-qty', function() {
        const idx = $(this).data('idx');
        servicesDraft[idx].qty = parseFloat($(this).val()) || 1;
        calculateFinancials();
        renderServicesTable();
    });

    $(document).on('input', '.input-service-discount', function() {
        const idx = $(this).data('idx');
        servicesDraft[idx].discount = parseFloat($(this).val()) || 0;
        calculateFinancials();
        renderServicesTable();
    });

    // 7. Remove Service (Marks as Cancelled and Soft-Deletes)
    $(document).on('click', '.btn-remove-service', function() {
        const idx = $(this).data('idx');
        const item = servicesDraft[idx];

        if (confirm(`Are you sure you want to remove and cancel "${item.service_name}"?`)) {
            if (item.id) {
                item.is_removed = true;
                // Add to cancelled draft for immediate visual tracking
                cancelledDraft.unshift({
                    id: item.id,
                    service_name: item.service_name,
                    charges: item.charges,
                    cancelled_at: 'Just now'
                });
            } else {
                // Newly added service, simply discard
                servicesDraft.splice(idx, 1);
            }

            renderServicesTable();
            renderCancelledTable();
            calculateFinancials();
        }
    });

    // 8. Add New Payment Row
    $('#btnAddNewPaymentRow').on('click', function() {
        const amt = parseFloat($('#newPaymentAmount').val()) || 0;
        const mode = $('#newPaymentMode').val();
        const txnId = $('#newPaymentTxnId').val().trim();

        if (amt <= 0) {
            alert('Please enter a valid payment amount greater than zero.');
            $('#newPaymentAmount').focus();
            return;
        }

        paymentsDraft.push({
            id: null, // brand new payment
            amount: amt,
            payment_mode: mode,
            transaction_id: txnId,
            date: 'Today',
            is_deleted: false
        });

        $('#newPaymentAmount').val('');
        $('#newPaymentTxnId').val('');

        renderPaymentsTable();
        calculateFinancials();
    });

    // Fill Balance Due button
    $('#btnFillDueAmount').on('click', function() {
        let gross = 0.0;
        servicesDraft.forEach(it => {
            if (!it.is_removed) gross += Math.max(0, (it.charges * it.qty) - it.discount);
        });
        const billDiscount = parseFloat($('#editBillDiscount').val()) || 0.0;
        const net = Math.max(0, gross - billDiscount);
        let totalPaid = 0.0;
        paymentsDraft.forEach(p => {
            if (!p.is_deleted) totalPaid += p.amount;
        });
        const due = Math.max(0, Math.round((net - totalPaid) * 100) / 100);
        $('#newPaymentAmount').val(due.toFixed(2));
    });

    // Inline Payment Edits
    $(document).on('input', '.input-payment-amount', function() {
        const idx = $(this).data('idx');
        paymentsDraft[idx].amount = parseFloat($(this).val()) || 0;
        calculateFinancials();
    });

    $(document).on('change', '.select-payment-mode', function() {
        const idx = $(this).data('idx');
        paymentsDraft[idx].payment_mode = $(this).val();
    });

    $(document).on('input', '.input-payment-txnid', function() {
        const idx = $(this).data('idx');
        paymentsDraft[idx].transaction_id = $(this).val().trim();
    });

    // Void / Remove Payment
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

    // Overall Bill Discount Input
    $('#editBillDiscount').on('input', function() {
        calculateFinancials();
    });

    // 9. Save Full Bill Changes
    $('#btnSaveFullBill').on('click', function() {
        if (!activeModalBillId) return;

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="uil uil-spinner uil-spin me-1"></i> Saving Bill...');

        const payload = {
            referral_doctor: $('#editReferralDoctor').val(),
            bill_discount: parseFloat($('#editBillDiscount').val()) || 0,
            services: servicesDraft,
            payments: paymentsDraft
        };

        $.ajax({
            url: `/lab/billing/${activeModalBillId}/save-full`,
            type: 'POST',
            data: JSON.stringify(payload),
            contentType: 'application/json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(res) {
                if (res.success) {
                    if (window.lisToast) {
                        window.lisToast(res.message, 'success');
                    } else {
                        alert(res.message);
                    }
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                }
            },
            error: function(xhr) {
                const msg = xhr.responseJSON?.message || 'Failed to save bill changes.';
                alert(msg);
                btn.prop('disabled', false).html('<i class="uil uil-check-circle me-1"></i> Save Bill Changes');
            }
        });
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
