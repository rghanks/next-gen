@extends('lab.layouts.app')

@section('title', 'Result Entry #' . $order->accession_no . ' | Rahul Clinic & Laboratory')
@section('context', 'results')

@push('styles')
<style>
    /* ══════════════════════════════════════════════════════════════ */
    /* PATIENT SUMMARY BANNER                                         */
    /* ══════════════════════════════════════════════════════════════ */
    .patient-summary-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        padding: 10px 14px;
        margin-bottom: 12px;
    }

    .patient-avatar-badge {
        width: 38px;
        height: 38px;
        background: #0284c7;
        color: #ffffff;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
        flex-shrink: 0;
    }

    .info-pill {
        display: inline-flex;
        align-items: center;
        font-size: 11.5px;
        color: #475569;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 2px 8px;
        border-radius: 4px;
        white-space: nowrap;
    }

    .info-pill strong {
        color: #1e293b;
        margin-left: 3px;
    }

    /* ══════════════════════════════════════════════════════════════ */
    /* WORKSPACE 3-COLUMN GRID LAYOUT                                 */
    /* ══════════════════════════════════════════════════════════════ */
    .entry-workspace-grid {
        display: grid;
        grid-template-columns: 240px minmax(0, 1fr) 250px;
        gap: 12px;
        align-items: start;
        margin-bottom: 60px; /* room for sticky bottom action bar */
    }

    @media (max-width: 1200px) {
        .entry-workspace-grid {
            grid-template-columns: 220px minmax(0, 1fr);
        }
        .entry-context-sidebar {
            display: none;
        }
    }

    @media (max-width: 768px) {
        .entry-workspace-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Left Test Navigator Sidebar */
    .test-navigator-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        overflow: hidden;
        position: sticky;
        top: 60px;
    }

    .test-navigator-header {
        background: #252f4a;
        color: #ffffff;
        font-size: 12px;
        font-weight: 700;
        padding: 8px 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .test-nav-link {
        display: flex;
        align-items: center;
        width: 100%;
        text-align: left;
        background: #ffffff;
        border: none;
        border-bottom: 1px solid #e2e8f0;
        padding: 8px 10px;
        font-size: 11.5px;
        color: #334155;
        cursor: pointer;
        transition: all 0.15s ease;
        text-decoration: none;
        gap: 8px;
    }

    .test-nav-link:hover {
        background: #f1f5f9;
        color: #0284c7;
    }

    .test-nav-link.active {
        background: #e0f2fe;
        color: #0369a1;
        font-weight: 600;
        border-left: 3px solid #0284c7;
    }

    .test-nav-idx {
        width: 20px;
        height: 20px;
        background: #e2e8f0;
        color: #475569;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        font-weight: 700;
        flex-shrink: 0;
    }

    .test-nav-link.active .test-nav-idx {
        background: #0284c7;
        color: #ffffff;
    }

    /* ══════════════════════════════════════════════════════════════ */
    /* RESULT ENTRY CARDS & TABLES                                    */
    /* ══════════════════════════════════════════════════════════════ */
    .investigation-entry-card {
        background: #ffffff;
        border: 2px solid #cbd5e1;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        margin-bottom: 14px;
        overflow: hidden;
    }

    .investigation-card-header {
        background: #252f4a !important;
        color: #ffffff !important;
        padding: 6px 12px !important;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid #1e293b;
    }

    .investigation-card-title,
    .investigation-card-header h6 {
        color: #ffffff !important;
        font-weight: 700 !important;
        font-size: 13.5px !important;
        letter-spacing: 0.25px;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .investigation-card-title span,
    .investigation-card-title i {
        color: #ffffff !important;
    }

    .table-result-entry {
        width: 100%;
        margin-bottom: 0;
        border-collapse: collapse !important;
        font-size: 11.5px;
    }

    .table-result-entry thead th {
        background: #f8fafc;
        color: #334155;
        font-weight: 700;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        border: 1px solid #cbd5e1 !important;
        padding: 5px 8px !important;
        white-space: nowrap;
    }

    .table-result-entry tbody td {
        border: 1px solid #cbd5e1 !important;
        padding: 4px 8px !important;
        vertical-align: middle !important;
        line-height: 1.25;
    }

    .table-result-entry tbody tr:hover td {
        background-color: #f8fafc !important;
    }

    /* Subgroup Header Row */
    .subgroup-header-row td {
        background-color: #f1f5f9 !important;
        font-weight: 700;
        color: #1e293b;
        font-size: 11px;
        border-top: 1.5px solid #cbd5e1 !important;
        border-bottom: 1.5px solid #cbd5e1 !important;
        padding: 4px 8px !important;
    }

    /* Live Result Inputs */
    .param-input {
        height: 26px !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        border-color: #cbd5e1 !important;
        border-radius: 4px;
        padding: 2px 6px !important;
    }

    .param-input:focus {
        border-color: #0284c7 !important;
        box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.2) !important;
    }

    /* Flag Badges */
    .flag-badge {
        font-size: 10.5px;
        font-weight: 700;
        padding: 1px 6px;
        border-radius: 10px;
        display: inline-block;
        min-width: 28px;
        text-align: center;
        line-height: 16px;
    }

    .flag-n {
        background-color: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
    }

    .flag-l, .flag-h {
        background-color: #fef3c7;
        color: #b45309;
        border: 1px solid #fcd34d;
    }

    .flag-ll, .flag-hh {
        background-color: #fee2e2;
        color: #b91c1c;
        border: 1px solid #f87171;
        animation: pulse-critical 1.8s infinite;
    }

    @keyframes pulse-critical {
        0%, 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4); }
        50% { box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.1); }
    }

    /* Right Context Sidebar Cards */
    .context-box-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        padding: 10px 12px;
        margin-bottom: 12px;
    }

    .context-box-header {
        font-size: 11.5px;
        font-weight: 700;
        color: #1e293b;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 5px;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    /* Fixed Sticky Bottom Action Bar */
    .bottom-result-action-bar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: #ffffff;
        border-top: 2px solid #cbd5e1;
        padding: 8px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 1000;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.08);
    }

    /* Buttons */
    .btn-save-primary {
        background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%) !important;
        border: 1px solid #0284c7 !important;
        color: #ffffff !important;
        font-weight: 600;
        font-size: 12px;
        padding: 5px 14px;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(2,132,199,0.3);
        cursor: pointer;
    }
    .btn-save-primary:hover {
        background: #0369a1 !important;
        color: #ffffff !important;
    }

    .btn-verify-success {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%) !important;
        border: 1px solid #16a34a !important;
        color: #ffffff !important;
        font-weight: 700;
        font-size: 12px;
        padding: 5px 16px;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(22,163,74,0.3);
        cursor: pointer;
    }
    .btn-verify-success:hover {
        background: #15803d !important;
        color: #ffffff !important;
    }

    /* Modal Overlay */
    .lis-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 2000;
    }
    .lis-modal-box {
        background: #ffffff;
        border-radius: 6px;
        width: 480px;
        max-width: 90vw;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        overflow: hidden;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-3 py-2">

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- 1. PATIENT SUMMARY & TOP ACTION BAR                            -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    @php
        $patient = $order->patient;
        $firstSpecimen = $order->specimens->first();
        $isSTAT = $order->priority === 'stat';
        $initials = strtoupper(substr($patient?->Fname ?? 'P', 0, 1) . substr($patient?->Lname ?? '', 0, 1));
        $receiptUrl = route('lab.billing.receipt', $order->id);
        $barcodeUrl = route('lab.billing.barcode', $order->id);
        $reportUrl = route('lab.reports.view', $order->id);
    @endphp

    <div class="patient-summary-card">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <!-- Left: Avatar, Name & Metadata Pills -->
            <div class="d-flex align-items-center gap-3">
                <div class="patient-avatar-badge">{{ $initials ?: 'PT' }}</div>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="fw-bold text-dark m-0" style="font-size: 15px;">
                            {{ $patient?->full_name ?: ('Patient #'.$order->patient_id) }}
                        </h5>
                        @if($isSTAT)
                            <span class="badge bg-danger text-white py-0 px-2" style="font-size: 10px;">STAT · URGENT</span>
                        @endif
                        <span class="badge bg-info-subtle text-primary border border-primary-subtle py-0 px-2" style="font-size: 10px;">
                            {{ $order->status->label() }}
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mt-1">
                        <span class="info-pill"><i class="uil uil-bill me-1 text-primary"></i>Bill:<strong>#{{ $order->bill?->bill_number ?: ($order->bill_id ?: $order->id) }}</strong></span>
                        <span class="info-pill"><i class="uil uil-user me-1 text-teal"></i>UHID:<strong>#{{ $patient?->uhid ?: $patient?->id ?: 'N/A' }}</strong></span>
                        <span class="info-pill"><i class="uil uil-calendar-alt me-1 text-secondary"></i>Age/Sex:<strong>{{ $patient?->age }}Y / {{ strtoupper($patient?->sex ?? 'M') }}</strong></span>
                        @if($patient?->mobile_no)
                            <span class="info-pill"><i class="uil uil-phone me-1 text-secondary"></i>Mobile:<strong>{{ $patient->mobile_no }}</strong></span>
                        @endif
                        <span class="info-pill"><i class="uil uil-user-md me-1 text-secondary"></i>Ref Doctor:<strong>{{ $patient?->referring_doctor ?: 'Dr. Self' }}</strong></span>
                        @if($firstSpecimen)
                            <span class="info-pill"><i class="uil uil-flask me-1 text-success"></i>Sample:<strong>{{ $firstSpecimen->barcode }} ({{ ucfirst($firstSpecimen->specimen_type) }})</strong></span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right: Action Buttons -->
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('lab.orders.index') }}" class="btn btn-sm btn-outline-secondary" style="font-size: 11.5px; height: 30px;">
                    <i class="uil uil-arrow-left me-1"></i> Lab Orders
                </a>
                <button type="button" class="btn btn-sm text-white" style="background-color: #252f4a; font-size: 11.5px; height: 30px;" onclick="printDirectPreview('{{ $receiptUrl }}')" title="Print Bill Receipt">
                    <i class="uil uil-receipt me-1"></i> Receipt
                </button>
                <button type="button" class="btn btn-sm text-white" style="background-color: #0d9488; font-size: 11.5px; height: 30px;" onclick="printDirectPreview('{{ $barcodeUrl }}')" title="Print Barcode Label">
                    <i class="uil uil-barcode me-1"></i> Barcode
                </button>
                <a href="{{ route('lab.reports.print', $order->id) }}" target="_blank" class="btn btn-sm text-white" style="background-color: #0284c7; font-size: 11.5px; height: 30px; display: inline-flex; align-items: center;" title="Generate & Print Full PDF Report">
                    <i class="uil uil-print me-1"></i> Print Report
                </a>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- 2. MAIN 3-COLUMN RESULT ENTRY WORKSPACE                        -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <div class="entry-workspace-grid">

        <!-- Column A: Left Investigations Navigator -->
        <aside class="test-navigator-card">
            <div class="test-navigator-header">
                <span><i class="uil uil-list-ul me-1"></i> Investigations</span>
                <span class="badge bg-white text-dark">{{ count($itemGrids) }}</span>
            </div>
            <div class="p-2 border-bottom">
                <input type="search" id="result-test-filter" class="form-control form-control-sm" placeholder="Filter investigations..." style="font-size: 11.5px; height: 28px;">
            </div>
            <div id="test-nav-items-list" style="max-height: 60vh; overflow-y: auto;">
                @foreach($itemGrids as $grid)
                    @php
                        $gridItem = $grid['item'];
                    @endphp
                    <button type="button" class="test-nav-link {{ $loop->first ? 'active' : '' }}" data-target="result-item-{{ $gridItem->id }}">
                        <span class="test-nav-idx">{{ $loop->iteration }}</span>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="text-truncate fw-semibold">{{ $gridItem->service?->service ?: 'Test #'.$gridItem->service_id }}</div>
                            <small class="text-muted" style="font-size: 10.5px;">{{ count($grid['parameters']) }} parameters · {{ $gridItem->status->label() }}</small>
                        </div>
                    </button>
                @endforeach
            </div>
        </aside>

        <!-- Column B: Center Result Entry Grids -->
        <div class="entry-center-workspace" id="result-grids">
            @forelse($itemGrids as $grid)
                @php
                    $item = $grid['item'];
                    $params = $grid['parameters'];
                    $service = $item->service;
                @endphp
                <div class="investigation-entry-card item-card" id="result-item-{{ $item->id }}" data-item-id="{{ $item->id }}" data-version="{{ $item->row_version }}">
                    <!-- Card Header -->
                    <div class="investigation-card-header">
                        <h6 class="investigation-card-title text-white">
                            <i class="uil uil-flask text-white me-1"></i>
                            <span class="text-white fw-bold">{{ $service?->service ?: 'Investigation #'.$item->service_id }}</span>
                            @if($grid['is_group'])
                                <span class="badge bg-primary text-white py-0 px-2" style="font-size: 9.5px; letter-spacing: 0.3px;">Group Panel</span>
                            @endif
                            @if($service?->short_code)
                                <span class="badge bg-dark bg-opacity-50 text-white-50 border border-white-50 py-0 px-2" style="font-size: 10px;">{{ $service->short_code }}</span>
                            @endif
                        </h6>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-white text-dark item-status-badge shadow-sm" style="font-size: 10px; font-weight: 600;">
                                v{{ $item->row_version }} · {{ $item->status->label() }}
                            </span>
                            <button type="button" class="btn btn-sm btn-light text-primary btn-save-single-item py-0 px-2 fw-semibold shadow-sm" data-item-id="{{ $item->id }}" title="Save only this test" style="font-size: 11px; height: 26px;">
                                <i class="uil uil-save me-1"></i> Save Test
                            </button>
                            <a href="{{ route('lab.reports.print', $order->id) }}?item_id={{ $item->id }}" target="_blank" class="btn btn-sm btn-light text-secondary py-0 px-2 fw-semibold shadow-sm" title="Generate & Print PDF for this test" style="font-size: 11px; height: 26px; display: inline-flex; align-items: center;">
                                <i class="uil uil-print me-1"></i> Print Test
                            </a>
                        </div>
                    </div>

                    <!-- Parameter Entry Table with Crisp Borders -->
                    <div class="table-responsive">
                        <table class="table-result-entry">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 32px;">#</th>
                                    <th style="min-width: 200px;">Investigation / Parameter</th>
                                    <th style="width: 150px;">Result Value</th>
                                    <th style="width: 70px;">Unit</th>
                                    <th style="min-width: 170px;">Biological Reference Range</th>
                                    <th class="text-center" style="width: 55px;">Flag</th>
                                    <th class="text-center" style="width: 75px;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $globalIdx = 1; @endphp
                                @foreach($grid['sections'] as $sec)
                                    {{-- Only show subgroup header if this section contains multiple parameters --}}
                                    @if(count($sec['parameters']) > 1)
                                        <tr class="subgroup-header-row">
                                            <td colspan="7">
                                                <i class="uil uil-layer-group text-primary me-1"></i>
                                                <strong>{{ $sec['component_service']->service }}</strong>
                                                @if($sec['component_service']->short_code)
                                                    <span class="text-muted fw-normal" style="font-size: 10.5px;">({{ $sec['component_service']->short_code }})</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endif

                                    @foreach($sec['parameters'] as $pData)
                                        @php
                                            $p = $pData['parameter'];
                                            $existing = $pData['existing'];
                                            $range = $pData['range'];
                                            $currentVal = $existing ? $existing->display_value : '';
                                            $currentFlag = $existing ? $existing->flag : 'N';
                                            $options = $p->options ?: ($sec['component_service']->input_attributes['options'] ?? []);
                                            $compShort = trim($sec['component_service']->short_code ?? '');
                                            $compName = trim($sec['component_service']->service ?? '');
                                        @endphp
                                        <tr data-param-id="{{ $p->id }}"
                                            data-low="{{ $range['low'] ?? '' }}"
                                            data-high="{{ $range['high'] ?? '' }}"
                                            data-crit-low="{{ $range['critical_low'] ?? '' }}"
                                            data-crit-high="{{ $range['critical_high'] ?? '' }}"
                                            data-formula="{{ $p->formula ?? '' }}"
                                            data-decimals="{{ $p->decimal_places ?? 2 }}">
                                            
                                            <!-- S.N. -->
                                            <td class="text-center text-muted fw-bold">{{ $globalIdx++ }}</td>

                                            <!-- Parameter Name -->
                                            <td>
                                                <span class="fw-semibold text-dark">{{ $p->name }}</span>
                                                @if($compShort !== '' && strcasecmp($compShort, $p->name) !== 0 && strcasecmp($compShort, $p->short_code ?? '') !== 0)
                                                    <span class="text-muted fw-normal" style="font-size: 10.5px;">({{ $compShort }})</span>
                                                @elseif($compName !== '' && strcasecmp($compName, $p->name) !== 0 && count($sec['parameters']) === 1)
                                                    <span class="text-muted fw-normal" style="font-size: 10.5px;">({{ $compName }})</span>
                                                @endif
                                                @if($p->formula)
                                                    <span class="badge bg-secondary-subtle text-secondary border py-0 px-1 ms-1" title="Computed Formula: {{ $p->formula }}">fx</span>
                                                @endif
                                                @if($sec['component_service']->method)
                                                    <div class="text-muted" style="font-size: 10px;">{{ $sec['component_service']->method }}</div>
                                                @elseif($p->method)
                                                    <div class="text-muted" style="font-size: 10px;">{{ $p->method }}</div>
                                                @endif
                                            </td>

                                            <!-- Result Value Input -->
                                            <td>
                                                @if(!empty($options))
                                                    <select class="form-select param-input" data-param-id="{{ $p->id }}" data-short-code="{{ $p->short_code }}" data-param-name="{{ $p->name }}" aria-label="{{ $p->name }}">
                                                        <option value="">-- Select --</option>
                                                        @foreach($options as $opt)
                                                            <option value="{{ $opt }}" {{ (string)$currentVal === (string)$opt ? 'selected' : '' }}>{{ $opt }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input type="{{ $p->input_type === 'numeric' ? 'number' : 'text' }}"
                                                           step="any"
                                                           class="form-control param-input {{ $p->formula ? 'formula-target' : '' }}"
                                                           data-param-id="{{ $p->id }}"
                                                           data-short-code="{{ $p->short_code }}"
                                                           data-param-name="{{ $p->name }}"
                                                           value="{{ $currentVal }}"
                                                           placeholder="{{ $p->formula ? 'Auto (fx)' : 'Enter value...' }}"
                                                           aria-label="{{ $p->name }}">
                                                @endif
                                            </td>

                                            <!-- Unit -->
                                            <td class="text-muted fw-medium">{{ $p->unit ?: '-' }}</td>

                                            <!-- Biological Reference Range -->
                                            <td>
                                                <span class="text-dark">{{ $range['text_range'] ?: ($range['low'] !== null && $range['high'] !== null ? $range['low'] . ' - ' . $range['high'] : 'N/A') }}</span>
                                                @if($range['critical_low'] !== null || $range['critical_high'] !== null)
                                                    <div class="text-danger fw-semibold" style="font-size: 10px;">
                                                        Panic: &lt; {{ $range['critical_low'] ?? '-' }} or &gt; {{ $range['critical_high'] ?? '-' }}
                                                    </div>
                                                @endif
                                            </td>

                                            <!-- Flag Badge -->
                                            <td class="text-center">
                                                <span class="flag-badge flag-{{ strtolower($currentFlag) }}">{{ $currentFlag }}</span>
                                            </td>

                                            <!-- Status / Ack -->
                                            <td class="text-center">
                                                @if(in_array($currentFlag, ['LL', 'HH'], true))
                                                    @if($existing && $existing->criticalAcknowledgement)
                                                        <span class="badge bg-success-subtle text-success border border-success py-0 px-1" title="Informed: {{ $existing->criticalAcknowledgement->informed_person }}">
                                                            Acked
                                                        </span>
                                                    @else
                                                        <button type="button" class="btn btn-danger btn-xs btn-ack-critical py-0 px-2" data-value-id="{{ $existing?->id }}" style="font-size: 10px; height: 22px;">
                                                            Ack !
                                                        </button>
                                                    @endif
                                                @else
                                                    <span class="text-muted" style="font-size: 11px;">Normal</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="card border-0 shadow-sm p-4 text-center text-muted">
                    <i class="uil uil-folder-open font-size-24 d-block mb-2"></i>
                    No investigations found on this order.
                </div>
            @endforelse
        </div>

        <!-- Column C: Right Specimen & Audit Sidebar -->
        <aside class="entry-context-sidebar">
            <!-- Sample Details Card -->
            <div class="context-box-card">
                <div class="context-box-header">
                    <i class="uil uil-flask text-teal"></i> Specimen & Tube
                </div>
                @forelse($order->specimens as $sp)
                    <div class="mb-2 pb-2 border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark" style="font-size: 11.5px;">{{ $sp->barcode }}</span>
                            <span class="badge bg-teal-soft text-teal" style="font-size: 9.5px;">{{ ucfirst($sp->condition ?? 'Good') }}</span>
                        </div>
                        <div class="text-muted" style="font-size: 10.5px; margin-top: 2px;">
                            {{ ucfirst($sp->specimen_type) }} · {{ str_replace('_', ' ', $sp->container) }}
                        </div>
                        <div class="text-muted" style="font-size: 10px;">
                            Collected: {{ $sp->collected_at ? $sp->collected_at->format('d M, h:i A') : 'Pending' }}
                        </div>
                    </div>
                @empty
                    <div class="text-muted" style="font-size: 11px;">No specimen details.</div>
                @endforelse
            </div>

            <!-- Flag Legend Card -->
            <div class="context-box-card">
                <div class="context-box-header">
                    <i class="uil uil-info-circle text-primary"></i> Flags & Interpretation
                </div>
                <div class="d-flex flex-column gap-1" style="font-size: 11px;">
                    <div class="d-flex align-items-center gap-2">
                        <span class="flag-badge flag-n">N</span>
                        <span class="text-muted">Normal (In Range)</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="flag-badge flag-l">L / H</span>
                        <span class="text-muted">Abnormal (Low / High)</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="flag-badge flag-ll">LL / HH</span>
                        <span class="text-danger fw-semibold">Critical Panic Value</span>
                    </div>
                </div>
            </div>

            <!-- Keyboard Shortcuts Card -->
            <div class="context-box-card">
                <div class="context-box-header">
                    <i class="uil uil-keyboard text-secondary"></i> Shortcuts
                </div>
                <div class="d-flex flex-column gap-1" style="font-size: 10.5px; color: #475569;">
                    <div class="d-flex justify-content-between">
                        <span>Next Parameter:</span>
                        <kbd class="bg-light text-dark border px-1">Enter</kbd>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Save Results:</span>
                        <kbd class="bg-light text-dark border px-1">Ctrl + S</kbd>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Save & Approve:</span>
                        <kbd class="bg-light text-dark border px-1">Ctrl + Enter</kbd>
                    </div>
                </div>
            </div>
        </aside>

    </div>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- 3. STICKY BOTTOM ACTION BAR                                    -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <div class="bottom-result-action-bar">
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted" style="font-size: 11.5px;">
                <i class="uil uil-info-circle text-primary me-1"></i>
                Press <kbd class="bg-light text-dark border px-1">Enter</kbd> to move to next parameter.
            </span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" id="btn-save-all-results" class="btn btn-save-primary">
                <i class="uil uil-save me-1"></i> Save Results <span class="badge bg-white text-dark ms-1 py-0 px-1" style="font-size: 9.5px;">Ctrl+S</span>
            </button>
            <button type="button" id="btn-verify-direct" class="btn btn-verify-success">
                <i class="uil uil-check-circle me-1"></i> Save & Approve <span class="badge bg-white text-dark ms-1 py-0 px-1" style="font-size: 9.5px;">Ctrl+Enter</span>
            </button>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════════════════ -->
    <!-- CRITICAL PANIC VALUE MODAL                                     -->
    <!-- ══════════════════════════════════════════════════════════════ -->
<div id="ack-modal" class="lis-modal-overlay">
    <div class="lis-modal-box">
        <div class="p-3 bg-danger text-white d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold"><i class="uil uil-exclamation-triangle me-1"></i> Critical Panic Value Acknowledgement</h6>
            <button type="button" class="btn-close btn-close-white" onclick="$('#ack-modal').hide()"></button>
        </div>
        <div class="p-3">
            <input type="hidden" id="ack-value-id">
            <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size: 12px;" for="ack-person">Informed Clinician / Doctor / Nurse <span class="text-danger">*</span></label>
                <input type="text" id="ack-person" class="form-control form-control-sm" placeholder="Doctor or nurse informed...">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size: 12px;" for="ack-remarks">Remarks / Read-Back Confirmation</label>
                <textarea id="ack-remarks" class="form-control form-control-sm" style="height: 60px; resize: none;" placeholder="e.g. Critical result read-back confirmed over phone at 14:30..."></textarea>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="$('#ack-modal').hide()">Cancel</button>
                <button type="button" id="btn-confirm-ack" class="btn btn-sm btn-danger fw-semibold">Confirm Acknowledgement</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function($) {
    'use strict';

    // ══════════════════════════════════════════════════════════════
    // 1. Direct Browser Print Preview
    // ══════════════════════════════════════════════════════════════
    let isPrinting = false;
    window.printDirectPreview = function(url) {
        if (!url || isPrinting) return;
        isPrinting = true;

        $('#entry-print-frame').remove();
        const iframe = $('<iframe id="entry-print-frame"></iframe>').css({
            position: 'fixed', right: '0', bottom: '0', width: '0', height: '0', border: '0', visibility: 'hidden'
        });

        let hasPrinted = false;
        iframe.on('load', function() {
            if (hasPrinted) return;
            hasPrinted = true;
            const f = this;
            setTimeout(() => {
                try {
                    f.contentWindow.focus();
                    f.contentWindow.print();
                } catch(e) {
                    console.warn(e);
                } finally {
                    setTimeout(() => { isPrinting = false; }, 1000);
                }
            }, 120);
        });

        $('body').append(iframe);
        iframe.attr('src', url);
    };

    // ══════════════════════════════════════════════════════════════
    // 2. Left Navigator Switching & Search Filter
    // ══════════════════════════════════════════════════════════════
    $('.test-nav-link').on('click', function() {
        $('.test-nav-link').removeClass('active');
        $(this).addClass('active');
        const targetId = $(this).data('target');
        const targetEl = document.getElementById(targetId);
        if (targetEl) {
            targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            $(targetEl).find('.param-input').first().focus();
        }
    });

    $('#result-test-filter').on('input', function() {
        const q = $(this).val().toLowerCase().trim();
        $('.test-nav-link').each(function() {
            const txt = $(this).text().toLowerCase();
            $(this).toggle(txt.includes(q));
        });
    });

    // ══════════════════════════════════════════════════════════════
    // 3. Dynamic Live Flag Recalculation & Formula Triggers
    // ══════════════════════════════════════════════════════════════
    let formulaTimer;

    $(document).on('input change', '.param-input', function() {
        const tr = $(this).closest('tr');
        const val = parseFloat($(this).val());
        const low = parseFloat(tr.data('low'));
        const high = parseFloat(tr.data('high'));
        const critLow = parseFloat(tr.data('crit-low'));
        const critHigh = parseFloat(tr.data('crit-high'));
        const badge = tr.find('.flag-badge');

        if (isNaN(val)) {
            badge.attr('class', 'flag-badge flag-n').text('N');
            return;
        }

        let flag = 'N';
        if (!isNaN(critLow) && val < critLow) {
            flag = 'LL';
        } else if (!isNaN(critHigh) && val > critHigh) {
            flag = 'HH';
        } else if (!isNaN(low) && val < low) {
            flag = 'L';
        } else if (!isNaN(high) && val > high) {
            flag = 'H';
        }

        badge.attr('class', 'flag-badge flag-' + flag.toLowerCase()).text(flag);

        // Auto-recalculate formulas if any
        clearTimeout(formulaTimer);
        formulaTimer = setTimeout(recomputeFormulas, 250);
    });

    // ══════════════════════════════════════════════════════════════
    // 4. Keyboard Navigation: Enter advances to next input
    // ══════════════════════════════════════════════════════════════
    $(document).on('keydown', '.param-input', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const inputs = $('.param-input');
            const idx = inputs.index(this);
            if (idx >= 0 && idx < inputs.length - 1) {
                inputs.eq(idx + 1).focus();
            } else {
                $('#btn-save-all-results').focus();
            }
        }
    });

    // ══════════════════════════════════════════════════════════════
    // 5. Global Shortcuts (Ctrl+S, Ctrl+Enter)
    // ══════════════════════════════════════════════════════════════
    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) {
            e.preventDefault();
            $('#btn-save-all-results').trigger('click');
        } else if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            $('#btn-verify-direct').trigger('click');
        }
    });

    // ══════════════════════════════════════════════════════════════
    // 6. Save Card Results Helper
    // ══════════════════════════════════════════════════════════════
    function saveCardResults(card, onSuccess, onError, onComplete) {
        const itemId = card.data('item-id');
        const version = card.data('version');

        const results = [];
        let hasAnyValue = false;
        card.find('.param-input').each(function() {
            const val = $(this).val();
            if (val !== null && val !== undefined && String(val).trim() !== '') {
                hasAnyValue = true;
            }
            results.push({
                parameter_id: $(this).data('param-id'),
                value: val
            });
        });

        $.ajax({
            url: "{{ route('lab.results.save') }}",
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                _token: $('meta[name="csrf-token"]').attr('content'),
                order_item_id: itemId,
                row_version: version,
                results: results
            }),
            success: function(res) {
                card.data('version', res.new_version);
                if (hasAnyValue) {
                    card.find('.item-status-badge').text('v' + res.new_version + ' · Resulted');
                }
                if (res.delta_alerts && res.delta_alerts.length) {
                    res.delta_alerts.forEach(alert => {
                        if (window.lisToast) window.lisToast('Delta Alert: ' + alert, 'danger');
                    });
                }
                if (onSuccess) onSuccess(res, hasAnyValue);
            },
            error: function(xhr) {
                const err = xhr.responseJSON?.message || 'Error saving results.';
                if (window.lisToast) window.lisToast(err, 'danger');
                else alert(err);
                if (onError) onError(xhr);
            },
            complete: function() {
                if (onComplete) onComplete();
            }
        });
    }

    // Single Test Save Handler
    $(document).on('click', '.btn-save-single-item', function() {
        const btn = $(this);
        if (btn.prop('disabled')) return;
        const card = btn.closest('.item-card');
        const oldHtml = btn.html();

        btn.prop('disabled', true).html('<i class="uil uil-spinner-alt"></i> Saving...');

        saveCardResults(card, function(res, hasValue) {
            if (window.lisToast) {
                const msg = hasValue
                    ? 'Test results saved successfully!'
                    : 'Test saved (no values entered).';
                window.lisToast(msg, 'success');
            }
        }, null, function() {
            btn.prop('disabled', false).html(oldHtml);
        });
    });

    // Save All Results Handler
    $('#btn-save-all-results, #btn-verify-direct').on('click', function() {
        const btn = $(this);
        if (btn.prop('disabled')) return;

        const forward = this.id === 'btn-verify-direct';
        const buttons = $('#btn-save-all-results, #btn-verify-direct, .btn-save-single-item');
        const oldHtml = btn.html();
        const itemCards = $('.item-card');

        if (!itemCards.length) return;

        // Filter cards that have values entered or are already resulted
        const cardsToProcess = [];
        itemCards.each(function() {
            const card = $(this);
            let hasValue = false;
            card.find('.param-input').each(function() {
                if (String($(this).val() || '').trim() !== '') {
                    hasValue = true;
                }
            });
            if (hasValue || forward) {
                cardsToProcess.push(card);
            }
        });

        if (!cardsToProcess.length) {
            if (window.lisToast) {
                window.lisToast('Please enter results for at least one test before saving.', 'warning');
            }
            return;
        }

        buttons.prop('disabled', true);
        btn.html(forward ? '<i class="uil uil-spinner-alt"></i> Saving & Approving...' : '<i class="uil uil-spinner-alt"></i> Saving...');

        let errorOccurred = false;
        let completedCards = 0;

        cardsToProcess.forEach(function(card) {
            saveCardResults(card, function(res) {
                // Individual success
            }, function() {
                errorOccurred = true;
            }, function() {
                completedCards++;
                if (completedCards !== cardsToProcess.length) return;

                if (errorOccurred) {
                    buttons.prop('disabled', false);
                    btn.html(oldHtml);
                    return;
                }

                if (forward) {
                    btn.html('<i class="uil uil-spinner-alt"></i> Approving...');
                    $.ajax({
                        url: "{{ route('lab.orders.approve') }}",
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            order_id: {{ $order->id }}
                        }),
                        success: function(approveRes) {
                            buttons.prop('disabled', false);
                            btn.html(oldHtml);
                            if (window.lisToast) {
                                window.lisToast(approveRes.message || 'Results saved and report approved successfully!', 'success');
                            }
                            setTimeout(() => {
                                window.location.reload();
                            }, 600);
                        },
                        error: function(xhr) {
                            buttons.prop('disabled', false);
                            btn.html(oldHtml);
                            let msg = 'Results were saved, but report approval failed.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                msg = xhr.responseJSON.message;
                            }
                            if (window.lisToast) {
                                window.lisToast(msg, 'danger');
                            } else {
                                alert(msg);
                            }
                        }
                    });
                } else {
                    buttons.prop('disabled', false);
                    btn.html(oldHtml);
                    if (window.lisToast) {
                        window.lisToast('Results saved successfully!', 'success');
                    }
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                }
            });
        });
    });

    // ══════════════════════════════════════════════════════════════
    // 7. Critical Panic Value Acknowledgement
    // ══════════════════════════════════════════════════════════════
    $(document).on('click', '.btn-ack-critical', function() {
        const valId = $(this).data('value-id');
        if (!valId) {
            if (window.lisToast) window.lisToast('Please save results first before acknowledging.', 'danger');
            else alert('Please save results first.');
            return;
        }
        $('#ack-value-id').val(valId);
        $('#ack-person').val('');
        $('#ack-remarks').val('');
        $('#ack-modal').css('display', 'flex');
        $('#ack-person').focus();
    });

    $('#btn-confirm-ack').on('click', function() {
        const valId = $('#ack-value-id').val();
        const person = $('#ack-person').val().trim();
        const remarks = $('#ack-remarks').val().trim();

        if (!person) {
            if (window.lisToast) window.lisToast('Please enter the name of the informed clinician.', 'danger');
            else alert('Please enter clinician name.');
            return;
        }

        $.post("{{ route('lab.results.ack-critical') }}", {
            _token: $('meta[name="csrf-token"]').attr('content'),
            result_value_id: valId,
            informed_person: person,
            remarks: remarks
        }, function(res) {
            if (window.lisToast) window.lisToast('Critical acknowledgement recorded successfully.', 'success');
            $('#ack-modal').hide();
            setTimeout(() => window.location.reload(), 300);
        }).fail(function(xhr) {
            const err = xhr.responseJSON?.message || 'Failed to acknowledge.';
            if (window.lisToast) window.lisToast(err, 'danger');
            else alert(err);
        });
    });

    // ══════════════════════════════════════════════════════════════
    // 8. Live Formulas Evaluation
    // ══════════════════════════════════════════════════════════════
    function recomputeFormulas() {
        const variables = {};
        $('.param-input').each(function() {
            const input = $(this);
            const pid = input.data('param-id');
            const code = input.data('short-code');
            const name = input.data('param-name');
            const val = parseFloat(input.val());

            if (!isNaN(val)) {
                variables['param_' + pid] = val;
                variables['p' + pid] = val;
                if (code) {
                    variables[code] = val;
                    variables[String(code).toLowerCase()] = val;
                    variables[String(code).toUpperCase()] = val;
                }
                if (name) {
                    const cleanName = String(name).replace(/[^a-zA-Z0-9_]/g, '_');
                    variables[cleanName] = val;
                    variables[cleanName.toLowerCase()] = val;
                    variables[cleanName.toUpperCase()] = val;
                }
            }
        });

        $('tr[data-formula!=""]').each(function() {
            const tr = $(this);
            const formula = tr.data('formula');
            const targetInput = tr.find('.formula-target');
            if (formula && targetInput.length) {
                $.ajax({
                    url: "{{ route('lab.api.results.formula-preview') }}",
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        formula: formula,
                        variables: variables,
                        decimals: tr.data('decimals') ?? 2
                    }),
                    success: function(res) {
                        if (res.computed_value !== null && String(targetInput.val()) !== String(res.computed_value)) {
                            targetInput.val(res.computed_value);
                            targetInput.trigger('change');
                            const tCode = targetInput.data('short-code');
                            const tPid = targetInput.data('param-id');
                            const numVal = parseFloat(res.computed_value);
                            if (!isNaN(numVal)) {
                                variables['param_' + tPid] = numVal;
                                variables['p' + tPid] = numVal;
                                if (tCode) {
                                    variables[tCode] = numVal;
                                    variables[String(tCode).toLowerCase()] = numVal;
                                    variables[String(tCode).toUpperCase()] = numVal;
                                }
                            }
                        }
                    }
                });
            }
        });
    }

    // Trigger initial calculation if values are present on load
    setTimeout(recomputeFormulas, 300);

})(jQuery);
</script>
@endpush
