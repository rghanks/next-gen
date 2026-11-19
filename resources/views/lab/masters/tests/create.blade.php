@extends('lab.layouts.app')

@section('title', 'Create New Lab Test / Profile | LIS Master')
@section('context', 'masters')
@section('page-title', 'Create Lab Test Master')
@section('page-description', 'Add a new investigation or group profile into the diagnostic test catalog.')

@push('styles')
<style>
    .create-header-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        padding: 10px 14px;
        margin-bottom: 8px;
    }

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

    {{-- Header --}}
    <div class="create-header-card d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('lab.masters.tests.index') }}" class="btn btn-sm btn-outline-secondary" style="font-size: 11px; padding: 3px 9px;">
                <i class="uil uil-arrow-left me-1"></i> Back to Catalog
            </a>
            <div class="vr mx-1" style="height: 20px;"></div>
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <span>Add New Diagnostic Test / Group Profile</span>
                <span class="badge bg-primary text-white" style="font-size: 10.5px;">New Catalog Entry</span>
            </h6>
        </div>
    </div>

    <form method="POST" action="{{ route('lab.masters.tests.store') }}" id="create-test-form">
        @csrf

        <div class="row g-2">
            {{-- Left Column (8 cols) --}}
            <div class="col-lg-8">
                {{-- 1. General Identification & Categorization --}}
                <div class="master-section-card">
                    <div class="section-card-title">
                        <span><i class="uil uil-info-circle text-primary me-1"></i> 1. Basic Test Details & Categorization</span>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Service Type <span class="text-danger">*</span></label>
                            <div class="d-flex align-items-center gap-3 mt-1">
                                <label class="form-check-label d-flex align-items-center gap-2 fw-semibold" style="cursor: pointer; font-size: 11.5px;">
                                    <input type="radio" name="is_group" value="0" checked id="radio-single-test" class="form-check-input"> Single Test
                                </label>
                                <label class="form-check-label d-flex align-items-center gap-2 fw-semibold" style="cursor: pointer; font-size: 11.5px;">
                                    <input type="radio" name="is_group" value="1" id="radio-group-test" class="form-check-input"> Group Profile / Panel
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Department / Lab Specialty <span class="text-danger">*</span></label>
                            <select name="department" id="test-dept" class="form-select" required>
                                <option value="Haematology">Haematology / Clinical Pathology</option>
                                <option value="Biochemistry" selected>Biochemistry</option>
                                <option value="Microbiology">Microbiology & Serology</option>
                                <option value="Immunology">Immunology / Endocrinology</option>
                                <option value="Histopathology">Histopathology / Cytology</option>
                                <option value="Clinical Pathology">Clinical Pathology</option>
                                <option value="Molecular Diagnostics">Molecular Diagnostics</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-8">
                            <label class="form-label" for="test-name">Test / Profile Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="test-name" class="form-control fw-bold" required placeholder="e.g. Complete Blood Count (CBC) or Serum Creatinine">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="test-short-code">Short Code <span class="text-danger">*</span></label>
                            <input type="text" name="short_code" id="test-short-code" class="form-control font-monospace text-uppercase" placeholder="e.g. CBC or CREAT">
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-8">
                            <label class="form-label" for="display-name">Display Name on Report</label>
                            <input type="text" name="display_name" id="display-name" class="form-control" placeholder="Official full title printed on patient report">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="loinc-code">LOINC Universal Code</label>
                            <input type="text" name="loinc_code" id="loinc-code" class="form-control font-monospace" placeholder="e.g. 58410-2">
                        </div>
                    </div>
                </div>

                {{-- 2. Specimen & Collection Requirements --}}
                <div class="master-section-card">
                    <div class="section-card-title">
                        <span><i class="uil uil-flask text-primary me-1"></i> 2. Specimen Collection & Tube Container</span>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label">Specimen Type <span class="text-danger">*</span></label>
                            <select name="specimen_type" class="form-select">
                                <option value="BLOOD">Whole Blood</option>
                                <option value="SERUM" selected>Serum</option>
                                <option value="PLASMA">Plasma</option>
                                <option value="URINE">Urine</option>
                                <option value="CSF">CSF (Cerebrospinal Fluid)</option>
                                <option value="SPUTUM">Sputum</option>
                                <option value="STOOL">Stool</option>
                                <option value="SWAB">Swab</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Container / Tube <span class="text-danger">*</span></label>
                            <select name="container" class="form-select">
                                <option value="EDTA">EDTA (Lavender Top)</option>
                                <option value="PLAIN_GEL" selected>Plain / SST Gel (Yellow/Red Top)</option>
                                <option value="FLUORIDE">Sodium Fluoride (Grey Top)</option>
                                <option value="CITRATE">Sodium Citrate (Blue Top)</option>
                                <option value="HEPARIN">Lithium Heparin (Green Top)</option>
                                <option value="CONTAINER">Container / Sterile Cup</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Required Sample Volume</label>
                            <div class="input-group">
                                <input type="number" step="0.1" name="sample_quantity" value="2.0" class="form-control">
                                <select name="sample_unit" class="form-select" style="max-width: 75px;">
                                    <option value="mL">mL</option>
                                    <option value="uL">µL</option>
                                    <option value="drops">drops</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-8">
                            <label class="form-label">Storage & Pre-analytical Instruction</label>
                            <input type="text" name="storage_instruction" value="Refrigerate 2-8°C. Do not freeze." class="form-control">
                        </div>

                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check mb-1">
                                <input type="checkbox" name="is_outsource" value="1" id="is-outsource" class="form-check-input">
                                <label for="is-outsource" class="form-check-label fw-semibold" style="font-size: 11px; cursor: pointer;">
                                    Send to Reference Lab (Outsource)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 3. Group Component Inclusion (for profiles) --}}
                <div class="master-section-card" id="group-components-card" style="display: none;">
                    <div class="section-card-title">
                        <div>
                            <span><i class="uil uil-layers text-primary me-1"></i> 3. Included Component Tests (Profile Composition)</span>
                            <div class="text-muted fw-normal" style="font-size: 11px;">Select child tests that make up this profile</div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <input type="text" id="component-search-box" class="form-control" placeholder="Type test name or code and press Enter to include in profile...">
                    </div>

                    <div id="selected-components-container" class="border rounded p-2 bg-light" style="min-height: 80px; max-height: 220px; overflow-y: auto;">
                        <div id="no-components-hint" class="text-muted small text-center py-3">
                            <i class="uil uil-layers-alt fs-3 opacity-50 d-block mb-1"></i>
                            No tests selected yet. Type above and press Enter to add child investigations.
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Column (4 cols) --}}
            <div class="col-lg-4">
                {{-- Turnaround Time --}}
                <div class="master-section-card">
                    <div class="section-card-title">
                        <span><i class="uil uil-stopwatch text-primary me-1"></i> Turnaround Time (TAT)</span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">TAT Target (Minutes)</label>
                        <div class="input-group">
                            <input type="number" name="tat" value="60" class="form-control fw-bold">
                            <span class="input-group-text bg-light text-muted" style="font-size: 11px;">Mins</span>
                        </div>
                        <div class="text-muted small mt-1" style="font-size: 10.5px;">Routine target for result availability</div>
                    </div>

                    <div>
                        <label class="form-label">Report Delivery (Hours)</label>
                        <div class="input-group">
                            <input type="number" name="report_time" value="24" class="form-control">
                            <span class="input-group-text bg-light text-muted" style="font-size: 11px;">Hours</span>
                        </div>
                        <div class="text-muted small mt-1" style="font-size: 10.5px;">Time promised to patient</div>
                    </div>
                </div>

                {{-- Pricing Setup --}}
                <div class="master-section-card">
                    <div class="section-card-title">
                        <span><i class="uil uil-bill text-primary me-1"></i> Initial Pricing Setup</span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">General Base Charge (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold">₹</span>
                            <input type="number" step="0.01" name="base_charge" value="200.00" class="form-control fw-bold text-end" required>
                        </div>
                        <div class="text-muted small mt-1" style="font-size: 10.5px;">Standard walk-in & cash price</div>
                    </div>

                    <div>
                        <label class="form-label">Estimated Internal Cost (₹)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">₹</span>
                            <input type="number" step="0.01" name="item_cost" value="45.00" class="form-control text-end">
                        </div>
                        <div class="text-muted small mt-1" style="font-size: 10.5px;">Reagent, tube & technician cost</div>
                    </div>
                </div>

                {{-- Submit Button --}}
                <div class="master-section-card text-center">
                    <button type="submit" class="btn btn-success fw-bold w-100 shadow-sm" style="background-color: #16a34a; border-color: #15803d; font-size: 12px; padding: 8px 14px;">
                        <i class="uil uil-check-circle me-1"></i> Save Master & Configure Parameters →
                    </button>
                    <div class="text-muted small mt-2" style="font-size: 11px;">
                        After saving, you can configure detailed reference ranges and pricing matrix.
                    </div>
                </div>
            </div>
        </div>
    </form>

