@extends('lab.layouts.app')

@section('title', 'System Overview & Executive Dashboard | LIS')
@section('context', 'dashboard')

@push('styles')
<!-- Chart.js for High-Performance Clinical Analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>
    /* ==========================================================================
       DASHBOARD COMMAND CENTER STYLES
       ========================================================================== */
    .dashboard-container {
        padding: 12px 18px 30px;
        max-width: 100%;
    }

    /* Top Command Header */
    .dashboard-header-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        box-shadow: 0 1px 4px rgba(15, 23, 42, 0.05);
        padding: 12px 16px;
        margin-bottom: 14px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .dashboard-title-area h1 {
        font-size: 17px;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
        letter-spacing: -0.2px;
    }

    .dashboard-title-area p {
        font-size: 11.5px;
        color: #64748b;
        margin: 2px 0 0 0;
    }

    .system-online-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #065f46;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 9999px;
    }

    .pulse-dot {
        width: 7px;
        height: 7px;
        background: #10b981;
        border-radius: 50%;
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
        animation: pulse-green 1.8s infinite;
    }

    @keyframes pulse-green {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }

    /* Date Filter Presets */
    .period-filter-group {
        display: inline-flex;
        background: #f1f5f9;
        padding: 3px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
    }

    .period-pill {
        font-size: 11px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 4px;
        color: #475569;
        text-decoration: none;
        transition: all 0.15s ease;
    }

    .period-pill:hover {
        color: #0f172a;
        background: #e2e8f0;
    }

    .period-pill.active {
        background: #0d766e;
        color: #ffffff;
        box-shadow: 0 1px 2px rgba(13, 118, 110, 0.25);
    }

    /* Quick Action Buttons */
    .quick-actions-bar {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    .btn-quick-action {
        font-size: 11.5px;
        font-weight: 600;
        padding: 5px 12px;
        border-radius: 5px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.15s ease;
        text-decoration: none;
        border: 1px solid transparent;
    }

    .btn-quick-action-primary {
        background: #0d766e;
        color: #ffffff;
    }
    .btn-quick-action-primary:hover {
        background: #0f4e48;
        color: #ffffff;
        transform: translateY(-1px);
    }

    .btn-quick-action-light {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #334155;
    }
    .btn-quick-action-light:hover {
        background: #f1f5f9;
        color: #0f172a;
        border-color: #94a3b8;
    }

    /* Metric KPI Cards Grid */
    .kpi-metric-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 12px 14px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        position: relative;
        overflow: hidden;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .kpi-metric-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.07);
    }

    .kpi-metric-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
    }

    .kpi-teal::before { background: #0d766e; }
    .kpi-blue::before { background: #0284c7; }
    .kpi-amber::before { background: #d97706; }
    .kpi-green::before { background: #16a34a; }
    .kpi-purple::before { background: #7c3aed; }
    .kpi-rose::before { background: #e11d48; }

    .kpi-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 6px;
    }

    .kpi-metric-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #64748b;
    }

    .kpi-icon-badge {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
    }

    .icon-teal { background: #ccfbf1; color: #0f766e; }
    .icon-blue { background: #e0f2fe; color: #0284c7; }
    .icon-amber { background: #fef3c7; color: #b45309; }
    .icon-green { background: #dcfce7; color: #15803d; }
    .icon-purple { background: #f3e8ff; color: #7e22ce; }
    .icon-rose { background: #ffe4e6; color: #be123c; }

    .kpi-value-row {
        display: flex;
        align-items: baseline;
        gap: 6px;
    }

    .kpi-primary-val {
        font-size: 22px;
        font-weight: 800;
        color: #0f172a;
        font-variant-numeric: tabular-nums;
        line-height: 1.1;
    }

    .kpi-subtext {
        font-size: 11px;
        color: #64748b;
        margin-top: 4px;
    }

    /* Pipeline Stepper */
    .pipeline-section {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 14px 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        margin-bottom: 14px;
    }

    .pipeline-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }

    .pipeline-header h2 {
        font-size: 13.5px;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .pipeline-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 8px;
    }

    @media (max-width: 992px) {
        .pipeline-grid {
            grid-template-columns: repeat(4, 1fr);
        }
    }
    @media (max-width: 576px) {
        .pipeline-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    .pipeline-step-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 10px 8px;
        text-align: center;
        text-decoration: none;
        color: inherit;
        transition: all 0.15s ease;
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .pipeline-step-card:hover {
        background: #ffffff;
        border-color: #0d766e;
        transform: translateY(-2px);
        box-shadow: 0 3px 8px rgba(13, 118, 110, 0.12);
    }

    .pipeline-step-badge {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: 0.3px;
    }

    .pipeline-step-count {
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        margin: 4px 0 2px;
        font-variant-numeric: tabular-nums;
    }

    .pipeline-step-status {
        font-size: 10.5px;
        font-weight: 600;
        padding: 1px 6px;
        border-radius: 4px;
        display: inline-block;
        margin: 0 auto;
    }

    .step-ordered { background: #e0f2fe; color: #0369a1; }
    .step-collected { background: #fef3c7; color: #b45309; }
    .step-received { background: #fef08a; color: #854d0e; }
    .step-in_process { background: #ede9fe; color: #6d28d9; }
    .step-resulted { background: #fed7aa; color: #c2410c; }
    .step-verified { background: #bbf7d0; color: #15803d; }
    .step-released { background: #dcfce7; color: #166534; }

    /* Dashboard Main Analytics Grid */
    .chart-panel-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 14px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        margin-bottom: 14px;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .chart-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 8px;
    }

    .chart-panel-header h3 {
        font-size: 13px;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* Live Data Tables */
    .dashboard-table {
        width: 100%;
        font-size: 12px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .dashboard-table th {
        background: #f8fafc;
        color: #475569;
        font-weight: 700;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        padding: 7px 10px;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }

    .dashboard-table td {
        padding: 8px 10px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    .dashboard-table tr:hover td {
        background: #fbfcfe;
    }

    /* Status Badges */
    .lis-status-badge {
        font-size: 10.5px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .badge-ordered { background: #e0f2fe; color: #0369a1; }
    .badge-collected { background: #fef3c7; color: #b45309; }
    .badge-received { background: #fef08a; color: #854d0e; }
    .badge-in_process { background: #ede9fe; color: #6d28d9; }
    .badge-resulted { background: #fed7aa; color: #c2410c; }
    .badge-verified { background: #bbf7d0; color: #15803d; }
    .badge-released { background: #dcfce7; color: #166534; }
    .badge-cancelled { background: #fee2e2; color: #991b1b; }

    /* Critical Alert Item */
    .critical-alert-row {
        background: #fff1f2;
        border-left: 3px solid #e11d48;
        padding: 8px 10px;
        border-radius: 4px;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 11.5px;
        transition: background 0.15s ease;
    }

    .critical-alert-row:hover {
        background: #ffe4e6;
    }

    .critical-param {
        font-weight: 700;
        color: #9f1239;
    }

    .critical-value-pill {
        background: #be123c;
        color: #ffffff;
        font-weight: 800;
        padding: 1px 6px;
        border-radius: 4px;
        font-size: 11px;
    }

    /* Payment Collection Report Card Styles */
    .payment-report-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 14px 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        margin-bottom: 14px;
    }

    .payment-mode-item {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 10px 12px;
        transition: all 0.15s ease;
        height: 100%;
    }

    .payment-mode-item:hover {
        background: #ffffff;
        border-color: #0d766e;
        transform: translateY(-2px);
        box-shadow: 0 3px 8px rgba(13, 118, 110, 0.1);
    }

    .payment-mode-icon {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
    }

    .payment-progress-bar {
        height: 6px;
        border-radius: 3px;
        background: #e2e8f0;
        overflow: hidden;
        margin-top: 6px;
    }

    .payment-progress-fill {
        height: 100%;
        border-radius: 3px;
    }

    /* System Diagnostics Footer Card */
    .system-diag-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 12px 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        font-size: 11.5px;
        color: #475569;
    }

    .diag-item {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .diag-title {
        font-weight: 600;
        color: #64748b;
    }

    .diag-val {
        font-weight: 700;
        color: #0f172a;
    }
</style>
@endpush

@section('content')
<div class="dashboard-container">

    <!-- 1. TOP COMMAND BAR & LIVE CONTROLS -->
    <div class="dashboard-header-card">
        <div class="dashboard-title-area">
            <h1>
                <i class="uil uil-tachometer-fast-alt text-primary"></i>
                <span>System Overview & Command Center</span>
                <span class="system-online-pill ms-2" title="LIS Database and Engine Operational">
                    <span class="pulse-dot"></span> System Online
                </span>
            </h1>
            <p>
                <span>{{ $labSetting->lab_name ?: 'Central Diagnostic Laboratory' }}</span>
                <span class="mx-1">•</span>
                <span id="liveClockDisplay" class="fw-semibold text-dark"></span>
                <span class="mx-1">•</span>
                <span class="text-primary fw-medium">{{ $periodLabel }}</span>
            </p>
        </div>

        <!-- Filter Presets & Quick Action Shortcuts -->
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="period-filter-group" role="group" aria-label="Date presets">
                <a href="{{ route('lab.dashboard', ['period' => 'today']) }}" class="period-pill {{ $period === 'today' ? 'active' : '' }}">Today</a>
                <a href="{{ route('lab.dashboard', ['period' => 'yesterday']) }}" class="period-pill {{ $period === 'yesterday' ? 'active' : '' }}">Yesterday</a>
                <a href="{{ route('lab.dashboard', ['period' => 'this_week']) }}" class="period-pill {{ $period === 'this_week' ? 'active' : '' }}">7 Days</a>
                <a href="{{ route('lab.dashboard', ['period' => 'this_month']) }}" class="period-pill {{ $period === 'this_month' ? 'active' : '' }}">This Month</a>
                <a href="{{ route('lab.dashboard', ['period' => 'all']) }}" class="period-pill {{ $period === 'all' ? 'active' : '' }}">All Time</a>
                <button type="button" class="period-pill border-0 {{ $period === 'custom' ? 'active' : '' }}" data-bs-toggle="modal" data-bs-target="#customDateModal">
                    <i class="uil uil-calendar-alt"></i> Custom
                </button>
            </div>

            <div class="quick-actions-bar">
                <a href="{{ route('lab.billing.index') }}" class="btn-quick-action btn-quick-action-primary" title="Create New Bill (Shortcut: F2)">
                    <i class="uil uil-plus-circle"></i>
                    <span>New Bill (F2)</span>
                </a>
                <button type="button" class="btn-quick-action btn-quick-action-light" onclick="window.location.reload();" title="Refresh Live Data">
                    <i class="uil uil-sync"></i>
                    <span class="d-none d-sm-inline">Refresh</span>
                </button>
            </div>
        </div>
    </div>

    <!-- 2. HIGH IMPACT METRIC KPI TILES -->
    <div class="row g-2 mb-3">
        <!-- Tile 1: Total Orders & Patient Registrations / Visits -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="kpi-metric-card kpi-teal">
                <div class="kpi-card-top">
                    <span class="kpi-metric-label">Period Orders</span>
                    <span class="kpi-icon-badge icon-teal"><i class="uil uil-clipboard-notes"></i></span>
                </div>
                <div class="kpi-value-row">
                    <span class="kpi-primary-val">{{ number_format($totalOrdersInPeriod) }}</span>
                    <span class="badge bg-light text-muted small">All: {{ number_format($totalOrdersAllTime) }}</span>
                </div>
                <div class="kpi-subtext d-flex align-items-center justify-content-between">
                    <span>
                        <i class="uil uil-user me-1 text-primary"></i> <strong>{{ number_format($patientsInPeriod) }}</strong> Registered • <strong>{{ number_format($visitsInPeriod) }}</strong> Visits
                    </span>
                    <button type="button" class="btn btn-link p-0 text-decoration-none text-primary" data-bs-toggle="modal" data-bs-target="#patientsAuditModal" title="Click to inspect patient registration & visit audit breakdown" style="font-size: 10.5px;">
                        <i class="uil uil-info-circle"></i> Verify
                    </button>
                </div>
            </div>
        </div>

        <!-- Tile 2: Gross Billed -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="kpi-metric-card kpi-blue">
                <div class="kpi-card-top">
                    <span class="kpi-metric-label">Net Billed</span>
                    <span class="kpi-icon-badge icon-blue"><i class="uil uil-invoice"></i></span>
                </div>
                <div class="kpi-value-row">
                    <span class="kpi-primary-val">₹{{ number_format($netBilled, 0) }}</span>
                </div>
                <div class="kpi-subtext text-truncate">
                    Gross: ₹{{ number_format($grossBilled, 0) }} @if($totalDiscounts > 0)<span class="text-danger">(-₹{{ number_format($totalDiscounts, 0) }})</span>@endif
                </div>
            </div>
        </div>

        <!-- Tile 3: Collected Revenue -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="kpi-metric-card kpi-green">
                <div class="kpi-card-top">
                    <span class="kpi-metric-label">Total Collected</span>
                    <span class="kpi-icon-badge icon-green"><i class="uil uil-money-bill"></i></span>
                </div>
                <div class="kpi-value-row">
                    <span class="kpi-primary-val text-success">₹{{ number_format($totalCollected, 0) }}</span>
                </div>
                <div class="kpi-subtext">
                    <i class="uil uil-percentage text-success"></i> <strong>{{ $collectionRate }}%</strong> collection efficiency
                </div>
            </div>
        </div>

        <!-- Tile 4: Due / Outstanding Balance -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="kpi-metric-card kpi-amber">
                <div class="kpi-card-top">
                    <span class="kpi-metric-label">Pending Dues</span>
                    <span class="kpi-icon-badge icon-amber"><i class="uil uil-clock-eight"></i></span>
                </div>
                <div class="kpi-value-row">
                    <span class="kpi-primary-val {{ $totalDue > 0 ? 'text-amber' : 'text-muted' }}">₹{{ number_format($totalDue, 0) }}</span>
                </div>
                <div class="kpi-subtext">
                    <a href="{{ route('lab.billing.daily-summary', ['status' => 'due']) }}" class="text-decoration-none text-muted">
                        <i class="uil uil-arrow-right"></i> Review unpaid bills
                    </a>
                </div>
            </div>
        </div>

        <!-- Tile 5: Active Workload Pipeline -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="kpi-metric-card kpi-purple">
                <div class="kpi-card-top">
                    <span class="kpi-metric-label">Active Workload</span>
                    <span class="kpi-icon-badge icon-purple"><i class="uil uil-flask"></i></span>
                </div>
                <div class="kpi-value-row">
                    <span class="kpi-primary-val text-purple">{{ number_format($activeWorkload) }}</span>
                    <span class="badge bg-light text-muted small">In-Lab</span>
                </div>
                <div class="kpi-subtext">
                    Pending tests across benches
                </div>
            </div>
        </div>

        <!-- Tile 6: Reports Released -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="kpi-metric-card kpi-green">
                <div class="kpi-card-top">
                    <span class="kpi-metric-label">Reports Released</span>
                    <span class="kpi-icon-badge icon-green"><i class="uil uil-file-check-alt"></i></span>
                </div>
                <div class="kpi-value-row">
                    <span class="kpi-primary-val text-success">{{ number_format($pipeline['released']) }}</span>
                    <span class="badge bg-light text-muted small">All: {{ number_format($totalReports) }}</span>
                </div>
                <div class="kpi-subtext">
                    <a href="{{ route('lab.reports.index') }}" class="text-decoration-none text-muted">
                        <i class="uil uil-arrow-right"></i> Review reports dispatch
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. DEDICATED PAYMENT COLLECTION REPORT CARD (MODE-WISE BREAKDOWN) -->
    <div class="payment-report-card">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
            <div>
                <h2 class="fs-6 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <i class="uil uil-wallet text-success fs-5"></i>
                    <span>Mode-wise Payment Collection Report</span>
                </h2>
                <div class="text-muted small" style="font-size: 11.5px;">
                    Detailed cash, digital QR, and card collections breakdown for <strong>{{ $periodLabel }}</strong>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <ul class="nav nav-pills" id="paymentModeTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active py-1 px-3" id="period-tab" data-bs-toggle="pill" data-bs-target="#payment-period-pane" type="button" role="tab" style="font-size: 11px; font-weight: 600;">
                            Selected Period (₹{{ number_format($periodCollectionSum, 2) }})
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-1 px-3" id="alltime-tab" data-bs-toggle="pill" data-bs-target="#payment-alltime-pane" type="button" role="tab" style="font-size: 11px; font-weight: 600;">
                            All-Time Total (₹{{ number_format($allTimeCollectionSum, 2) }})
                        </button>
                    </li>
                </ul>

                <a href="{{ route('lab.billing.daily-summary') }}" class="btn btn-sm btn-outline-secondary py-1 px-2" style="font-size: 11px;">
                    <i class="uil uil-receipt"></i> Full Ledger
                </a>
            </div>
        </div>

        <div class="tab-content" id="paymentModeTabContent">
            <!-- PANE 1: SELECTED PERIOD PAYMENT MODES -->
            <div class="tab-pane fade show active" id="payment-period-pane" role="tabpanel" aria-labelledby="period-tab">
                @if($periodPaymentModes->isNotEmpty())
                    <div class="row g-2 mb-3">
                        @foreach($periodPaymentModes as $pm)
                            <div class="col-xl-4 col-md-6">
                                <div class="payment-mode-item">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="payment-mode-icon" style="background: {{ $pm->color }}15; color: {{ $pm->color }};">
                                                <i class="{{ $pm->icon }}"></i>
                                            </span>
                                            <div>
                                                <div class="fw-bold text-dark" style="font-size: 12.5px;">{{ $pm->label }}</div>
                                                <div class="text-muted" style="font-size: 10.5px;">
                                                    <span class="badge bg-light text-dark border">{{ $pm->tx_count }} Transactions</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold text-dark font-monospace" style="font-size: 15px;">
                                                ₹{{ number_format($pm->amount, 2) }}
                                            </div>
                                            <span class="badge" style="background: {{ $pm->color }}20; color: {{ $pm->color }}; font-size: 10.5px; font-weight: 700;">
                                                {{ $pm->percent }}% share
                                            </span>
                                        </div>
                                    </div>
                                    <div class="payment-progress-bar">
                                        <div class="payment-progress-fill" style="width: {{ $pm->percent }}%; background: {{ $pm->color }};"></div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="p-2 bg-light rounded border d-flex align-items-center justify-content-between flex-wrap gap-2" style="font-size: 11.5px;">
                        <div>
                            <span class="text-muted">Period Total Collections:</span>
                            <strong class="text-success font-monospace fs-6 ms-1">₹{{ number_format($periodCollectionSum, 2) }}</strong>
                            <span class="text-muted ms-2">({{ $periodTxCount }} total receipts issued)</span>
                        </div>
                        <div>
                            <span class="text-muted">Average Receipt Value:</span>
                            <strong class="text-dark font-monospace ms-1">
                                ₹{{ $periodTxCount > 0 ? number_format($periodCollectionSum / $periodTxCount, 2) : '0.00' }}
                            </strong>
                        </div>
                    </div>
                @else
                    <div class="text-center text-muted py-4 bg-light rounded border">
                        <i class="uil uil-receipt text-secondary fs-3 d-block mb-1"></i>
                        <span>No payment transactions recorded during {{ $periodLabel }}.</span>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="$('#alltime-tab').tab('show');" style="font-size: 11px;">
                                View All-Time Cumulative Collection (₹{{ number_format($allTimeCollectionSum, 2) }})
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            <!-- PANE 2: ALL-TIME PAYMENT MODES -->
            <div class="tab-pane fade" id="payment-alltime-pane" role="tabpanel" aria-labelledby="alltime-tab">
                <div class="row g-2 mb-3">
                    @foreach($allTimePaymentModes as $pm)
                        <div class="col-xl-4 col-md-6">
                            <div class="payment-mode-item">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="payment-mode-icon" style="background: {{ $pm->color }}15; color: {{ $pm->color }};">
                                            <i class="{{ $pm->icon }}"></i>
                                        </span>
                                        <div>
                                            <div class="fw-bold text-dark" style="font-size: 12.5px;">{{ $pm->label }}</div>
                                            <div class="text-muted" style="font-size: 10.5px;">
                                                <span class="badge bg-light text-dark border">{{ number_format($pm->tx_count) }} Txns</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-dark font-monospace" style="font-size: 15px;">
                                            ₹{{ number_format($pm->amount, 2) }}
                                        </div>
                                        <span class="badge" style="background: {{ $pm->color }}20; color: {{ $pm->color }}; font-size: 10.5px; font-weight: 700;">
                                            {{ $pm->percent }}% share
                                        </span>
                                    </div>
                                </div>
                                <div class="payment-progress-bar">
                                    <div class="payment-progress-fill" style="width: {{ $pm->percent }}%; background: {{ $pm->color }};"></div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="p-2 bg-light rounded border d-flex align-items-center justify-content-between flex-wrap gap-2" style="font-size: 11.5px;">
                    <div>
                        <span class="text-muted">All-Time Cumulative Collections:</span>
                        <strong class="text-success font-monospace fs-6 ms-1">₹{{ number_format($allTimeCollectionSum, 2) }}</strong>
                        <span class="text-muted ms-2">({{ number_format($allTimeTxCount) }} total receipts)</span>
                    </div>
                    <div>
                        <span class="text-muted">Average Receipt Value:</span>
                        <strong class="text-dark font-monospace ms-1">
                            ₹{{ $allTimeTxCount > 0 ? number_format($allTimeCollectionSum / $allTimeTxCount, 2) : '0.00' }}
                        </strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. LABORATORY WORKFLOW FUNNEL / LIFECYCLE STEPPER -->
    <div class="pipeline-section">
        <div class="pipeline-header">
            <h2>
                <i class="uil uil-arrows-h text-primary"></i>
                <span>Complete Diagnostic Lifecycle Funnel</span>
            </h2>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-muted border">Live Sample Queue Distribution</span>
                <a href="{{ route('lab.orders.index') }}" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 11px;">
                    View Worklist <i class="uil uil-arrow-right"></i>
                </a>
            </div>
        </div>

        <div class="pipeline-grid">
            <!-- Stage 1: Ordered / Sample Pending -->
            <a href="{{ route('lab.orders.index', ['status' => 'sample_pending']) }}" class="pipeline-step-card">
                <span class="pipeline-step-badge">1. Sample Pending</span>
                <span class="pipeline-step-count text-primary">{{ $pipeline['ordered'] }}</span>
                <span class="pipeline-step-status step-ordered"><i class="uil uil-clock"></i> Phlebotomy</span>
            </a>

            <!-- Stage 2: Collected -->
            <a href="{{ route('lab.orders.index', ['status' => 'sample_collected']) }}" class="pipeline-step-card">
                <span class="pipeline-step-badge">2. Collected</span>
                <span class="pipeline-step-count text-warning">{{ $pipeline['collected'] }}</span>
                <span class="pipeline-step-status step-collected"><i class="uil uil-box"></i> In-Transit</span>
            </a>

            <!-- Stage 3: Received in Lab -->
            <a href="{{ route('lab.orders.index', ['status' => 'sample_received']) }}" class="pipeline-step-card">
                <span class="pipeline-step-badge">3. Accessioned</span>
                <span class="pipeline-step-count text-secondary">{{ $pipeline['received'] }}</span>
                <span class="pipeline-step-status step-received"><i class="uil uil-check"></i> Lab Received</span>
            </a>

            <!-- Stage 4: In Process (Testing) -->
            <a href="{{ route('lab.results.index') }}" class="pipeline-step-card">
                <span class="pipeline-step-badge">4. In-Process</span>
                <span class="pipeline-step-count text-purple">{{ $pipeline['in_process'] }}</span>
                <span class="pipeline-step-status step-in_process"><i class="uil uil-flask"></i> Testing Bench</span>
            </a>

            <!-- Stage 5: Resulted / Pending Verification -->
            <a href="{{ route('lab.verification.index') }}" class="pipeline-step-card">
                <span class="pipeline-step-badge">5. Resulted</span>
                <span class="pipeline-step-count text-danger">{{ $pipeline['resulted'] }}</span>
                <span class="pipeline-step-status step-resulted"><i class="uil uil-file-check"></i> Need Sign-off</span>
            </a>

            <!-- Stage 6: Verified -->
            <a href="{{ route('lab.reports.index') }}" class="pipeline-step-card">
                <span class="pipeline-step-badge">6. Verified</span>
                <span class="pipeline-step-count text-success">{{ $pipeline['verified'] }}</span>
                <span class="pipeline-step-status step-verified"><i class="uil uil-shield-check"></i> Ready Print</span>
            </a>

            <!-- Stage 7: Released -->
            <a href="{{ route('lab.reports.index') }}" class="pipeline-step-card">
                <span class="pipeline-step-badge">7. Released</span>
                <span class="pipeline-step-count text-success">{{ $pipeline['released'] }}</span>
                <span class="pipeline-step-status step-released"><i class="uil uil-check-circle"></i> Dispatched</span>
            </a>
        </div>
    </div>

    <!-- 5. VISUAL ANALYTICS & CLINICAL CHARTS ROW -->
    <div class="row g-2 mb-3">
        <!-- 7-Day Performance Trend Chart -->
        <div class="col-xl-7 col-lg-12">
            <div class="chart-panel-card">
                <div class="chart-panel-header">
                    <h3>
                        <i class="uil uil-chart-line text-primary"></i>
                        <span>7-Day Orders & Revenue Trend</span>
                    </h3>
                    <div class="badge bg-light text-muted border font-monospace" style="font-size: 11px;">Orders vs Collection</div>
                </div>
                <div style="position: relative; height: 230px; width: 100%;">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Status Pipeline & Payment Breakdown Charts (2 halves) -->
        <div class="col-xl-5 col-lg-12">
            <div class="row g-2 h-100">
                <!-- Status Donut Chart -->
                <div class="col-sm-6">
                    <div class="chart-panel-card">
                        <div class="chart-panel-header">
                            <h3><i class="uil uil-chart-pie text-teal"></i> Workload Share</h3>
                        </div>
                        <div style="position: relative; height: 165px; width: 100%;">
                            <canvas id="statusPieChart"></canvas>
                        </div>
                        <div class="text-center text-muted small mt-2" style="font-size: 10.5px;">
                            Active pipeline: <strong>{{ $activeWorkload }}</strong> orders
                        </div>
                    </div>
                </div>

                <!-- Payment Modes Breakdown Donut -->
                <div class="col-sm-6">
                    <div class="chart-panel-card">
                        <div class="chart-panel-header">
                            <h3><i class="uil uil-wallet text-success"></i> Payment Modes</h3>
                        </div>
                        <div style="position: relative; height: 165px; width: 100%;">
                            <canvas id="paymentPieChart"></canvas>
                        </div>
                        <div class="text-center text-muted small mt-2" style="font-size: 10.5px;">
                            Cash, UPI, and Digital receipts
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. OPERATIONAL WORKLISTS & CRITICAL MONITORING -->
    <div class="row g-2 mb-3">
        <!-- Left: Recent Orders Live Feed -->
        <div class="col-xl-8 col-lg-12">
            <div class="chart-panel-card">
                <div class="chart-panel-header">
                    <h3>
                        <i class="uil uil-list-ul text-primary"></i>
                        <span>Recent Laboratory Orders & Live Activity Feed</span>
                    </h3>
                    <a href="{{ route('lab.orders.index') }}" class="btn btn-sm btn-link p-0 text-decoration-none text-primary" style="font-size: 11.5px;">
                        All Orders ({{ $totalOrdersAllTime }}) <i class="uil uil-arrow-right"></i>
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Accession #</th>
                                <th>Patient Details</th>
                                <th>Tests Ordered</th>
                                <th>Billing</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentOrders as $order)
                                @php
                                    $patient = $order->patient;
                                    $patientName = $patient ? trim(($patient->prefix ? $patient->prefix . ' ' : '') . $patient->Fname . ' ' . ($patient->Lname ?? '')) : 'Guest Patient';
                                    $bill = $order->bill;
                                    $isPaid = $bill ? (bool) $bill->is_settled : false;
                                    $statusVal = $order->status instanceof \App\Domain\Lab\States\LabState ? $order->status->value : (string) $order->status;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark font-monospace" style="font-size: 12px;">{{ $order->accession_no }}</div>
                                        <div class="text-muted" style="font-size: 10.5px;">
                                            <i class="uil uil-clock"></i> {{ $order->created_at ? $order->created_at->diffForHumans(null, true) . ' ago' : '-' }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $patientName }}</div>
                                        <div class="text-muted" style="font-size: 10.5px;">
                                            UHID: <span class="font-monospace text-primary">{{ $patient->uhid ?? '-' }}</span> • {{ $patient->age ?? '?' }}Y/{{ $patient->sex ?? '?' }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-truncate text-dark" style="max-width: 220px;" title="{{ $order->items->map(fn($i) => $i->service->service ?? '')->filter()->implode(', ') }}">
                                            @if($order->items->isNotEmpty())
                                                {{ $order->items->first()->service->service ?? 'Lab Investigation' }}
                                                @if($order->items->count() > 1)
                                                    <span class="badge bg-light text-muted border">+{{ $order->items->count() - 1 }} more</span>
                                                @endif
                                            @else
                                                <span class="text-muted">No test items</span>
                                            @endif
                                        </div>
                                        <div class="text-muted" style="font-size: 10px;">
                                            {{ $order->items->count() }} test item(s)
                                        </div>
                                    </td>
                                    <td>
                                        @if($isPaid)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <i class="uil uil-check-circle"></i> Paid
                                            </span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                                <i class="uil uil-clock"></i> Due
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="lis-status-badge badge-{{ $statusVal }}">
                                            {{ strtoupper(str_replace('_', ' ', $statusVal)) }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        @if($statusVal === 'ordered')
                                            <a href="{{ route('lab.orders.index', ['q' => $order->accession_no]) }}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 11px;">
                                                Collect
                                            </a>
                                        @elseif(in_array($statusVal, ['collected', 'received', 'in_process']))
                                            <a href="{{ route('lab.results.entry', $order->id) }}" class="btn btn-sm btn-primary py-0 px-2" style="font-size: 11px;">
                                                Enter Result
                                            </a>
                                        @elseif($statusVal === 'resulted')
                                            <a href="{{ route('lab.verification.index', ['q' => $order->accession_no]) }}" class="btn btn-sm btn-warning py-0 px-2 text-dark" style="font-size: 11px;">
                                                Verify
                                            </a>
                                        @elseif(in_array($statusVal, ['verified', 'released']))
                                            <a href="{{ route('lab.reports.view', $order->id) }}" target="_blank" class="btn btn-sm btn-outline-success py-0 px-2" style="font-size: 11px;" title="View HTML Report">
                                                Report
                                            </a>
                                        @else
                                            <a href="{{ route('lab.orders.index', ['q' => $order->accession_no]) }}" class="btn btn-sm btn-light py-0 px-2" style="font-size: 11px;">
                                                View
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="uil uil-inbox fs-3 d-block mb-1"></i>
                                        No recent orders in this period.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right: Top Tests Leaderboard -->
        <div class="col-xl-4 col-lg-12">
            <!-- Top Performing Catalog Tests -->
            <div class="chart-panel-card">
                <div class="chart-panel-header">
                    <h3>
                        <i class="uil uil-award text-amber"></i>
                        <span>High-Volume Laboratory Tests</span>
                    </h3>
                    <a href="{{ route('lab.masters.tests.index') }}" class="btn btn-sm btn-link p-0 text-decoration-none text-muted" style="font-size: 11px;">
                        Catalog <i class="uil uil-arrow-right"></i>
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Test Name</th>
                                <th class="text-center">Count</th>
                                <th class="text-end">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topServices as $service)
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark text-truncate" style="max-width: 140px;" title="{{ $service->service }}">
                                            {{ $service->service }}
                                        </div>
                                        <div class="text-muted font-monospace" style="font-size: 10px;">{{ $service->code ?: 'LAB' }}</div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border">{{ $service->order_count }}</span>
                                    </td>
                                    <td class="text-end fw-bold text-dark font-monospace">
                                        ₹{{ number_format((float) $service->gross_revenue, 0) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-3 small">
                                        No test statistics available.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 7. SYSTEM HEALTH, LICENSING & INFRASTRUCTURE PROFILE -->
    <div class="system-diag-card">
        <div class="row g-3 align-items-center">
            <div class="col-md-3">
                <div class="diag-item">
                    <i class="uil uil-hospital fs-5 text-primary"></i>
                    <div>
                        <div class="diag-title">Laboratory Center</div>
                        <div class="diag-val">{{ $labSetting->lab_name ?: 'Rahul Clinic / LMPath LIS' }}</div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="diag-item">
                    <i class="uil uil-key-skeleton fs-5 {{ !empty($licenseStatus['valid']) ? 'text-success' : 'text-danger' }}"></i>
                    <div>
                        <div class="diag-title">Software License Status</div>
                        <div class="diag-val">
                            @if(!empty($licenseStatus['valid']))
                                <span class="badge bg-success text-white" style="font-size: 10px;">ACTIVE LICENSE</span>
                                @if(!empty($licenseStatus['days_left']))
                                    <span class="text-muted small">({{ $licenseStatus['days_left'] }} days)</span>
                                @endif
                            @else
                                <span class="badge bg-danger text-white" style="font-size: 10px;">ACTION REQUIRED</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="diag-item">
                    <i class="uil uil-users-alt fs-5 text-teal"></i>
                    <div>
                        <div class="diag-title">System Master Records</div>
                        <div class="diag-val">
                            <span>{{ number_format($totalPatientsAllTime) }} Patients</span> • 
                            <span>{{ number_format($totalServices) }} Tests</span> • 
                            <button type="button" class="btn btn-link p-0 text-decoration-none fw-bold text-primary" data-bs-toggle="modal" data-bs-target="#usersAuditModal" title="Click to inspect all 121 users">
                                {{ number_format($totalUsers) }} Users <i class="uil uil-external-link-alt" style="font-size: 10px;"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3 text-md-end">
                <div class="text-muted" style="font-size: 10.5px;">
                    <span class="fw-semibold text-dark">Hardware ID:</span> <span class="font-monospace text-primary">{{ $machineId }}</span>
                </div>
                <div class="text-muted" style="font-size: 10px; margin-top: 2px;">
                    <i class="uil uil-shield-check text-success"></i> LMPath Professional Enterprise LIS v2.0
                </div>
            </div>
        </div>
    </div>

</div>

<!-- MODAL 1: CUSTOM DATE RANGE MODAL -->
<div class="modal fade" id="customDateModal" tabindex="-1" aria-labelledby="customDateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 8px;">
            <div class="modal-header bg-light py-2 px-3 border-bottom">
                <h5 class="modal-title fs-6 fw-bold text-dark" id="customDateModalLabel">
                    <i class="uil uil-calendar-alt text-primary me-1"></i> Select Date Range
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('lab.dashboard') }}" method="GET">
                <input type="hidden" name="period" value="custom">
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold mb-1">From Date</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $startDate }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label text-muted small fw-semibold mb-1">To Date</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $endDate }}" required>
                    </div>
                </div>
                <div class="modal-footer bg-light p-2 border-top">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="uil uil-filter me-1"></i> Apply Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL 2: PATIENT REGISTRATION & VISIT VERIFICATION AUDIT -->
<div class="modal fade" id="patientsAuditModal" tabindex="-1" aria-labelledby="patientsAuditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 10px;">
            <div class="modal-header bg-light py-2 px-3 border-bottom">
                <div>
                    <h5 class="modal-title fs-6 fw-bold text-dark d-flex align-items-center gap-2" id="patientsAuditModalLabel">
                        <i class="uil uil-user-check text-primary fs-5"></i>
                        <span>Patient Registration & Visit Log Audit</span>
                    </h5>
                    <div class="text-muted" style="font-size: 11px;">
                        Data verification for {{ $periodLabel }}: Database master records vs actual clinical encounters
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <!-- Explanation Metric Cards -->
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <div class="p-2 border rounded bg-white text-center">
                            <span class="text-muted text-uppercase fw-bold" style="font-size: 10px;">New Registered Patients</span>
                            <div class="fs-4 fw-bold text-primary font-monospace">{{ $patientsInPeriod }}</div>
                            <span class="text-muted" style="font-size: 10.5px;">Stored in <code class="text-dark">pi</code> table (All: {{ $totalPatientsAllTime }})</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-2 border rounded bg-white text-center">
                            <span class="text-muted text-uppercase fw-bold" style="font-size: 10px;">Patient Visits Logged</span>
                            <div class="fs-4 fw-bold text-success font-monospace">{{ $visitsInPeriod }}</div>
                            <span class="text-muted" style="font-size: 10.5px;">Stored in <code class="text-dark">visitlog</code> table (All: {{ $totalVisitsAllTime }})</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-2 border rounded bg-white text-center">
                            <span class="text-muted text-uppercase fw-bold" style="font-size: 10px;">Lab Diagnostic Orders</span>
                            <div class="fs-4 fw-bold text-dark font-monospace">{{ $totalOrdersInPeriod }}</div>
                            <span class="text-muted" style="font-size: 10.5px;">Stored in <code class="text-dark">lab_orders</code> table (All: {{ $totalOrdersAllTime }})</span>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info py-2 px-3 mb-3 d-flex align-items-center gap-2" style="font-size: 11.5px; border-radius: 6px;">
                    <i class="uil uil-info-circle fs-5"></i>
                    <div>
                        <strong>Why do these numbers differ?</strong> Each walk-in registration registers a master patient profile in the <code>pi</code> table (generating a unique UHID). When investigations are ordered or clinical consultation begins, a visit record is logged in <code>visitlog</code> and a test requisition is accessioned in <code>lab_orders</code>.
                    </div>
                </div>

                <!-- Recent Registered Patients Table -->
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="fw-bold text-dark mb-0" style="font-size: 12.5px;">Registered Patients in Period ({{ $recentRegisteredPatients->count() }})</h6>
                    <input type="text" id="patientFilterInput" class="form-control form-control-sm" placeholder="Search by UHID, Name or Mobile..." style="max-width: 260px; font-size: 11.5px;">
                </div>

                <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                    <table class="table table-sm table-hover border align-middle mb-0" id="patientAuditTable" style="font-size: 11.5px;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>UHID</th>
                                <th>Patient Name</th>
                                <th>Age / Sex</th>
                                <th>Contact No</th>
                                <th>Registration Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentRegisteredPatients as $rp)
                                <tr>
                                    <td class="font-monospace fw-bold text-primary">{{ $rp->uhid ?: 'P-'.$rp->id }}</td>
                                    <td class="fw-semibold text-dark">{{ trim(($rp->prefix ? $rp->prefix . ' ' : '') . $rp->Fname . ' ' . ($rp->Lname ?? '')) }}</td>
                                    <td>{{ $rp->age ?? '?' }} Y / {{ $rp->sex ?? '?' }}</td>
                                    <td>{{ $rp->mobile_no ?: '-' }}</td>
                                    <td class="text-muted font-monospace">{{ $rp->created_at ? $rp->created_at->format('d M Y, h:i A') : '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">No patient profiles registered in this period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light p-2 border-top">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL 3: SYSTEM USERS & ROLES DIRECTORY (121 USERS BREAKDOWN) -->
<div class="modal fade" id="usersAuditModal" tabindex="-1" aria-labelledby="usersAuditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 10px;">
            <div class="modal-header bg-light py-2 px-3 border-bottom">
                <div>
                    <h5 class="modal-title fs-6 fw-bold text-dark d-flex align-items-center gap-2" id="usersAuditModalLabel">
                        <i class="uil uil-users-alt text-primary fs-5"></i>
                        <span>System Users & Staff Directory Breakdown (121 Total Accounts)</span>
                    </h5>
                    <div class="text-muted" style="font-size: 11px;">
                        Comprehensive audit of registered laboratory technicians, consultants, medical officers, and hospital employees
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <!-- User Category Summary Badges -->
                <div class="row g-2 mb-3">
                    <div class="col-md-2 col-4">
                        <div class="p-2 border rounded bg-white text-center">
                            <span class="text-muted text-uppercase fw-bold" style="font-size: 10px;">Total Accounts</span>
                            <div class="fs-4 fw-bold text-dark font-monospace">{{ $totalUsers }}</div>
                            <span class="badge bg-light text-dark border small" style="font-size: 9.5px;">All Registered</span>
                        </div>
                    </div>
                    <div class="col-md-2 col-4">
                        <div class="p-2 border rounded bg-white text-center">
                            <span class="text-muted text-uppercase fw-bold" style="font-size: 10px;">Active Users</span>
                            <div class="fs-4 fw-bold text-success font-monospace">{{ $activeUsers }}</div>
                            <span class="badge bg-success-subtle text-success small" style="font-size: 9.5px;">{{ $inactiveUsers }} Inactive</span>
                        </div>
                    </div>
                    <div class="col-md-2 col-4">
                        <div class="p-2 border rounded bg-white text-center">
                            <span class="text-muted text-uppercase fw-bold" style="font-size: 10px;">Doctors / MD</span>
                            <div class="fs-4 fw-bold text-primary font-monospace">{{ $doctorUsers }}</div>
                            <span class="badge bg-primary-subtle text-primary small" style="font-size: 9.5px;">Pathologists</span>
                        </div>
                    </div>
                    <div class="col-md-2 col-4">
                        <div class="p-2 border rounded bg-white text-center">
                            <span class="text-muted text-uppercase fw-bold" style="font-size: 10px;">Hospital Staff</span>
                            <div class="fs-4 fw-bold text-teal font-monospace">{{ $staffUsers }}</div>
                            <span class="badge bg-light text-dark border small" style="font-size: 9.5px;">type = emp</span>
                        </div>
                    </div>
                    <div class="col-md-2 col-4">
                        <div class="p-2 border rounded bg-white text-center">
                            <span class="text-muted text-uppercase fw-bold" style="font-size: 10px;">Operators / Tech</span>
                            <div class="fs-4 fw-bold text-amber font-monospace">{{ $operatorUsers }}</div>
                            <span class="badge bg-light text-dark border small" style="font-size: 9.5px;">Phlebotomists</span>
                        </div>
                    </div>
                    <div class="col-md-2 col-4">
                        <div class="p-2 border rounded bg-white text-center">
                            <span class="text-muted text-uppercase fw-bold" style="font-size: 10px;">Consultants / Admins</span>
                            <div class="fs-4 fw-bold text-purple font-monospace">{{ $consultantUsers + $proUsers + $adminUsers }}</div>
                            <span class="badge bg-light text-dark border small" style="font-size: 9.5px;">Visiting / Admin</span>
                        </div>
                    </div>
                </div>

                <!-- User Search & Table -->
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="fw-bold text-dark mb-0" style="font-size: 12.5px;">User Directory List ({{ $usersList->count() }} Accounts)</h6>
                    <input type="text" id="userFilterInput" class="form-control form-control-sm" placeholder="Search user by name, username, or role..." style="max-width: 280px; font-size: 11.5px;">
                </div>

                <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                    <table class="table table-sm table-hover border align-middle mb-0" id="userAuditTable" style="font-size: 11.5px;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>#</th>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Category / Type</th>
                                <th>Doctor?</th>
                                <th>Contact / Email</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($usersList as $idx => $u)
                                <tr>
                                    <td class="text-muted font-monospace">{{ $u->id }}</td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $u->name }}</div>
                                        @if($u->designation)
                                            <div class="text-muted" style="font-size: 10px;">Designation: {{ $u->designation }}</div>
                                        @endif
                                    </td>
                                    <td class="font-monospace text-primary">{{ '@' . $u->username }}</td>
                                    <td>
                                        @if($u->is_doctor)
                                            <span class="badge bg-primary text-white">Doctor / Pathologist</span>
                                        @elseif($u->type === 'emp')
                                            <span class="badge bg-info-subtle text-info-emphasis border">Hospital Employee</span>
                                        @elseif($u->type === 'non-emp')
                                            <span class="badge bg-secondary-subtle text-secondary border">Visiting Consultant</span>
                                        @elseif($u->type === '1')
                                            <span class="badge bg-warning-subtle text-warning-emphasis border">Operator / Tech</span>
                                        @elseif($u->type === 'pro')
                                            <span class="badge bg-purple-subtle text-purple border">PRO Officer</span>
                                        @else
                                            <span class="badge bg-light text-dark border">General User</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($u->is_doctor)
                                            <span class="badge bg-success-subtle text-success"><i class="uil uil-check"></i> Yes</span>
                                        @else
                                            <span class="text-muted small">No</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $u->contact_no ?: ($u->mobile ?: '-') }}</div>
                                        <div class="text-muted" style="font-size: 10px;">{{ $u->email ?: 'No email' }}</div>
                                    </td>
                                    <td>
                                        @if($u->active == 1)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <i class="uil uil-check-circle"></i> Active
                                            </span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                                <i class="uil uil-times-circle"></i> Inactive
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light p-2 border-top">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Live Digital Clock
    function updateLiveClock() {
        const now = new Date();
        const options = { 
            weekday: 'short', 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric',
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit',
            hour12: true 
        };
        const clockEl = document.getElementById('liveClockDisplay');
        if (clockEl) {
            clockEl.textContent = now.toLocaleDateString('en-IN', options);
        }
    }
    updateLiveClock();
    setInterval(updateLiveClock, 1000);

    // Keyboard Shortcuts for LIS Command Center
    document.addEventListener('keydown', function(e) {
        if (e.key === 'F2') {
            e.preventDefault();
            window.location.href = "{{ route('lab.billing.index') }}";
        }
    });

    // Real-Time Search Filter for Patient Audit Modal
    const patientFilterInput = document.getElementById('patientFilterInput');
    if (patientFilterInput) {
        patientFilterInput.addEventListener('keyup', function() {
            const query = this.value.toLowerCase();
            const rows = document.querySelectorAll('#patientAuditTable tbody tr');
            rows.forEach(function(row) {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }

    // Real-Time Search Filter for Users Audit Modal
    const userFilterInput = document.getElementById('userFilterInput');
    if (userFilterInput) {
        userFilterInput.addEventListener('keyup', function() {
            const query = this.value.toLowerCase();
            const rows = document.querySelectorAll('#userAuditTable tbody tr');
            rows.forEach(function(row) {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }

    // -------------------------------------------------------------
    // CHART 1: 7-DAY ORDERS & REVENUE TREND
    // -------------------------------------------------------------
    const trendCtx = document.getElementById('trendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: @json($trendDates),
                datasets: [
                    {
                        label: 'Orders Count',
                        data: @json($trendOrders),
                        borderColor: '#0d766e',
                        backgroundColor: 'rgba(13, 118, 110, 0.1)',
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: '#0d766e',
                        pointBorderColor: '#ffffff',
                        pointHoverRadius: 6,
                        yAxisID: 'yOrders',
                    },
                    {
                        label: 'Collections (₹)',
                        data: @json($trendRevenue),
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22, 163, 74, 0.05)',
                        fill: false,
                        borderDash: [4, 4],
                        tension: 0.35,
                        pointBackgroundColor: '#16a34a',
                        pointBorderColor: '#ffffff',
                        pointHoverRadius: 6,
                        yAxisID: 'yRevenue',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            font: { family: 'Inter', size: 11 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.dataset.yAxisID === 'yRevenue') {
                                    return 'Collections: ₹' + Number(context.raw).toLocaleString('en-IN');
                                }
                                return 'Orders: ' + context.raw + ' orders';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Inter', size: 10.5 } }
                    },
                    yOrders: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        ticks: { precision: 0, font: { family: 'Inter', size: 10 } },
                        title: { display: true, text: 'Orders', font: { size: 10 } }
                    },
                    yRevenue: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: { drawOnChartArea: false },
                        ticks: {
                            font: { family: 'Inter', size: 10 },
                            callback: function(value) { return '₹' + value; }
                        },
                        title: { display: true, text: 'Revenue (₹)', font: { size: 10 } }
                    }
                }
            }
        });
    }

    // -------------------------------------------------------------
    // CHART 2: WORKLOAD STATUS DONUT
    // -------------------------------------------------------------
    const statusCtx = document.getElementById('statusPieChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Sample Pending', 'Collected', 'Accessioned', 'In-Process', 'Resulted', 'Verified', 'Released'],
                datasets: [{
                    data: [
                        {{ $pipeline['ordered'] }},
                        {{ $pipeline['collected'] }},
                        {{ $pipeline['received'] }},
                        {{ $pipeline['in_process'] }},
                        {{ $pipeline['resulted'] }},
                        {{ $pipeline['verified'] }},
                        {{ $pipeline['released'] }}
                    ],
                    backgroundColor: [
                        '#0284c7', // Pending
                        '#d97706', // Collected
                        '#eab308', // Received
                        '#7c3aed', // In Process
                        '#ea580c', // Resulted
                        '#16a34a', // Verified
                        '#059669'  // Released
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ' ' + ctx.label + ': ' + ctx.raw + ' orders';
                            }
                        }
                    }
                }
            }
        });
    }

    // -------------------------------------------------------------
    // CHART 3: PAYMENT MODES DOUGHNUT
    // -------------------------------------------------------------
    const paymentCtx = document.getElementById('paymentPieChart');
    if (paymentCtx) {
        const pLabels = {!! json_encode($paymentModes->pluck('label')->all()) !!};
        const pData = {!! json_encode($paymentModes->pluck('amount')->all()) !!};
        const pColors = {!! json_encode($paymentModes->pluck('color')->all()) !!};

        new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: pLabels.length > 0 ? pLabels : ['Cash Payment'],
                datasets: [{
                    data: pData.length > 0 ? pData : [1],
                    backgroundColor: pColors.length > 0 ? pColors : ['#0d766e'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ' ' + ctx.label + ': ₹' + Number(ctx.raw).toLocaleString('en-IN');
                            }
                        }
                    }
                }
            }
        });
    }
</script>
@endpush
