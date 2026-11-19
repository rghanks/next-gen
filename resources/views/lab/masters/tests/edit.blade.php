@extends('lab.layouts.app')

@section('title', 'Configure ' . $test->service . ' | LIS Master')
@section('context', 'masters')
@section('page-title', 'Configure Test Master')
@section('page-description', 'Manage investigation details, group components, parameters, reference ranges, and pricing.')

@php
    $groupAttrs = is_array($test->group_attributes) ? $test->group_attributes : [];
    $sampleData = $groupAttrs['sample'] ?? [];
    $sampleType = $sampleData['sample_type'][0] ?? 'SERUM';
    $vial = $sampleData['vial'][0] ?? 'PLAIN_GEL';
    $qty = $sampleData['quantity'][0] ?? 2.0;
    $sUnit = $sampleData['unit'][0] ?? 'mL';
    $storage = $groupAttrs['sample_storage_instruction'] ?? 'Refrigerate 2-8°C';
    $tat = $groupAttrs['tat'] ?? 60;
    $reportTime = $groupAttrs['report_time'] ?? 24;
    $dept = $groupAttrs['group'] ?? 'Haematology';

    $primaryParam = $parameters->first();
    $inputAttrs = is_array($test->input_attributes) ? $test->input_attributes : [];
    $inputType = $primaryParam?->input_type ?? ($inputAttrs['input_type'] ?? 'numeric');
@endphp

