@extends('lab.layouts.app')

@section('title', 'Lab Test & Profile Catalog Master | LIS')
@section('context', 'masters')
@section('page-title', 'Test Catalog Master')
@section('page-description', 'Manage investigations, panels, reference ranges, specimen requirements, and hospital pricing.')

@push('styles')
<style>
    /* Catalog Header Card */
    .catalog-header-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        padding: 10px 14px;
        margin-bottom: 8px;
    }

    /* Filter & Status Tabs */
    .catalog-filter-card {
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

    /* Main Catalog Table */
    .catalog-table-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.04);
        overflow: hidden;
    }

    .table-container {
        max-height: calc(100vh - 220px);
        overflow-y: auto;
    }

    .table-catalog {
        width: 100%;
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    /* Table Header */
    .table-catalog thead th {
        background-color: #252f4a !important;
        color: #ffffff !important;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.4px;
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

    .table-catalog thead th:last-child {
        border-right: none;
    }

    /* Table Body */
    .table-catalog tbody td {
        padding: 5px 8px;
        font-size: 11.5px;
        vertical-align: middle;
        border-bottom: 1px solid #e2e8f0;
        border-right: 1px solid #f1f5f9;
        color: #1e293b;
    }

    .table-catalog tbody td:last-child {
        border-right: none;
    }

    .table-catalog tbody tr:nth-child(even) {
        background-color: #f8fafc;
    }

    .table-catalog tbody tr:hover {
        background-color: #e0f2fe !important;
        transition: background-color 0.12s ease;
    }

    /* Code Pill */
    .test-code-badge {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 10.5px;
        font-weight: 700;
        background-color: #f1f5f9;
        color: #0f172a;
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px solid #cbd5e1;
        white-space: nowrap;
    }

    /* Type Badges */
    .badge-test-single {
        background-color: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        font-weight: 600;
        font-size: 10.5px;
        padding: 2px 7px;
        border-radius: 4px;
        white-space: nowrap;
    }

    .badge-test-group {
        background-color: #faf5ff;
        color: #7e22ce;
        border: 1px solid #e9d5ff;
        font-weight: 700;
        font-size: 10.5px;
        padding: 2px 7px;
        border-radius: 4px;
        white-space: nowrap;
    }

    /* Status Toggle Button */
    .btn-toggle-status {
        font-size: 10.5px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.15s ease;
        white-space: nowrap;
    }

    .btn-status-active {
        background-color: #dcfce7;
        color: #15803d;
        border: 1px solid #86efac;
    }

    .btn-status-active:hover {
        background-color: #bbf7d0;
        color: #14532d;
    }

    .btn-status-inactive {
        background-color: #f1f5f9;
        color: #64748b;
        border: 1px solid #cbd5e1;
    }

    .btn-status-inactive:hover {
        background-color: #e2e8f0;
        color: #334155;
    }

    /* Action Configure Button */
    .btn-configure {
        background-color: #1e293b;
        border: 1px solid #0f172a;
        color: #ffffff !important;
        font-size: 10.5px;
        font-weight: 600;
        padding: 2.5px 8px;
        border-radius: 4px;
        text-decoration: none;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        display: inline-flex;
        align-items: center;
        gap: 3px;
        transition: all 0.15s ease;
    }

    .btn-configure:hover {
        background-color: #0f172a;
        box-shadow: 0 2px 4px rgba(0,0,0,0.25);
        transform: translateY(-1px);
    }

    /* Price Badge & Inline Editor */
    .price-tag {
        font-weight: 700;
        color: #0f172a;
        font-size: 11.5px;
    }

    .inline-price-box {
        position: relative;
    }

    .inline-price-input {
        width: 82px;
        height: 26px;
        font-size: 11.5px;
        font-weight: 700;
        text-align: right;
        padding: 2px 6px;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        background-color: #ffffff;
        color: #0f172a;
        transition: all 0.2s ease;
    }

    .inline-price-input:hover {
        border-color: #94a3b8;
    }

    .inline-price-input:focus {
        border-color: #0284c7;
        background-color: #f0f9ff;
        box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.2);
        outline: none;
    }

    .inline-price-input.price-saving {
        background-color: #f1f5f9;
        color: #94a3b8;
        cursor: wait;
    }

    .inline-price-input.price-saved {
        background-color: #dcfce7 !important;
        border-color: #22c55e !important;
        color: #15803d !important;
        transition: background-color 0.4s ease, border-color 0.4s ease;
    }

    .inline-price-input.price-error {
        background-color: #fee2e2 !important;
        border-color: #ef4444 !important;
        color: #b91c1c !important;
    }

    .btn-save-price {
        height: 26px;
        width: 26px;
        padding: 0 !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        font-size: 13px;
        flex-shrink: 0;
    }

    /* Pagination Styling */
    .pagination {
        margin-bottom: 0;
        gap: 2px;
    }

    .pagination .page-item .page-link {
        font-size: 11.5px;
        padding: 3px 8px;
        color: #334155;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        line-height: 1.4;
        background-color: #ffffff;
    }

    .pagination .page-item .page-link:hover {
        background-color: #f1f5f9;
        color: #0f172a;
        border-color: #94a3b8;
    }

    .pagination .page-item.active .page-link {
        background-color: #0284c7;
        border-color: #0284c7;
        color: #ffffff;
        font-weight: 700;
        box-shadow: 0 1px 2px rgba(2, 132, 199, 0.25);
    }

    .pagination .page-item.disabled .page-link {
        color: #94a3b8;
        background-color: #f8fafc;
        border-color: #e2e8f0;
    }

    .pagination svg,
    nav svg {
        width: 14px !important;
        height: 14px !important;
        max-width: 14px !important;
        max-height: 14px !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-2 py-1">

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- 1. HEADER SECTION & MAIN ACTIONS                               --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="catalog-header-card d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #e0f2fe; color: #0284c7;">
                <i class="uil uil-sliders-v-alt fs-5"></i>
            </div>
            <div>
                <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                    <span>Lab Test & Diagnostic Catalog Master</span>
                    <span class="badge bg-primary text-white" style="font-size: 11px;">{{ number_format($counts['total']) }} Items</span>
                </h6>
                <div class="text-muted" style="font-size: 11px;">Manage investigations, group panels, reference ranges, specimen tubes & hospital pricing.</div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('lab.masters.tests.create') }}" class="btn btn-sm btn-success text-white fw-semibold shadow-sm" style="background-color: #16a34a; border-color: #15803d; font-size: 11.5px; padding: 4px 12px;">
                <i class="uil uil-plus-circle me-1"></i> Add New Test / Group
                <span class="badge bg-white text-success ms-1" style="font-size: 9.5px; vertical-align: middle;">Alt+N</span>
            </a>
            <a href="{{ route('lab.masters.tests.index') }}" class="btn btn-sm btn-outline-secondary" style="font-size: 11.5px; padding: 4px 10px;" title="Reset filters & reload catalog">
                <i class="uil uil-refresh me-1"></i> Refresh
            </a>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- 2. FILTER PILLS & SEARCH BAR                                   --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="catalog-filter-card">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            {{-- Category Filter Pills --}}
            <div class="d-flex align-items-center flex-wrap gap-1">
                <a href="{{ route('lab.masters.tests.index', ['tab' => 'all', 'q' => $search, 'department' => $department]) }}"
                   class="filter-tab-pill {{ $tab === 'all' ? 'active' : '' }}">
                    <i class="uil uil-apps"></i>
                    <span>All Items</span>
                    <span class="pill-badge">{{ $counts['total'] }}</span>
                </a>
                <a href="{{ route('lab.masters.tests.index', ['tab' => 'single', 'q' => $search, 'department' => $department]) }}"
                   class="filter-tab-pill {{ $tab === 'single' ? 'active' : '' }}">
                    <i class="uil uil-flask"></i>
                    <span>Single Tests</span>
                    <span class="pill-badge">{{ $counts['single'] }}</span>
                </a>
                <a href="{{ route('lab.masters.tests.index', ['tab' => 'group', 'q' => $search, 'department' => $department]) }}"
                   class="filter-tab-pill {{ $tab === 'group' ? 'active' : '' }}">
                    <i class="uil uil-layers"></i>
                    <span>Group Panels</span>
                    <span class="pill-badge">{{ $counts['group'] }}</span>
                </a>
                <a href="{{ route('lab.masters.tests.index', ['tab' => 'active', 'q' => $search, 'department' => $department]) }}"
                   class="filter-tab-pill {{ $tab === 'active' ? 'active' : '' }}">
                    <i class="uil uil-check-circle text-success"></i>
                    <span>Active</span>
                    <span class="pill-badge">{{ $counts['active'] }}</span>
                </a>
                <a href="{{ route('lab.masters.tests.index', ['tab' => 'inactive', 'q' => $search, 'department' => $department]) }}"
                   class="filter-tab-pill {{ $tab === 'inactive' ? 'active' : '' }}">
                    <i class="uil uil-times-circle text-secondary"></i>
                    <span>Inactive</span>
                    <span class="pill-badge">{{ $counts['inactive'] }}</span>
                </a>
            </div>

            {{-- Department & Keyword Search Form --}}
            <form method="GET" action="{{ route('lab.masters.tests.index') }}" class="d-flex align-items-center flex-wrap gap-1 m-0">
                <input type="hidden" name="tab" value="{{ $tab }}">

                {{-- Department Dropdown --}}
                @if($departments->isNotEmpty())
                <select name="department" class="form-select form-select-sm" style="height: 30px; font-size: 11px; max-width: 170px;" onchange="this.form.submit()">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept }}" {{ $department === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                    @endforeach
                </select>
                @endif

                {{-- Search Input --}}
                <div class="input-group input-group-sm" style="width: 250px;">
                    <span class="input-group-text bg-light border-end-0" style="height: 30px; font-size: 11px;">
                        <i class="uil uil-search text-muted"></i>
                    </span>
                    <input type="text" name="q" id="catalog-search-input" value="{{ $search }}" class="form-control border-start-0" placeholder="Search test name, code, LOINC... (/)" style="height: 30px; font-size: 11.5px;">
                </div>

                <button type="submit" class="btn btn-sm btn-primary fw-semibold" style="height: 30px; font-size: 11px; padding: 0 10px;">
                    <i class="uil uil-filter me-1"></i>Filter
                </button>

                @if($search || $department)
                    <a href="{{ route('lab.masters.tests.index', ['tab' => $tab]) }}" class="btn btn-sm btn-outline-danger" style="height: 30px; font-size: 11px; padding: 0 8px;" title="Clear search & filters">
                        <i class="uil uil-times me-1"></i>Clear
                    </a>
                @endif
            </form>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- 3. TEST CATALOG TABLE                                          --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="catalog-table-card">
        <div class="table-container">
            <table class="table-catalog" id="test-catalog-table">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">ID</th>
                        <th style="width: 100px;">Code</th>
                        <th>Investigation / Profile Name</th>
                        <th style="width: 130px; text-align: center;">Type</th>
                        <th style="width: 150px;">Department</th>
                        <th style="width: 130px;">Sample & Vial</th>
                        <th style="width: 65px; text-align: center;">TAT</th>
                        <th style="width: 125px; text-align: right;">Price (₹)</th>
                        <th style="width: 85px; text-align: center;">Status</th>
                        <th style="width: 100px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tests as $idx => $t)
                    @php
                        $groupAttrs = is_array($t->group_attributes) ? $t->group_attributes : [];
                        $deptName = $groupAttrs['group'] ?? 'Clinical Lab';
                        $sampleData = $groupAttrs['sample'] ?? [];
                        $sampleType = $sampleData['sample_type'][0] ?? 'Blood';
                        $vial = $sampleData['vial'][0] ?? 'EDTA';
                        $tatVal = $groupAttrs['tat'] ?? 60;
                        
                        // Extract base price from charges
                        $chargeObj = $t->charges->first();
                        $priceVal = $chargeObj ? ($chargeObj->charges ?: $chargeObj->opd) : null;
                    @endphp
                    <tr data-id="{{ $t->id }}">
                        <!-- 1. ID -->
                        <td style="text-align: center; font-weight: bold; color: #64748b;">
                            #{{ $t->id }}
                        </td>

                        <!-- 2. Short Code -->
                        <td>
                            <span class="test-code-badge">{{ $t->short_code ?: 'N/A' }}</span>
                        </td>

                        <!-- 3. Investigation / Profile Name -->
                        <td>
                            <div class="fw-bold text-dark" style="font-size: 12px;">{{ $t->service }}</div>
                            @if($t->display_full_name && $t->display_full_name !== $t->service)
                                <div class="text-muted" style="font-size: 10.5px;">{{ $t->display_full_name }}</div>
                            @endif
                            @if($t->loinc_code)
                                <span class="badge bg-light text-secondary border mt-1" style="font-size: 9.5px; font-weight: normal;">
                                    LOINC: <code>{{ $t->loinc_code }}</code>
                                </span>
                            @endif
                        </td>

                        <!-- 4. Type -->
                        <td style="text-align: center;">
                            @if($t->is_group)
                                <span class="badge-test-group" title="Group Profile composed of multiple component tests">
                                    <i class="uil uil-layers me-1"></i>Profile ({{ $t->group_tests_count }} tests)
                                </span>
                            @else
                                <span class="badge-test-single" title="Discrete single test">
                                    <i class="uil uil-flask me-1"></i>Single Test
                                </span>
                            @endif
                        </td>

                        <!-- 5. Department -->
                        <td>
                            <span class="fw-semibold text-secondary" style="font-size: 11px;">
                                <i class="uil uil-building me-1 text-muted"></i>{{ $deptName }}
                            </span>
                        </td>

                        <!-- 6. Sample & Vial -->
                        <td>
                            <div class="fw-semibold text-dark" style="font-size: 11px;">{{ strtoupper($sampleType) }}</div>
                            <div class="text-muted" style="font-size: 10px;">{{ strtoupper($vial) }} Container</div>
                        </td>

                        <!-- 7. TAT -->
                        <td style="text-align: center;">
                            <span class="badge bg-light text-dark border font-monospace" style="font-size: 10.5px;" title="Turnaround Time: {{ $tatVal }} minutes">
                                {{ $tatVal }}m
                            </span>
                        </td>

                        <!-- 8. Price (₹) Inline Editor -->
                        <td style="text-align: right;">
                            <div class="inline-price-box d-flex align-items-center justify-content-end gap-1">
                                <span class="text-muted fw-bold" style="font-size: 11px;">₹</span>
                                <input type="number" 
                                       step="any" 
                                       min="0"
                                       class="form-control form-control-sm inline-price-input" 
                                       data-id="{{ $t->id }}" 
                                       data-name="{{ $t->service }}"
                                       data-orig="{{ $priceVal !== null ? (float)$priceVal : 0 }}"
                                       value="{{ $priceVal !== null ? (float)$priceVal : '' }}" 
                                       placeholder="0.00"
                                       title="Edit charge inline (Press Enter or leave field to save)">
                                <button type="button" 
                                        class="btn btn-sm btn-success btn-save-price d-none" 
                                        data-id="{{ $t->id }}" 
                                        title="Save charge">
                                    <i class="uil uil-check"></i>
                                </button>
                            </div>
                        </td>

                        <!-- 9. Status (Interactive Toggle) -->
                        <td style="text-align: center;">
                            <button type="button" 
                                    class="btn-toggle-status {{ $t->active ? 'btn-status-active' : 'btn-status-inactive' }}" 
                                    data-id="{{ $t->id }}" 
                                    title="Click to toggle Active/Inactive">
                                <i class="uil {{ $t->active ? 'uil-check-circle' : 'uil-times-circle' }} me-1"></i>
                                <span>{{ $t->active ? 'Active' : 'Inactive' }}</span>
                            </button>
                        </td>

                        <!-- 10. Actions -->
                        <td style="text-align: center;">
                            <a href="{{ route('lab.masters.tests.edit', $t->id) }}" class="btn-configure" title="Configure test parameters, formulas, and pricing">
                                <i class="uil uil-sliders-v-alt"></i> Configure
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="text-center py-5 text-muted">
                            <i class="uil uil-folder-open fs-1 text-secondary opacity-50 d-block mb-2"></i>
                            <div class="fw-semibold">No laboratory tests or profiles found.</div>
                            <div class="small">Try adjusting your search criteria or filter tabs.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- 4. FOOTER & PAGINATION                                         --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-2 px-3 border-top bg-light">
            <div class="text-muted" style="font-size: 11.5px;">
                <span class="badge bg-white text-dark border"><kbd>Alt+N</kbd></span> New Test &nbsp;•&nbsp; 
                <span class="badge bg-white text-dark border"><kbd>/</kbd></span> Quick Search &nbsp;•&nbsp; 
                Showing <strong>{{ $tests->firstItem() ?? 0 }}</strong> to <strong>{{ $tests->lastItem() ?? 0 }}</strong> of <strong>{{ $tests->total() }}</strong> records
            </div>
            <div>
                {{ $tests->withQueryString()->links() }}
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
(function($) {
    'use strict';

    const toggleUrlTemplate = "{{ route('lab.masters.tests.toggle', 999999) }}";
    const updateChargeUrlTemplate = "{{ route('lab.masters.tests.charge', 999999) }}";

    $(document).ready(function() {
        // 1. Inline Price Editor
        $(document).on('input', '.inline-price-input', function() {
            const input = $(this);
            const orig = parseFloat(input.data('orig')) || 0;
            const current = parseFloat(input.val()) || 0;
            const saveBtn = input.siblings('.btn-save-price');

            if (current !== orig) {
                saveBtn.removeClass('d-none');
            } else {
                saveBtn.addClass('d-none');
            }
        });

        // Trigger save on Enter key, cancel on Escape
        $(document).on('keydown', '.inline-price-input', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveInlinePrice($(this));
            } else if (e.key === 'Escape') {
                e.preventDefault();
                const orig = $(this).data('orig');
                $(this).val(orig > 0 ? orig : '');
                $(this).siblings('.btn-save-price').addClass('d-none');
                $(this).blur();
            }
        });

        // Trigger save on click of save checkmark button
        $(document).on('click', '.btn-save-price', function(e) {
            e.preventDefault();
            const input = $(this).siblings('.inline-price-input');
            saveInlinePrice(input);
        });

        // Trigger save on blur if changed
        $(document).on('blur', '.inline-price-input', function() {
            const input = $(this);
            const orig = parseFloat(input.data('orig')) || 0;
            const current = parseFloat(input.val()) || 0;
            if (current !== orig && !input.hasClass('price-saving')) {
                saveInlinePrice(input);
            }
        });

        function saveInlinePrice(input) {
            const testId = input.data('id');
            const orig = parseFloat(input.data('orig')) || 0;
            const val = input.val().trim();
            const charge = parseFloat(val) || 0;
            const saveBtn = input.siblings('.btn-save-price');

            if (charge < 0) {
                alert('Charge cannot be negative.');
                input.val(orig > 0 ? orig : '');
                saveBtn.addClass('d-none');
                return;
            }

            if (charge === orig && !saveBtn.is(':visible')) {
                return;
            }

            // Visual saving state
            input.prop('disabled', true).addClass('price-saving');
            saveBtn.prop('disabled', true).html('<i class="uil uil-spinner uil-spin"></i>').removeClass('d-none');

            const url = updateChargeUrlTemplate.replace('999999', testId);

            $.ajax({
                url: url,
                type: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    charge: charge
                },
                success: function(res) {
                    input.data('orig', charge);
                    input.removeClass('price-saving price-error').addClass('price-saved');
                    saveBtn.addClass('d-none').prop('disabled', false).html('<i class="uil uil-check"></i>');

                    if (window.lisToast) {
                        window.lisToast(res.message || `Charge updated to ₹${charge.toFixed(2)}`, 'success');
                    }

                    setTimeout(function() {
                        input.removeClass('price-saved');
                    }, 1200);
                },
                error: function(xhr) {
                    input.removeClass('price-saving').addClass('price-error');
                    saveBtn.prop('disabled', false).html('<i class="uil uil-check"></i>');
                    let msg = 'Failed to update charge.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    if (window.lisToast) {
                        window.lisToast(msg, 'danger');
                    } else {
                        alert(msg);
                    }
                    setTimeout(function() {
                        input.removeClass('price-error');
                    }, 2000);
                },
                complete: function() {
                    input.prop('disabled', false);
                }
            });
        }

        // 2. Quick interactive AJAX status toggle
        $('.btn-toggle-status').on('click', function(e) {
            e.preventDefault();
            const btn = $(this);
            const testId = btn.data('id');
            const spanText = btn.find('span');
            const icon = btn.find('i');

            // Visual loading state
            btn.prop('disabled', true).css('opacity', '0.7');
            icon.removeClass('uil-check-circle uil-times-circle').addClass('uil-spinner uil-spin');

            const url = toggleUrlTemplate.replace('999999', testId);

            $.post(url, {
                _token: $('meta[name="csrf-token"]').attr('content')
            })
            .done(function(res) {
                if (res.success) {
                    if (res.active) {
                        btn.removeClass('btn-status-inactive').addClass('btn-status-active');
                        spanText.text('Active');
                        icon.removeClass('uil-spinner uil-spin uil-times-circle').addClass('uil-check-circle');
                    } else {
                        btn.removeClass('btn-status-active').addClass('btn-status-inactive');
                        spanText.text('Inactive');
                        icon.removeClass('uil-spinner uil-spin uil-check-circle').addClass('uil-times-circle');
                    }
                    if (window.lisToast) {
                        window.lisToast(res.message || 'Status updated successfully.', 'success');
                    }
                }
            })
            .fail(function() {
                alert('Failed to update status. Please try again.');
                icon.removeClass('uil-spinner uil-spin').addClass(btn.hasClass('btn-status-active') ? 'uil-check-circle' : 'uil-times-circle');
            })
            .always(function() {
                btn.prop('disabled', false).css('opacity', '1');
            });
        });

        // 3. Keyboard shortcut: Alt+N for new test
        $(document).on('keydown', function(e) {
            if (e.altKey && (e.key === 'n' || e.key === 'N')) {
                e.preventDefault();
                window.location.href = "{{ route('lab.masters.tests.create') }}";
            }
            // Press '/' to jump to search input
            if (e.key === '/' && !$(e.target).is('input, textarea, select')) {
                e.preventDefault();
                $('#catalog-search-input').focus().select();
            }
        });
    });
})(jQuery);
</script>
@endpush