</div>
@endsection

@push('scripts')
<script>
(function($) {
    'use strict';

    $(document).ready(function() {
        // Toggle Group components section
        $('input[name="is_group"]').on('change', function() {
            if ($(this).val() === '1') {
                $('#group-components-card').slideDown(150);
            } else {
                $('#group-components-card').slideUp(150);
            }
        });

        // Auto generate short code if empty
        $('#test-name').on('blur', function() {
            const val = $(this).val().trim();
            const shortCodeInput = $('#test-short-code');
            if (val && !shortCodeInput.val()) {
                const code = val.replace(/[^a-zA-Z0-9]/g, '').substring(0, 8).toUpperCase();
                shortCodeInput.val(code);
            }
        });

        // Group component selection from preloaded allTests
        const allTests = @json($allTests);
        const selectedIds = new Set();

        window.addComponent = function(id, name, code) {
            if (selectedIds.has(id)) return;
            selectedIds.add(id);
            $('#no-components-hint').hide();

            const safeName = $('<div>').text(name).html();
            const safeCode = $('<div>').text(code || 'N/A').html();

            const badge = $(`
                <div class="d-flex justify-content-between align-items-center bg-white border rounded p-2 mb-1">
                    <input type="hidden" name="component_ids[]" value="${id}">
                    <span style="font-size: 11.5px;">
                        <strong>${safeName}</strong>
                        <span class="badge bg-light text-dark border font-monospace ms-1">${safeCode}</span>
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size: 11px;" onclick="$(this).parent().remove(); selectedIds.delete(${id}); if(selectedIds.size === 0) $('#no-components-hint').show();">
                        <i class="uil uil-times"></i>
                    </button>
                </div>
            `);
            $('#selected-components-container').append(badge);
        };

        $('#component-search-box').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = $(this).val().toLowerCase().trim();
                if (!query) return;

                const match = allTests.find(t => !selectedIds.has(t.id) && (t.service.toLowerCase().includes(query) || (t.short_code && t.short_code.toLowerCase().includes(query))));
                if (match) {
                    addComponent(match.id, match.service, match.short_code);
                    $(this).val('');
                } else {
                    alert('No matching available test found for: ' + query);
                }
            }
        });
    });
})(jQuery);
</script>
@endpush