@push('styles')
<style>
    /* Header Card */
    .edit-header-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        padding: 10px 14px;
        margin-bottom: 8px;
    }

    /* Tabs Bar */
    .edit-tabs-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        padding: 6px 10px;
        margin-bottom: 8px;
    }

    .master-nav-tab {
        font-size: 11.5px;
        font-weight: 600;
        padding: 5px 14px;
        border-radius: 5px;
        border: 1px solid #cbd5e1;
        background-color: #ffffff;
        color: #334155;
        cursor: pointer;
        transition: all 0.15s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .master-nav-tab:hover {
        background-color: #f1f5f9;
        color: #0f172a;
        border-color: #94a3b8;
    }

    .master-nav-tab.active {
        background-color: #0284c7 !important;
        border-color: #0369a1 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 4px rgba(2,132,199,0.25);
    }

    /* Inner Section Cards */
    .master-section-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        padding: 14px;
        margin-bottom: 12px;
    }

    .section-card-title {
        font-size: 12.5px;
        font-weight: 700;
        color: #0f172a;
        border-bottom: 1.5px solid #e2e8f0;
        padding-bottom: 8px;
        margin-bottom: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Tables */
    .table-master {
        width: 100%;
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        overflow: hidden;
    }

    .table-master thead th {
        background-color: #252f4a !important;
        color: #ffffff !important;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.3px;
        text-transform: uppercase;
        padding: 6px 10px;
        vertical-align: middle;
        border-bottom: 2px solid #1e293b;
        border-right: 1px solid rgba(255, 255, 255, 0.12);
        white-space: nowrap;
    }

    .table-master thead th:last-child {
        border-right: none;
    }

    .table-master tbody td {
        padding: 6px 10px;
        font-size: 11.5px;
        vertical-align: middle;
        border-bottom: 1px solid #e2e8f0;
        border-right: 1px solid #f1f5f9;
        color: #1e293b;
    }

    .table-master tbody td:last-child {
        border-right: none;
    }

    .table-master tbody tr:nth-child(even) {
        background-color: #f8fafc;
    }

    .table-master tbody tr:hover {
        background-color: #e0f2fe !important;
    }

    /* Status Toggle Button */
    .btn-toggle-status {
        font-size: 11px;
        font-weight: 700;
        padding: 3px 10px;
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

    .form-label {
        font-size: 11px;
        font-weight: 600;
        color: #334155;
        margin-bottom: 4px;
    }

    .form-control, .form-select {
        font-size: 11.5px;
        border-color: #cbd5e1;
    }

    .form-control:focus, .form-select:focus {
        border-color: #0284c7;
        box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.15);
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-2 py-1">

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- 1. TOP HEADER & TEST BADGE                                     --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="edit-header-card d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('lab.masters.tests.index') }}" class="btn btn-sm btn-outline-secondary" style="font-size: 11px; padding: 3px 9px;">
                <i class="uil uil-arrow-left me-1"></i> Back to Catalog
            </a>
            <div class="vr mx-1" style="height: 20px;"></div>
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <span>{{ $test->service }}</span>
                <span class="badge bg-light text-dark border font-monospace" style="font-size: 11px;">
                    {{ $test->short_code ?: 'NO-CODE' }}
                </span>
                @if($test->is_group)
                    <span class="badge" style="background-color: #faf5ff; color: #7e22ce; border: 1px solid #e9d5ff; font-size: 10.5px;">
                        <i class="uil uil-layers me-1"></i>Group Profile / Panel ({{ $groupComponents->count() }} tests)
                    </span>
                @else
                    <span class="badge" style="background-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 10.5px;">
                        <i class="uil uil-flask me-1"></i>Single Investigation Test
                    </span>
                @endif
            </h6>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button type="button" 
                    class="btn-toggle-status {{ $test->active ? 'btn-status-active' : 'btn-status-inactive' }}" 
                    data-id="{{ $test->id }}" 
                    title="Click to toggle Active/Inactive">
                <i class="uil {{ $test->active ? 'uil-check-circle' : 'uil-times-circle' }} me-1"></i>
                <span>Status: {{ $test->active ? 'Active' : 'Inactive' }}</span>
            </button>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- 2. TAB NAVIGATION                                              --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="edit-tabs-card">
        <div class="d-flex align-items-center flex-wrap gap-1">
            <button type="button" class="master-nav-tab active" data-tab="tab-details">
                <i class="uil uil-file-medical-alt"></i>
                <span>1. Basic Details & Specimen</span>
            </button>

            @if($test->is_group)
            <button type="button" class="master-nav-tab" data-tab="tab-components">
                <i class="uil uil-layers"></i>
                <span>2. Profile Components ({{ $groupComponents->count() }})</span>
            </button>
            @else
            <button type="button" class="master-nav-tab" data-tab="tab-parameters">
                <i class="uil uil-sliders-v-alt"></i>
                <span>2. Parameters & Formulas</span>
            </button>
            <button type="button" class="master-nav-tab" data-tab="tab-ranges">
                <i class="uil uil-chart-bar"></i>
                <span>3. Biological Reference Ranges</span>
            </button>
            @endif

            <button type="button" class="master-nav-tab" data-tab="tab-pricing">
                <i class="uil uil-bill"></i>
                <span>{{ $test->is_group ? '3' : '4' }}. Pricing & Payer Matrix</span>
            </button>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- TAB 1: BASIC TEST DETAILS & SPECIMEN REQUIREMENTS               --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div id="tab-details" class="master-tab-pane">
        <form method="POST" action="{{ route('lab.masters.tests.update', $test->id) }}" id="form-update-details">
            @csrf
            {{-- Preserve is_group flag --}}
            <input type="hidden" name="is_group" value="{{ $test->is_group ? 1 : 0 }}">

            <div class="row g-2">
                {{-- Left 8 Cols --}}
                <div class="col-lg-8">
                    {{-- 1. General Identification --}}
                    <div class="master-section-card">
                        <div class="section-card-title">
                            <span><i class="uil uil-info-circle text-primary me-1"></i> General Identification & Universal Codes</span>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-md-8">
                                <label class="form-label">Investigation / Profile Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" value="{{ $test->service }}" class="form-control fw-bold" required placeholder="e.g. Complete Blood Count (CBC)">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Short Code <span class="text-danger">*</span></label>
                                <input type="text" name="short_code" value="{{ $test->short_code }}" class="form-control font-monospace text-uppercase" placeholder="e.g. CBC">
                            </div>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-md-7">
                                <label class="form-label">Display Name on Printed Report</label>
                                <input type="text" name="display_name" value="{{ $test->display_full_name }}" class="form-control" placeholder="Official full name on patient PDF report">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Department / Lab Section <span class="text-danger">*</span></label>
                                <select name="department" class="form-select">
                                    @php
                                        $standardDepts = [
                                            'HAEMATOLOGY' => 'Haematology / Clinical Pathology',
                                            'BIOCHEMISTRY' => 'Biochemistry',
                                            'CLINICAL BIOCHEMISTRY' => 'Clinical Biochemistry',
                                            'CLINICAL PATHOLOGY' => 'Clinical Pathology',
                                            'SEROLOGY' => 'Serology & Immunology',
                                            'MICROBIOLOGY' => 'Microbiology',
                                            'HISTOPATHOLOGY' => 'Histopathology & Cytology',
                                            'URINE EXAMINATION' => 'Urine Examination',
                                            'Blood Test' => 'Blood Test',
                                            'Others' => 'Other Diagnostic Category',
                                        ];
                                    @endphp
                                    @foreach($standardDepts as $val => $lbl)
                                        <option value="{{ $val }}" {{ strtoupper($dept) === strtoupper($val) ? 'selected' : '' }}>{{ $lbl }}</option>
                                    @endforeach
                                    @if(!array_key_exists($dept, $standardDepts))
                                        <option value="{{ $dept }}" selected>{{ $dept }}</option>
                                    @endif
                                </select>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">LOINC Universal Code</label>
                                <input type="text" name="loinc_code" value="{{ $test->loinc_code }}" class="form-control font-monospace" placeholder="e.g. 58410-2">
                            </div>
                        </div>
                    </div>

                    {{-- 2. Specimen & Pre-analytical Requirements --}}
                    <div class="master-section-card">
                        <div class="section-card-title">
                            <span><i class="uil uil-flask text-primary me-1"></i> Specimen & Pre-analytical Requirements</span>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-md-4">
                                <label class="form-label">Specimen Type</label>
                                <select name="specimen_type" class="form-select">
                                    @foreach(['BLOOD', 'SERUM', 'PLASMA', 'URINE', 'CSF', 'SPUTUM', 'STOOL', 'SWAB', 'SYNOVIAL_FLUID'] as $st)
                                        <option value="{{ $st }}" {{ strtoupper($sampleType) === $st ? 'selected' : '' }}>{{ $st }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Vial / Tube Container</label>
                                <select name="container" class="form-select">
                                    @foreach(['EDTA', 'PLAIN_GEL', 'CONTAINER', 'FLUORIDE', 'CITRATE', 'HEPARIN', 'STERILE_CUP'] as $vt)
                                        <option value="{{ $vt }}" {{ strtoupper($vial) === $vt ? 'selected' : '' }}>{{ $vt }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Sample Quantity</label>
                                <div class="input-group">
                                    <input type="number" step="0.1" name="sample_quantity" value="{{ $qty }}" class="form-control">
                                    <input type="text" name="sample_unit" value="{{ $sUnit }}" class="form-control" style="max-width: 75px;" placeholder="Unit">
                                </div>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label">Sample Storage & Pre-Analytical Instructions</label>
                                <input type="text" name="storage_instruction" value="{{ $storage }}" class="form-control" placeholder="e.g. Refrigerate at 2-8°C, Protect from light">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right 4 Cols --}}
                <div class="col-lg-4">
                    {{-- 3. Turnaround Targets --}}
                    <div class="master-section-card">
                        <div class="section-card-title">
                            <span><i class="uil uil-stopwatch text-primary me-1"></i> Turnaround Targets</span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Turnaround Time (Minutes)</label>
                            <div class="input-group">
                                <input type="number" name="tat" value="{{ $tat }}" class="form-control fw-bold">
                                <span class="input-group-text bg-light text-muted" style="font-size: 11px;">Mins</span>
                            </div>
                            <div class="text-muted small mt-1" style="font-size: 10.5px;">Target processing time from sample collection.</div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Report Delivery (Hours)</label>
                            <div class="input-group">
                                <input type="number" name="report_time" value="{{ $reportTime }}" class="form-control">
                                <span class="input-group-text bg-light text-muted" style="font-size: 11px;">Hours</span>
                            </div>
                            <div class="text-muted small mt-1" style="font-size: 10.5px;">Standard expected patient delivery window.</div>
                        </div>
                    </div>

                    {{-- Save Action Card --}}
                    <div class="master-section-card text-center">
                        <button type="submit" class="btn btn-primary fw-bold w-100 shadow-sm" style="background-color: #0284c7; border-color: #0369a1; padding: 7px 14px; font-size: 12px;">
                            <i class="uil uil-check-circle me-1"></i> Save Test Details
                        </button>
                        <div class="text-muted small mt-2" style="font-size: 11px;">
                            Saves basic identification, department, and specimen requirements.
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    @if($test->is_group)
    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- TAB 2: GROUP PROFILE COMPONENTS                                --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div id="tab-components" class="master-tab-pane" style="display: none;">
        <form method="POST" action="{{ route('lab.masters.tests.update', $test->id) }}" id="form-update-components">
            @csrf
            {{-- Hidden attributes to preserve test identification & specimen settings --}}
            <input type="hidden" name="name" value="{{ $test->service }}">
            <input type="hidden" name="short_code" value="{{ $test->short_code }}">
            <input type="hidden" name="display_name" value="{{ $test->display_full_name }}">
            <input type="hidden" name="department" value="{{ $dept }}">
            <input type="hidden" name="tat" value="{{ $tat }}">
            <input type="hidden" name="report_time" value="{{ $reportTime }}">
            <input type="hidden" name="loinc_code" value="{{ $test->loinc_code }}">
            <input type="hidden" name="specimen_type" value="{{ $sampleType }}">
            <input type="hidden" name="container" value="{{ $vial }}">
            <input type="hidden" name="sample_quantity" value="{{ $qty }}">
            <input type="hidden" name="sample_unit" value="{{ $sUnit }}">
            <input type="hidden" name="storage_instruction" value="{{ $storage }}">
            <input type="hidden" name="is_group" value="1">

            <div class="master-section-card">
                <div class="section-card-title">
                    <div>
                        <span><i class="uil uil-layers text-primary me-1"></i> Manage Component Tests in Profile</span>
                        <div class="text-muted fw-normal" style="font-size: 11px; margin-top: 2px;">
                            These child investigations are automatically added to the order whenever <strong>{{ $test->service }}</strong> is billed.
                        </div>
                    </div>
                    <span class="badge bg-primary text-white" id="badge-components-count">{{ $groupComponents->count() }} Components</span>
                </div>

                {{-- Add Component Bar --}}
                <div class="d-flex align-items-center gap-2 mb-3 p-2 bg-light rounded border">
                    <div style="flex-grow: 1; max-width: 450px;">
                        <select id="select-add-child-test" class="form-select form-select-sm" style="font-size: 11.5px;">
                            <option value="">-- Search & Select Single Test to Add --</option>
                            @foreach($allAvailableTests as $at)
                                @if(!in_array($at->id, $groupComponentIds))
                                    <option value="{{ $at->id }}" data-name="{{ $at->service }}" data-code="{{ $at->short_code }}">
                                        {{ $at->service }} ({{ $at->short_code ?: 'N/A' }})
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <button type="button" id="btn-add-child-test" class="btn btn-sm btn-success fw-semibold" style="background-color: #16a34a; border-color: #15803d; font-size: 11.5px;">
                        <i class="uil uil-plus-circle me-1"></i> Add to Profile
                    </button>
                </div>

                {{-- Components Table --}}
                <table class="table-master" id="table-group-components">
                    <thead>
                        <tr>
                            <th style="width: 60px; text-align: center;"># Seq</th>
                            <th style="width: 120px;">Short Code</th>
                            <th>Component Investigation Name</th>
                            <th style="width: 90px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="components-tbody">
                        @forelse($groupComponents as $idx => $gc)
                        <tr>
                            <td style="text-align: center;">
                                <input type="hidden" name="component_ids[]" value="{{ $gc->id }}">
                                <span class="badge bg-light text-dark border font-monospace">#{{ $idx + 1 }}</span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace">{{ $gc->short_code ?: 'N/A' }}</span>
                            </td>
                            <td>
                                <strong>{{ $gc->service }}</strong>
                                @if($gc->display_full_name && $gc->display_full_name !== $gc->service)
                                    <div class="text-muted small" style="font-size: 10px;">{{ $gc->display_full_name }}</div>
                                @endif
                            </td>
                            <td style="text-align: center;">
                                <button type="button" class="btn btn-sm btn-outline-danger" style="font-size: 10.5px; padding: 1.5px 7px;" onclick="removeComponentRow(this, {{ $gc->id }}, '{{ addslashes($gc->service) }}', '{{ $gc->short_code }}');" title="Remove test from profile">
                                    <i class="uil uil-trash-alt me-1"></i>Remove
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr id="tr-no-components">
                            <td colspan="4" class="text-center py-4 text-muted">
                                <i class="uil uil-layers-alt fs-2 text-secondary opacity-50 d-block mb-1"></i>
                                <div class="fw-semibold">No component tests added to this profile yet.</div>
                                <div class="small">Use the selector above to add investigations to this panel.</div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-3 text-end">
                    <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm" style="background-color: #0284c7; border-color: #0369a1; font-size: 12px;">
                        <i class="uil uil-check-circle me-1"></i> Save Profile Components
                    </button>
                </div>
            </div>
        </form>
    </div>
    @else
    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- TAB 2: PARAMETERS & FORMULAS (FOR SINGLE TESTS)                --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div id="tab-parameters" class="master-tab-pane" style="display: none;">
        <form id="form-save-parameters">
            @csrf
            <div class="master-section-card">
                <div class="section-card-title">
                    <span><i class="uil uil-sliders-v-alt text-primary me-1"></i> Discrete Parameter & Calculation Configuration</span>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label">Parameter Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" value="{{ $primaryParam?->name ?? $test->service }}" class="form-control fw-bold" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Short Code (for Formulas) <span class="text-danger">*</span></label>
                        <input type="text" name="short_code" id="param-short-code" value="{{ $primaryParam?->short_code ?? $test->short_code }}" class="form-control font-monospace text-uppercase" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Unit of Measure</label>
                        <input type="text" name="unit" value="{{ $primaryParam?->unit }}" placeholder="e.g. g/dL, mg/dL, %" class="form-control">
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Input Element Type</label>
                        <select name="input_type" id="param-input-type" class="form-select fw-semibold">
                            <option value="numeric" {{ $inputType === 'numeric' ? 'selected' : '' }}>Numeric (Value with Reference Range)</option>
                            <option value="text" {{ $inputType === 'text' ? 'selected' : '' }}>Text (Free-text / Qualitative)</option>
                            <option value="options" {{ $inputType === 'options' ? 'selected' : '' }}>Options / Select (Dropdown)</option>
                            <option value="formula" {{ $inputType === 'formula' ? 'selected' : '' }}>Formula (Computed fx)</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Analytical Method</label>
                        <input type="text" name="method" value="{{ $primaryParam?->method ?? 'Automated Analyzer' }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Decimal Places</label>
                        <input type="number" name="decimal_places" value="{{ $primaryParam?->decimal_places ?? 2 }}" min="0" max="4" class="form-control">
                    </div>
                </div>

                {{-- Formula Builder Box --}}
                <div id="formula-builder-box" class="p-3 mb-3 bg-light rounded border" style="{{ $inputType === 'formula' ? '' : 'display: none;' }}">
                    <label class="form-label fw-bold text-primary">
                        Safe Mathematical Formula Expression:
                    </label>
                    <div class="input-group mb-2">
                        <span class="input-group-text bg-white font-monospace fw-bold">fx =</span>
                        <input type="text" id="formula-input" name="formula" value="{{ $primaryParam?->formula }}" class="form-control font-monospace" placeholder="e.g. [HB] * 3 or [CHOL] - [HDL]">
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-wrap mb-1">
                        <span class="text-muted small me-2" style="font-size: 11px;">Insert operator:</span>
                        @foreach(['+', '-', '*', '/', '(', ')'] as $op)
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-insert-op font-monospace fw-bold px-2 py-0" data-op="{{ $op }}">{{ $op }}</button>
                        @endforeach
                    </div>
                    <div class="text-muted small" style="font-size: 10.5px;">
                        Use square brackets around component short codes (e.g. <code>[CHOL] - [HDL] - ([TRIG] / 5)</code>).
                    </div>
                </div>

                <div class="text-end">
                    <button type="button" id="btn-save-parameters-only" class="btn btn-primary fw-bold px-4 shadow-sm" style="background-color: #0284c7; border-color: #0369a1; font-size: 12px;">
                        <i class="uil uil-check-circle me-1"></i> Save Parameter Settings
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- TAB 3: BIOLOGICAL REFERENCE RANGES (FOR SINGLE TESTS)          --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div id="tab-ranges" class="master-tab-pane" style="display: none;">
        @php
            $ranges = $primaryParam?->referenceRanges ?? collect();
            $brackets = [
                ['name' => 'New Born', 'from' => 0, 'to' => 30, 'str' => '0-1m'],
                ['name' => 'Infant', 'from' => 31, 'to' => 365, 'str' => '1m-1y'],
                ['name' => 'Child', 'from' => 366, 'to' => 4015, 'str' => '1y-11y'],
                ['name' => 'Adult', 'from' => 4016, 'to' => 43800, 'str' => '>11y'],
            ];
        @endphp
        <form id="form-save-ranges">
            @csrf
            <div class="master-section-card">
                <div class="section-card-title">
                    <div>
                        <span><i class="uil uil-chart-bar text-primary me-1"></i> Biological Reference Ranges & Critical Panic Limits</span>
                        <div class="text-muted fw-normal" style="font-size: 11px; margin-top: 2px;">
                            Resolved dynamically by patient age & sex during result entry.
                        </div>
                    </div>
                    <button type="button" id="btn-copy-male-to-female" class="btn btn-sm btn-outline-primary fw-semibold" style="font-size: 11px;">
                        <i class="uil uil-copy me-1"></i> Copy Male Ranges to Female
                    </button>
                </div>

                <div class="row g-3">
                    {{-- Male Matrix --}}
                    <div class="col-lg-6">
                        <div class="p-2 border rounded bg-white">
                            <h6 class="fw-bold text-primary mb-2 pb-1 border-bottom d-flex align-items-center gap-1" style="font-size: 12px;">
                                <span>♂ MALE REFERENCE RANGES</span>
                            </h6>
                            <table class="table-master">
                                <thead>
                                    <tr>
                                        <th>Age Bracket</th>
                                        <th style="width: 75px;">Norm Low</th>
                                        <th style="width: 75px;">Norm High</th>
                                        <th style="width: 75px; color: #f87171 !important;">Panic Low</th>
                                        <th style="width: 75px; color: #f87171 !important;">Panic High</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($brackets as $idx => $b)
                                    @php
                                        $mR = $ranges->first(fn($r) => in_array($r->sex, ['male', 'both']) && $r->age_from_days == $b['from']);
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $b['name'] }}</strong>
                                            <div class="text-muted small" style="font-size: 9.5px;">({{ $b['str'] }})</div>
                                            <input type="hidden" name="ranges[{{ $idx }}][sex]" value="male">
                                            <input type="hidden" name="ranges[{{ $idx }}][group_name]" value="{{ $b['name'] }}">
                                            <input type="hidden" name="ranges[{{ $idx }}][age_from_days]" value="{{ $b['from'] }}">
                                            <input type="hidden" name="ranges[{{ $idx }}][age_to_days]" value="{{ $b['to'] }}">
                                        </td>
                                        <td><input type="number" step="any" name="ranges[{{ $idx }}][low]" value="{{ $mR?->low }}" class="form-control form-control-sm text-center male-low"></td>
                                        <td><input type="number" step="any" name="ranges[{{ $idx }}][high]" value="{{ $mR?->high }}" class="form-control form-control-sm text-center male-high"></td>
                                        <td><input type="number" step="any" name="ranges[{{ $idx }}][critical_low]" value="{{ $mR?->critical_low }}" class="form-control form-control-sm text-center border-danger male-crit-low" style="background-color: #fef2f2;"></td>
                                        <td><input type="number" step="any" name="ranges[{{ $idx }}][critical_high]" value="{{ $mR?->critical_high }}" class="form-control form-control-sm text-center border-danger male-crit-high" style="background-color: #fef2f2;"></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Female Matrix --}}
                    <div class="col-lg-6">
                        <div class="p-2 border rounded bg-white">
                            <h6 class="fw-bold text-danger mb-2 pb-1 border-bottom d-flex align-items-center gap-1" style="font-size: 12px;">
                                <span>♀ FEMALE REFERENCE RANGES</span>
                            </h6>
                            <table class="table-master">
                                <thead>
                                    <tr>
                                        <th>Age Bracket</th>
                                        <th style="width: 75px;">Norm Low</th>
                                        <th style="width: 75px;">Norm High</th>
                                        <th style="width: 75px; color: #f87171 !important;">Panic Low</th>
                                        <th style="width: 75px; color: #f87171 !important;">Panic High</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($brackets as $idx => $b)
                                    @php
                                        $fIdx = $idx + 4;
                                        $fR = $ranges->first(fn($r) => in_array($r->sex, ['female', 'both']) && $r->age_from_days == $b['from']);
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $b['name'] }}</strong>
                                            <div class="text-muted small" style="font-size: 9.5px;">({{ $b['str'] }})</div>
                                            <input type="hidden" name="ranges[{{ $fIdx }}][sex]" value="female">
                                            <input type="hidden" name="ranges[{{ $fIdx }}][group_name]" value="{{ $b['name'] }}">
                                            <input type="hidden" name="ranges[{{ $fIdx }}][age_from_days]" value="{{ $b['from'] }}">
                                            <input type="hidden" name="ranges[{{ $fIdx }}][age_to_days]" value="{{ $b['to'] }}">
                                        </td>
                                        <td><input type="number" step="any" name="ranges[{{ $fIdx }}][low]" value="{{ $fR?->low }}" class="form-control form-control-sm text-center female-low"></td>
                                        <td><input type="number" step="any" name="ranges[{{ $fIdx }}][high]" value="{{ $fR?->high }}" class="form-control form-control-sm text-center female-high"></td>
                                        <td><input type="number" step="any" name="ranges[{{ $fIdx }}][critical_low]" value="{{ $fR?->critical_low }}" class="form-control form-control-sm text-center border-danger female-crit-low" style="background-color: #fef2f2;"></td>
                                        <td><input type="number" step="any" name="ranges[{{ $fIdx }}][critical_high]" value="{{ $fR?->critical_high }}" class="form-control form-control-sm text-center border-danger female-crit-high" style="background-color: #fef2f2;"></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mt-3 text-end">
                    <button type="button" id="btn-save-ranges-all" class="btn btn-primary fw-bold px-4 shadow-sm" style="background-color: #0284c7; border-color: #0369a1; font-size: 12px;">
                        <i class="uil uil-check-circle me-1"></i> Save Reference Ranges & Sync Matrix
                    </button>
                </div>
            </div>
        </form>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- TAB: PRICING & PAYER SCHEDULE MATRIX                           --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div id="tab-pricing" class="master-tab-pane" style="display: none;">
        <form id="form-save-pricing">
            @csrf
            <div class="master-section-card" style="max-width: 650px;">
                <div class="section-card-title">
                    <div>
                        <span><i class="uil uil-bill text-primary me-1"></i> Hospital Charges & Payer Category Rate Matrix</span>
                        <div class="text-muted fw-normal" style="font-size: 11px; margin-top: 2px;">
                            Configure base OPD fee and contracted insurance / corporate price lists.
                        </div>
                    </div>
                </div>

                <table class="table-master mb-3">
                    <thead>
                        <tr>
                            <th>Payer Category / Price Schedule</th>
                            <th style="width: 170px; text-align: right;">Contracted Charge (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <strong>General Walk-in / Cash OPD</strong>
                                <div class="text-muted small" style="font-size: 10px;">Default base charge for walk-in patients</div>
                            </td>
                            <td style="text-align: right;">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light">₹</span>
                                    <input type="number" step="0.01" name="pricing[general]" value="{{ $baseCharge }}" class="form-control fw-bold text-end">
                                </div>
                            </td>
                        </tr>
                        @foreach($priceLists->where('payer_category', '!=', 'general') as $pl)
                        @php
                            $itemCharge = $pl->items->first()?->charge ?? $baseCharge;
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $pl->name }}</strong>
                                <span class="badge bg-light text-secondary border font-monospace ms-1" style="font-size: 9.5px;">{{ $pl->payer_category }}</span>
                            </td>
                            <td style="text-align: right;">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light">₹</span>
                                    <input type="number" step="0.01" name="pricing[{{ $pl->payer_category }}]" value="{{ $itemCharge }}" class="form-control text-end">
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="text-end">
                    <button type="button" id="btn-save-pricing-action" class="btn btn-primary fw-bold px-4 shadow-sm" style="background-color: #0284c7; border-color: #0369a1; font-size: 12px;">
                        <i class="uil uil-check-circle me-1"></i> Save Pricing & Sync Rate Lists
                    </button>
                </div>
            </div>
        </form>
    </div>

