@extends('lab.layouts.app')

@section('title', 'Laboratory Settings & Print Configuration | LIS')
@section('context', 'settings')
@section('page-title', 'Laboratory Settings & Branding')
@section('page-description', 'Manage laboratory identity, GST details, address, letterhead header & footer, logo, and digital signatures.')

@push('styles')
<style>
    .settings-header-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        padding: 12px 18px;
        margin-bottom: 12px;
    }

    .settings-nav-tabs .nav-link {
        font-size: 12.5px;
        font-weight: 600;
        color: #475569;
        border: 1px solid transparent;
        padding: 8px 16px;
        border-radius: 6px;
        margin-right: 4px;
        transition: all 0.15s ease;
    }

    .settings-nav-tabs .nav-link:hover {
        background-color: #f1f5f9;
        color: #0f172a;
    }

    .settings-nav-tabs .nav-link.active {
        background-color: #0284c7;
        color: #ffffff !important;
        box-shadow: 0 2px 4px rgba(2, 132, 199, 0.25);
    }

    .settings-section-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        padding: 16px 20px;
        margin-bottom: 14px;
    }

    .settings-section-title {
        font-size: 13px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 14px;
        padding-bottom: 6px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .field-label {
        font-size: 11px;
        font-weight: 600;
        color: #334155;
        margin-bottom: 3px;
    }

    .image-preview-box {
        border: 1px dashed #cbd5e1;
        border-radius: 6px;
        padding: 10px;
        text-align: center;
        background-color: #f8fafc;
        min-height: 90px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .image-preview-box img {
        max-height: 65px;
        max-width: 100%;
        object-fit: contain;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-3 py-2">

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2 px-3 mb-2" role="alert" style="font-size: 12px;">
            <i class="uil uil-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem;"></button>
        </div>
    @endif

    <!-- 1. Header Card -->
    <div class="settings-header-card d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style="font-size: 16px;">
                <i class="uil uil-cog text-primary"></i>
                <span>Laboratory Settings & Letterhead Configuration</span>
            </h5>
            <div class="text-muted" style="font-size: 11px;">
                Customise lab branding, GST number, address, letterhead header & footer banners, logo, and digital signatures.
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="submit" form="formLabSettings" class="btn btn-sm btn-primary fw-bold shadow-sm px-3" style="font-size: 12px;">
                <i class="uil uil-save me-1"></i> Save All Settings
            </button>
        </div>
    </div>

    <!-- 2. Settings Form with Tabs -->
    <form id="formLabSettings" action="{{ route('lab.settings.update') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <!-- Navigation Tabs -->
        <ul class="nav settings-nav-tabs mb-3" id="settingsTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#tabProfile" type="button" role="tab">
                    <i class="uil uil-hospital me-1"></i> Lab Profile & Identity
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="branding-tab" data-bs-toggle="tab" data-bs-target="#tabBranding" type="button" role="tab">
                    <i class="uil uil-image me-1"></i> Header, Footer & Logo
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="signatures-tab" data-bs-toggle="tab" data-bs-target="#tabSignatures" type="button" role="tab">
                    <i class="uil uil-pen me-1"></i> Digital Signatures
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="print-tab" data-bs-toggle="tab" data-bs-target="#tabPrint" type="button" role="tab">
                    <i class="uil uil-print me-1"></i> Report & Receipt Preferences
                </button>
            </li>
        </ul>

        <div class="tab-content" id="settingsTabContent">
            
            <!-- ══════════════════════════════════════════════════════════ -->
            <!-- TAB 1: LAB PROFILE & IDENTITY                              -->
            <!-- ══════════════════════════════════════════════════════════ -->
            <div class="tab-pane fade show active" id="tabProfile" role="tabpanel">
                <div class="settings-section-card">
                    <div class="settings-section-title">
                        <i class="uil uil-building text-primary"></i>
                        <span>Laboratory Identity & Registration Details</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="field-label">Laboratory / Clinic Name <span class="text-danger">*</span></label>
                            <input type="text" name="lab_name" class="form-control form-control-sm fw-bold" value="{{ old('lab_name', $setting->lab_name) }}" required>
                            <small class="text-muted" style="font-size: 10px;">Displayed on top of reports, receipts, and navigation bar.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="field-label">Department / Subtitle Tagline</label>
                            <input type="text" name="tagline" class="form-control form-control-sm" value="{{ old('tagline', $setting->tagline) }}" placeholder="e.g. Department of Pathology & Clinical Diagnostics">
                        </div>
                        <div class="col-md-12">
                            <label class="field-label">Accreditation / Quality Badge Text</label>
                            <input type="text" name="accreditation_text" class="form-control form-control-sm" value="{{ old('accreditation_text', $setting->accreditation_text) }}" placeholder="e.g. Fully Automated Computerized Diagnostic Lab • Accredited Quality Care">
                        </div>
                        <div class="col-md-4">
                            <label class="field-label">GSTIN / Tax ID</label>
                            <input type="text" name="gst_number" class="form-control form-control-sm font-monospace" value="{{ old('gst_number', $setting->gst_number) }}" placeholder="e.g. 06AAAAA0000A1Z5">
                        </div>
                        <div class="col-md-4">
                            <label class="field-label">Registration / License No.</label>
                            <input type="text" name="reg_number" class="form-control form-control-sm" value="{{ old('reg_number', $setting->reg_number) }}" placeholder="e.g. LAB/HAR/2026/884">
                        </div>
                        <div class="col-md-4">
                            <label class="field-label">Website URL</label>
                            <input type="text" name="website" class="form-control form-control-sm" value="{{ old('website', $setting->website) }}" placeholder="https://yourlaboratory.com">
                        </div>
                    </div>
                </div>

                <div class="settings-section-card">
                    <div class="settings-section-title">
                        <i class="uil uil-phone text-success"></i>
                        <span>Contact & Address Information</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="field-label">Mobile / Phone No. <span class="text-danger">*</span></label>
                            <input type="text" name="mobile_no" class="form-control form-control-sm" value="{{ old('mobile_no', $setting->mobile_no) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="field-label">Alternate Phone / Landline</label>
                            <input type="text" name="phone_alt" class="form-control form-control-sm" value="{{ old('phone_alt', $setting->phone_alt) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="field-label">Email Address</label>
                            <input type="email" name="email" class="form-control form-control-sm" value="{{ old('email', $setting->email) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="field-label">Address Line 1 (Street / Building)</label>
                            <input type="text" name="address_line1" class="form-control form-control-sm" value="{{ old('address_line1', $setting->address_line1) }}" placeholder="e.g. Opp. Civil Hospital, Hospital Road">
                        </div>
                        <div class="col-md-6">
                            <label class="field-label">Address Line 2 (City, State, Pincode)</label>
                            <input type="text" name="address_line2" class="form-control form-control-sm" value="{{ old('address_line2', $setting->address_line2) }}" placeholder="e.g. Hisar, Haryana - 125001">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════ -->
            <!-- TAB 2: HEADER, FOOTER & LOGO BRANDING                     -->
            <!-- ══════════════════════════════════════════════════════════ -->
            <div class="tab-pane fade" id="tabBranding" role="tabpanel">
                <div class="settings-section-card">
                    <div class="settings-section-title">
                        <i class="uil uil-image text-primary"></i>
                        <span>Letterhead Mode & Visual Assets</span>
                    </div>

                    <div class="alert alert-info py-2 px-3 mb-3" style="font-size: 11.5px;">
                        <i class="uil uil-info-circle me-1"></i>
                        <strong>Letterhead Option:</strong> You can either use a <strong>Computerized Text Header</strong> (with your uploaded Logo, Lab Name, Address & GST) OR upload a pre-printed graphic <strong>Banner Header Image</strong>.
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" name="use_letterhead_image" id="checkUseLetterhead" value="1" {{ $setting->use_letterhead_image ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold text-dark" for="checkUseLetterhead" style="font-size: 12px;">
                                Use Graphic Letterhead Banner Image instead of text header
                            </label>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- 1. Logo Upload -->
                        <div class="col-md-4">
                            <div class="card p-2 border bg-light h-100">
                                <label class="field-label fw-bold">Laboratory Logo</label>
                                <div class="image-preview-box mb-2">
                                    @if($setting->logo_path && file_exists(public_path($setting->logo_path)))
                                        <img src="{{ asset($setting->logo_path) }}" alt="Logo Preview" id="previewLogo">
                                    @else
                                        <div class="text-muted small" id="noLogoText"><i class="uil uil-image-slash fs-4 d-block"></i>No logo uploaded</div>
                                        <img src="" alt="Logo Preview" id="previewLogo" style="display: none;">
                                    @endif
                                </div>
                                <input type="file" name="logo" class="form-control form-control-sm" accept="image/*" onchange="previewImage(this, '#previewLogo', '#noLogoText')">
                                <small class="text-muted mt-1" style="font-size: 9.5px;">Recommended: Transparent PNG, 300x120px.</small>
                            </div>
                        </div>

                        <!-- 2. Header Banner Image -->
                        <div class="col-md-4">
                            <div class="card p-2 border bg-light h-100">
                                <label class="field-label fw-bold">Header Banner Image</label>
                                <div class="image-preview-box mb-2">
                                    @if($setting->header_image_path && file_exists(public_path($setting->header_image_path)))
                                        <img src="{{ asset($setting->header_image_path) }}" alt="Header Preview" id="previewHeader">
                                    @else
                                        <div class="text-muted small" id="noHeaderText"><i class="uil uil-image-slash fs-4 d-block"></i>No header banner</div>
                                        <img src="" alt="Header Preview" id="previewHeader" style="display: none;">
                                    @endif
                                </div>
                                <input type="file" name="header_image" class="form-control form-control-sm mb-1" accept="image/*" onchange="previewImage(this, '#previewHeader', '#noHeaderText')">
                                <div class="d-flex align-items-center gap-1 mt-1">
                                    <span class="text-muted small" style="font-size: 10px;">Height (px):</span>
                                    <input type="number" name="header_height" class="form-control form-control-sm text-center" value="{{ old('header_height', $setting->header_height) }}" style="width: 75px; height: 26px;">
                                </div>
                            </div>
                        </div>

                        <!-- 3. Footer Banner Image -->
                        <div class="col-md-4">
                            <div class="card p-2 border bg-light h-100">
                                <label class="field-label fw-bold">Footer Banner Image</label>
                                <div class="image-preview-box mb-2">
                                    @if($setting->footer_image_path && file_exists(public_path($setting->footer_image_path)))
                                        <img src="{{ asset($setting->footer_image_path) }}" alt="Footer Preview" id="previewFooter">
                                    @else
                                        <div class="text-muted small" id="noFooterText"><i class="uil uil-image-slash fs-4 d-block"></i>No footer banner</div>
                                        <img src="" alt="Footer Preview" id="previewFooter" style="display: none;">
                                    @endif
                                </div>
                                <input type="file" name="footer_image" class="form-control form-control-sm mb-1" accept="image/*" onchange="previewImage(this, '#previewFooter', '#noFooterText')">
                                <div class="d-flex align-items-center gap-1 mt-1">
                                    <span class="text-muted small" style="font-size: 10px;">Height (px):</span>
                                    <input type="number" name="footer_height" class="form-control form-control-sm text-center" value="{{ old('footer_height', $setting->footer_height) }}" style="width: 75px; height: 26px;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════ -->
            <!-- TAB 3: DIGITAL SIGNATURES                                 -->
            <!-- ══════════════════════════════════════════════════════════ -->
            <div class="tab-pane fade" id="tabSignatures" role="tabpanel">
                <div class="row g-3">
                    <!-- Left: Lab Technician -->
                    <div class="col-md-6">
                        <div class="settings-section-card h-100">
                            <div class="settings-section-title">
                                <i class="uil uil-user-check text-info"></i>
                                <span>Lab Technician / Prepared By</span>
                            </div>
                            <div class="mb-2">
                                <label class="field-label">Technician Name <span class="text-danger">*</span></label>
                                <input type="text" name="technician_name" class="form-control form-control-sm fw-bold" value="{{ old('technician_name', $setting->technician_name) }}" required>
                            </div>
                            <div class="mb-2">
                                <label class="field-label">Designation <span class="text-danger">*</span></label>
                                <input type="text" name="technician_designation" class="form-control form-control-sm" value="{{ old('technician_designation', $setting->technician_designation) }}" required>
                            </div>
                            <div class="mb-2">
                                <label class="field-label">Degree / Qualifications</label>
                                <input type="text" name="technician_degree" class="form-control form-control-sm" value="{{ old('technician_degree', $setting->technician_degree) }}" placeholder="e.g. DMLT / B.Sc MLT">
                            </div>
                            <div class="mb-2">
                                <label class="field-label">Signature Image</label>
                                <div class="image-preview-box mb-2" style="background-color: #ffffff; border: 1px solid #cbd5e1;">
                                    @if($setting->technician_signature_path && file_exists(public_path($setting->technician_signature_path)))
                                        <img src="{{ asset($setting->technician_signature_path) }}" alt="Technician Signature" id="previewTechSign" style="max-height: 48px;">
                                    @else
                                        <div class="text-muted small" id="noTechSignText">No signature uploaded</div>
                                        <img src="" alt="Technician Signature" id="previewTechSign" style="display: none; max-height: 48px;">
                                    @endif
                                </div>
                                <input type="file" name="technician_signature" class="form-control form-control-sm" accept="image/*" onchange="previewImage(this, '#previewTechSign', '#noTechSignText')">
                            </div>
                        </div>
                    </div>

                    <!-- Right: Consultant Pathologist -->
                    <div class="col-md-6">
                        <div class="settings-section-card h-100">
                            <div class="settings-section-title">
                                <i class="uil uil-award text-success"></i>
                                <span>Consultant Pathologist / Verified By</span>
                            </div>
                            <div class="mb-2">
                                <label class="field-label">Pathologist / Doctor Name <span class="text-danger">*</span></label>
                                <input type="text" name="pathologist_name" class="form-control form-control-sm fw-bold" value="{{ old('pathologist_name', $setting->pathologist_name) }}" required>
                            </div>
                            <div class="mb-2">
                                <label class="field-label">Designation <span class="text-danger">*</span></label>
                                <input type="text" name="pathologist_designation" class="form-control form-control-sm" value="{{ old('pathologist_designation', $setting->pathologist_designation) }}" required>
                            </div>
                            <div class="mb-2">
                                <label class="field-label">Degree / Medical Qualifications</label>
                                <input type="text" name="pathologist_degree" class="form-control form-control-sm" value="{{ old('pathologist_degree', $setting->pathologist_degree) }}" placeholder="e.g. M.B.B.S., MD (Path)">
                            </div>
                            <div class="mb-2">
                                <label class="field-label">Signature Image</label>
                                <div class="image-preview-box mb-2" style="background-color: #ffffff; border: 1px solid #cbd5e1;">
                                    @if($setting->pathologist_signature_path && file_exists(public_path($setting->pathologist_signature_path)))
                                        <img src="{{ asset($setting->pathologist_signature_path) }}" alt="Pathologist Signature" id="previewPathSign" style="max-height: 48px;">
                                    @else
                                        <div class="text-muted small" id="noPathSignText">No signature uploaded</div>
                                        <img src="" alt="Pathologist Signature" id="previewPathSign" style="display: none; max-height: 48px;">
                                    @endif
                                </div>
                                <input type="file" name="pathologist_signature" class="form-control form-control-sm" accept="image/*" onchange="previewImage(this, '#previewPathSign', '#noPathSignText')">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════ -->
            <!-- TAB 4: REPORT & RECEIPT PREFERENCES                       -->
            <!-- ══════════════════════════════════════════════════════════ -->
            <div class="tab-pane fade" id="tabPrint" role="tabpanel">
                <div class="settings-section-card">
                    <div class="settings-section-title">
                        <i class="uil uil-ruler-combined text-primary"></i>
                        <span>Print Page Margins (Top & Bottom)</span>
                    </div>

                    <div class="alert alert-info py-2 px-3 mb-3" style="font-size: 11.5px;">
                        <i class="uil uil-info-circle me-1"></i>
                        <strong>Letterhead Printing Tip:</strong> When clicking <strong>Print</strong> to print directly onto your clinic's pre-printed letterhead paper, set the <strong>Top Margin</strong> and <strong>Bottom Margin</strong> in millimeters (mm) so the test results start neatly below your pre-printed letterhead and stop above your pre-printed footer. When clicking <strong>Download</strong>, the PDF report is always generated with your uploaded header image banner.
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="field-label fw-bold">Top Margin for Print (mm) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="print_top_margin" class="form-control" value="{{ old('print_top_margin', $setting->print_top_margin ?? 35) }}" min="0" max="150" required>
                                <span class="input-group-text bg-light text-muted">mm</span>
                            </div>
                            <small class="text-muted" style="font-size: 10px;">Space left blank at the top for pre-printed letterhead (recommended: 30 - 45 mm).</small>
                        </div>

                        <div class="col-md-4">
                            <label class="field-label fw-bold">Bottom Margin for Print (mm) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="print_bottom_margin" class="form-control" value="{{ old('print_bottom_margin', $setting->print_bottom_margin ?? 20) }}" min="0" max="150" required>
                                <span class="input-group-text bg-light text-muted">mm</span>
                            </div>
                            <small class="text-muted" style="font-size: 10px;">Space left blank at the bottom for pre-printed footer (recommended: 15 - 25 mm).</small>
                        </div>

                        <div class="col-md-4 d-flex align-items-center">
                            <div class="form-check form-switch pt-3">
                                <input class="form-check-input" type="checkbox" role="switch" name="print_with_header" id="checkPrintWithHeader" value="1" {{ $setting->print_with_header ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold text-dark" for="checkPrintWithHeader" style="font-size: 11.5px;">
                                    Print with Header on Blank Paper
                                </label>
                                <div class="text-muted" style="font-size: 9.5px;">Leave unchecked when printing on pre-printed letterhead. (Downloaded PDFs always include header).</div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-section-title">
                        <i class="uil uil-sliders-v-alt text-primary"></i>
                        <span>Report Formatting & Print Options</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" name="show_barcode" id="checkShowBarcode" value="1" {{ $setting->show_barcode ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold text-dark" for="checkShowBarcode" style="font-size: 12px;">
                                    Show 1D Barcode on Top Header of Reports
                                </label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" name="show_qr_code" id="checkShowQr" value="1" {{ $setting->show_qr_code ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold text-dark" for="checkShowQr" style="font-size: 12px;">
                                    Show QR Code for Public Verification in Signatures block
                                </label>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="field-label">Report Bottom Medico-Legal Disclaimer</label>
                            <textarea name="report_disclaimer" rows="2" class="form-control form-control-sm" placeholder="Disclaimer displayed at the end of every lab report...">{{ old('report_disclaimer', $setting->report_disclaimer) }}</textarea>
                            <small class="text-muted" style="font-size: 10px;">Displayed under the signatures section on reports.</small>
                        </div>

                        <div class="col-md-12">
                            <label class="field-label">Receipt Footer Note</label>
                            <textarea name="receipt_footer_note" rows="2" class="form-control form-control-sm" placeholder="Note displayed on OPD payment receipts...">{{ old('receipt_footer_note', $setting->receipt_footer_note) }}</textarea>
                            <small class="text-muted" style="font-size: 10px;">Printed at the bottom of OPD billing payment receipts.</small>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Sticky Footer Save Bar -->
        <div class="card p-3 border mt-3 bg-white shadow-sm d-flex flex-row justify-content-between align-items-center">
            <div class="text-muted" style="font-size: 11.5px;">
                Changes will take effect immediately across all printed reports, receipts, and order previews.
            </div>
            <button type="submit" class="btn btn-primary btn-sm fw-bold px-4 shadow-sm" style="font-size: 12.5px;">
                <i class="uil uil-save me-1"></i> Save All Settings
            </button>
        </div>
    </form>

</div>
@endsection

@push('scripts')
<script>
function previewImage(input, previewSelector, noTextSelector) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            $(previewSelector).attr('src', e.target.result).show();
            if (noTextSelector) $(noTextSelector).hide();
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endpush