</div>
@endsection

@push('scripts')
<script>
(function($) {
    'use strict';

    $(document).ready(function() {
        // 1. Top Tabs Switcher
        $('.master-nav-tab').on('click', function(e) {
            e.preventDefault();
            $('.master-nav-tab').removeClass('active');
            $(this).addClass('active');
            $('.master-tab-pane').hide();
            $('#' + $(this).data('tab')).fadeIn(100);
        });

        // 2. Input Type change triggers formula box toggle
        $('#param-input-type').on('change', function() {
            if ($(this).val() === 'formula') {
                $('#formula-builder-box').slideDown(120);
            } else {
                $('#formula-builder-box').slideUp(120);
            }
        });

        // 3. Insert operator into formula input
        $('.btn-insert-op').on('click', function() {
            const op = $(this).data('op');
            const input = $('#formula-input');
            input.val(input.val() + ' ' + op + ' ');
            input.focus();
        });

        // 4. Copy Male to Female reference ranges
        $('#btn-copy-male-to-female').on('click', function() {
            for (let i = 0; i < 4; i++) {
                const low = $(`input[name="ranges[${i}][low]"]`).val();
                const high = $(`input[name="ranges[${i}][high]"]`).val();
                const critLow = $(`input[name="ranges[${i}][critical_low]"]`).val();
                const critHigh = $(`input[name="ranges[${i}][critical_high]"]`).val();

                const fIdx = i + 4;
                $(`input[name="ranges[${fIdx}][low]"]`).val(low);
                $(`input[name="ranges[${fIdx}][high]"]`).val(high);
                $(`input[name="ranges[${fIdx}][critical_low]"]`).val(critLow);
                $(`input[name="ranges[${fIdx}][critical_high]"]`).val(critHigh);
            }
            if (window.lisToast) {
                window.lisToast('Copied Male ranges to Female successfully.', 'info');
            } else {
                alert('Copied Male ranges to Female successfully.');
            }
        });

        // 5. Add component test to group profile
        $('#btn-add-child-test').on('click', function() {
            const sel = $('#select-add-child-test option:selected');
            const id = sel.val();
            if (!id) return;
            const name = sel.data('name');
            const code = sel.data('code') || 'N/A';

            $('#tr-no-components').remove();
            const count = $('#components-tbody tr').length + 1;
            const safeName = $('<div>').text(name).html();
            const safeCode = $('<div>').text(code).html();

            const row = $(`
                <tr>
                    <td style="text-align: center;">
                        <input type="hidden" name="component_ids[]" value="${id}">
                        <span class="badge bg-light text-dark border font-monospace">#${count}</span>
                    </td>
                    <td><span class="badge bg-light text-dark border font-monospace">${safeCode}</span></td>
                    <td><strong>${safeName}</strong></td>
                    <td style="text-align: center;">
                        <button type="button" class="btn btn-sm btn-outline-danger" style="font-size: 10.5px; padding: 1.5px 7px;" onclick="removeComponentRow(this, ${id}, '${safeName.replace(/'/g, "\\'")}', '${safeCode}');" title="Remove test from profile">
                            <i class="uil uil-trash-alt me-1"></i>Remove
                        </button>
                    </td>
                </tr>
            `);
            $('#components-tbody').append(row);
            sel.remove();
            $('#select-add-child-test').val('');
            updateComponentCountBadge();
        });

        // 6. Global remove component function
        window.removeComponentRow = function(btn, testId, testName, testCode) {
            $(btn).closest('tr').remove();
            // Re-add to dropdown
            $('#select-add-child-test').append(
                $('<option>', {
                    value: testId,
                    text: testName + ' (' + testCode + ')',
                    'data-name': testName,
                    'data-code': testCode
                })
            );
            updateComponentCountBadge();
            if ($('#components-tbody tr').length === 0) {
                $('#components-tbody').html(`
                    <tr id="tr-no-components">
                        <td colspan="4" class="text-center py-4 text-muted">
                            <i class="uil uil-layers-alt fs-2 text-secondary opacity-50 d-block mb-1"></i>
                            <div class="fw-semibold">No component tests added to this profile yet.</div>
                            <div class="small">Use the selector above to add investigations to this panel.</div>
                        </td>
                    </tr>
                `);
            }
        };

        function updateComponentCountBadge() {
            const count = $('#components-tbody input[name="component_ids[]"]').length;
            $('#badge-components-count').text(count + ' Components');
        }

        // 7. Save Parameters only (AJAX)
        $('#btn-save-parameters-only').on('click', function() {
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="uil uil-spinner uil-spin me-1"></i> Saving...');
            const formData = $('#form-save-parameters').serializeArray();
            const payload = {};
            formData.forEach(item => payload[item.name] = item.value);

            $.post("{{ route('lab.masters.tests.parameters', $test->id) }}", payload)
            .done(function(res) {
                if (res.success) {
                    if (window.lisToast) window.lisToast(res.message, 'success');
                    else alert(res.message);
                }
            })
            .fail(function(xhr) {
                alert(xhr.responseJSON?.message || 'Failed to save parameters.');
            })
            .always(function() {
                btn.prop('disabled', false).html('<i class="uil uil-check-circle me-1"></i> Save Parameter Settings');
            });
        });

        // 8. Save Reference Ranges (AJAX)
        $('#btn-save-ranges-all').on('click', function() {
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="uil uil-spinner uil-spin me-1"></i> Saving Ranges...');

            $.ajax({
                url: "{{ route('lab.masters.tests.parameters', $test->id) }}",
                type: "POST",
                data: $('#form-save-parameters').serialize() + '&' + $('#form-save-ranges').serialize(),
                success: function(res) {
                    if (res.success) {
                        if (window.lisToast) window.lisToast(res.message, 'success');
                        else alert(res.message);
                    }
                },
                error: function(xhr) {
                    alert(xhr.responseJSON?.message || 'Error saving reference ranges.');
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="uil uil-check-circle me-1"></i> Save Reference Ranges & Sync Matrix');
                }
            });
        });

        // 9. Save Pricing (AJAX)
        $('#btn-save-pricing-action').on('click', function() {
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="uil uil-spinner uil-spin me-1"></i> Saving Pricing...');

            $.post("{{ route('lab.masters.tests.pricing', $test->id) }}", $('#form-save-pricing').serialize())
            .done(function(res) {
                if (res.success) {
                    if (window.lisToast) window.lisToast(res.message, 'success');
                    else alert(res.message);
                }
            })
            .fail(function(xhr) {
                alert(xhr.responseJSON?.message || 'Error saving pricing.');
            })
            .always(function() {
                btn.prop('disabled', false).html('<i class="uil uil-check-circle me-1"></i> Save Pricing & Sync Rate Lists');
            });
        });

        // 10. Quick Status Toggle
        $('.btn-toggle-status').on('click', function(e) {
            e.preventDefault();
            const btn = $(this);
            const spanText = btn.find('span');
            const icon = btn.find('i');

            btn.prop('disabled', true).css('opacity', '0.7');
            icon.removeClass('uil-check-circle uil-times-circle').addClass('uil-spinner uil-spin');

            $.post(`/lab/masters/tests/{{ $test->id }}/toggle`, {
                _token: $('meta[name="csrf-token"]').attr('content')
            })
            .done(function(res) {
                if (res.success) {
                    if (res.active) {
                        btn.removeClass('btn-status-inactive').addClass('btn-status-active');
                        spanText.text('Status: Active');
                        icon.removeClass('uil-spinner uil-spin uil-times-circle').addClass('uil-check-circle');
                    } else {
                        btn.removeClass('btn-status-active').addClass('btn-status-inactive');
                        spanText.text('Status: Inactive');
                        icon.removeClass('uil-spinner uil-spin uil-check-circle').addClass('uil-times-circle');
                    }
                    if (window.lisToast) window.lisToast(res.message, 'success');
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
    });
})(jQuery);
</script>
@endpush
