@extends('lab.layouts.app')

@section('title', 'Billing Desk | ')
@section('context', 'billing')

@section('content')
<div class="lis-billing-workspace"
     data-patient-url="{{ route('lab.api.patients.search') }}"
     data-services-url="{{ route('lab.api.services.search') }}"
     data-today-visits-url="{{ route('lab.api.billing.today-visits') }}"
     data-referral-store-url="{{ route('lab.api.referrals.store') }}"
     data-next-sample-url="{{ route('lab.billing.next-sample-no') }}"
     data-store-url="{{ route('lab.api.billing.store') }}">

    <!-- Page Title & Header Bar -->
    <!-- <div class="opd-page-header">
        <h5 class="opd-page-title">
            <i class="uil uil-file-alt"></i> Billing Desk
        </h5>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-white text-dark border px-2 py-1" style="font-size: 11.5px;">
                <i class="uil uil-calendar-alt text-teal me-1"></i> {{ \Carbon\Carbon::today()->format('D, d M Y') }}
            </span>
            <button type="button" class="btn btn-today-visits btn-sm" id="btn-header-toggle-today-visits" title="Toggle Today's Visits (Open/Collapse)">
                <i class="uil uil-users-alt me-1"></i> Today's Visits
                <span class="badge rounded-pill bg-teal text-white ms-1" id="badge-today-count-header">{{ $todayVisitsCount ?? 0 }}</span>
            </button>
        </div>
    </div> -->

    <!-- Main Two-Column Layout: 70% Billing Desk + 30% Today's Visits -->
    <div class="opd-desk-layout">

        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- LEFT COLUMN (70%): COMPLETE BILLING WORKSPACE -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <div class="opd-desk-left">

            <!-- Step 1: Patient Information Card -->
            <div class="pro-card">
                <!-- Single Line: New/Old Toggle + Search Box + Ref By Dropdown -->
                <div class="row align-items-center g-2" id="patient_top_bar_row">
                    <!-- 1. Patient Toggle Switch -->
                    <div class="col-auto">
                        <div class="d-flex align-items-center">
                            <span class="fw-bold text-dark me-2" style="font-size: 12px;">Patient:</span>
                            <div class="d-flex align-items-center me-1">
                                <input type="checkbox" id="switch9" switch="dark">
                                <label for="switch9" data-on-label="New" data-off-label="Old" class="m-0" title="Toggle New / Old Patient"></label>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Search Box (Takes middle column when Old mode) -->
                    <div class="col position-relative" id="search_box_container">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted" style="border-color: var(--brand-border); height: 34px;">
                                <i class="uil uil-search"></i>
                            </span>
                            <input type="text"
                                   class="form-control border-start-0 ps-1"
                                   id="uhid"
                                   placeholder="Search UHID, Mobile number, or Patient Name..."
                                   value="{{ $preloadedPatient['uhid'] ?? '' }}"
                                   autocomplete="off"
                                   style="height: 34px; font-size: 12.5px;">
                            <button type="button" class="btn btn-search-navy px-3" id="search-btn" title="Search (Enter)" style="height: 34px; font-size: 12px;">
                                Search
                            </button>
                            <button type="button" class="btn btn-reset-coral px-2" id="reset-btn" title="Clear / Reset (Alt+N)" style="height: 34px;">
                                <i class="uil uil-refresh"></i>
                            </button>
                        </div>

                        <!-- Floating Live Autocomplete Dropdown Menu -->
                        <div id="patient-autocomplete-dropdown" class="patient-autocomplete-menu" style="display: none;"></div>
                    </div>

                    <!-- 3. Ref By Dropdown + Add Button -->
                    <div class="col-auto">
                        <div class="d-flex align-items-center gap-1" style="min-width: 250px;">
                            <label for="referral_select" class="form-label mb-0 fw-bold text-dark text-nowrap" style="font-size: 11.5px;">
                                <i class="uil uil-user-md text-teal me-1"></i> Ref By:
                            </label>
                            <div class="input-group input-group-sm">
                                <select class="form-select form-select-sm" id="referral_select" style="font-size: 12px; height: 34px;">
                                    <option value="Self" selected>Self (Direct)</option>
                                    @foreach($referrals ?? [] as $ref)
                                        <option value="{{ $ref->name }}" data-phone="{{ $ref->mobile_no }}">
                                            {{ $ref->name }} {{ $ref->mobile_no ? '('.$ref->mobile_no.')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-outline-teal btn-sm" id="btn-add-referral-modal" title="Add New Referring Doctor" style="height: 34px;">
                                    <i class="uil uil-plus"></i> Add
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Sample No (Custom or Auto Series) -->
                    <div class="col-auto">
                        <div class="d-flex align-items-center gap-1" style="min-width: 220px;">
                            <label for="custom_sample_no" class="form-label mb-0 fw-bold text-dark text-nowrap" style="font-size: 11.5px;">
                                <i class="uil uil-tag-alt text-teal me-1"></i> Sample No:
                            </label>
                            <div class="input-group input-group-sm">
                                <input type="text"
                                       class="form-control form-control-sm"
                                       id="custom_sample_no"
                                       placeholder="Auto: {{ $nextSampleBarcode ?? 'SMP-000001' }}"
                                       data-default-preview="{{ $nextSampleBarcode ?? 'SMP-000001' }}"
                                       title="Enter custom sample no. to start series from there, or leave empty for auto series"
                                       style="font-size: 11.5px; height: 34px; font-weight: 600;">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-refresh-sample-no" title="Reset / Refresh next sample number in series" style="height: 34px;">
                                    <i class="uil uil-sync"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Patient Details Display BELOW of Search Box (When Old) -->
                <div class="patient-details-below mt-2" id="patient_info_bar">
                    <div class="detail-cell">
                        <span class="detail-label">UHID:</span>
                        <span class="detail-val text-teal fw-bold" id="b_uhid">{{ $preloadedPatient['uhid'] ?? '---' }}</span>
                    </div>
                    <div class="detail-cell">
                        <span class="detail-label">Name:</span>
                        <span class="detail-val fw-bold text-dark" id="b_name">{{ $preloadedPatient['name'] ?? '---' }}</span>
                    </div>
                    <div class="detail-cell">
                        <span class="detail-label">Age/Sex:</span>
                        <span class="detail-val" id="b_age">{{ $preloadedPatient['age'] ?? '---' }}</span>
                    </div>
                    <div class="detail-cell">
                        <span class="detail-label">Mobile:</span>
                        <span class="detail-val" id="b_mobile_no">{{ $preloadedPatient['mobile_no'] ?? '---' }}</span>
                    </div>
                    <div class="detail-cell">
                        <span class="detail-label">Category:</span>
                        <span class="detail-val badge bg-light text-dark border" id="b_i_cover">{{ $preloadedPatient['category'] ?? 'Cash' }}</span>
                    </div>
                </div>

                <input type="hidden" id="patient_id" value="{{ $preloadedPatient['id'] ?? '' }}">
                <input type="hidden" id="client_token" value="">

                <!-- 1B: Register New Patient (When New is Checked) -->
                <div id="pane_register_patient" style="display: none;" class="mt-2">
                    <div class="p-2 rounded-2" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-dark" style="font-size: 12px;">
                                <i class="uil uil-user-plus text-teal me-1"></i> New Patient Registration
                            </span>
                            <span class="badge bg-teal-soft text-teal" style="font-size: 10.5px;">
                                <i class="uil uil-check-circle me-1"></i> Auto UHID on Save
                            </span>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-3 col-6">
                                <label class="form-label mb-0 fw-semibold" style="font-size: 11px;">Full Name <span class="text-danger">*</span></label>
                                <input type="text" id="reg_name" class="form-control form-control-sm" placeholder="e.g. Ramesh Kumar">
                            </div>
                            <div class="col-md-2 col-3">
                                <label class="form-label mb-0 fw-semibold" style="font-size: 11px;">Age <span class="text-danger">*</span></label>
                                <input type="number" id="reg_age" class="form-control form-control-sm" placeholder="Age" min="0" max="150">
                            </div>
                            <div class="col-md-2 col-3">
                                <label class="form-label mb-0 fw-semibold" style="font-size: 11px;">Gender <span class="text-danger">*</span></label>
                                <select id="reg_sex" class="form-select form-select-sm">
                                    <option value="Male" selected>Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-6">
                                <label class="form-label mb-0 fw-semibold" style="font-size: 11px;">Mobile Number <span class="text-danger">*</span></label>
                                <input type="tel" id="reg_phone" class="form-control form-control-sm" placeholder="10-digit Mobile" maxlength="10" required>
                            </div>
                            <div class="col-md-2 col-6">
                                <label class="form-label mb-0 fw-semibold" style="font-size: 11px;">City / Area</label>
                                <input type="text" id="reg_city" class="form-control form-control-sm" placeholder="City or Local">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2 & 3: Main Billing Workspace -->
            <div class="billing-split-grid">
                <!-- Left: Investigation Table & Catalog -->
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

                    <!-- Full-Width Investigation Search Bar -->
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
                                   id="search_service"
                                   class="form-control"
                                   placeholder="Type investigation name or code (e.g. LFT, ESR, Blood Sugar, CBC, USG)..."
                                   autocomplete="off">
                        </div>
                        <ul id="results" class="investigation-list-results w-100"></ul>
                    </div>
                </div>

                <!-- Right: Step 3 Payment & Settlement Panel -->
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

                        <!-- Final Discount (%) -->
                        <div class="payment-row">
                            <label for="discount_percent">Discount (%)</label>
                            <div class="input-group" style="max-width: 120px;">
                                <input type="number"
                                       step="0.5"
                                       min="0"
                                       max="100"
                                       class="form-control payment-input-editable text-end"
                                       id="discount_percent"
                                       placeholder="0"
                                       value=""
                                       style="height: 32px; font-size: 13px;">
                                <span class="input-group-text bg-light text-muted px-2" style="font-size: 12px; height: 32px; border-color: var(--brand-border);">%</span>
                            </div>
                        </div>

                        <!-- Remarks -->
                        <div class="payment-row">
                            <label for="remarks">Remarks</label>
                            <input type="text" class="payment-input-editable" id="remarks" placeholder="Optional note">
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
                            <label for="payment_cash"><i class="uil uil-money-bill text-success me-1"></i> Cash</label>
                            <input type="number" step="0.01" min="0" class="payment-input-editable text-end payment-field" id="payment_cash" data-mode="cash" placeholder="0.00" value="">
                        </div>

                        <!-- UPI / QR -->
                        <div class="payment-row multi-col">
                            <label for="payment_upi"><i class="uil uil-qrcode-scan text-teal me-1"></i> UPI / QR</label>
                            <input type="number" step="0.01" min="0" class="payment-input-editable text-end payment-field" id="payment_upi" data-mode="upi" placeholder="0.00" value="">
                            <input type="text" class="payment-input-editable" id="tx_upi" placeholder="UTR / TRX ID">
                        </div>

                        <!-- Card / POS -->
                        <div class="payment-row multi-col">
                            <label for="payment_card"><i class="uil uil-credit-card text-primary me-1"></i> Card / POS</label>
                            <input type="number" step="0.01" min="0" class="payment-input-editable text-end payment-field" id="payment_card" data-mode="card" placeholder="0.00" value="">
                            <input type="text" class="payment-input-editable" id="tx_card" placeholder="Slip Ref #">
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

                        <!-- Action Buttons Footer -->
                        <div class="payment-actions-footer">
                            <button type="button" class="btn-generate-bill w-100 justify-content-center" id="btn-save-bill" title="Save and print receipt (Ctrl+Enter)">
                                <i class="uil uil-print"></i> Generate & Print Bill
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- RIGHT COLUMN: TODAY'S VISITS (COLLAPSIBLE TO RIGHT SIDE) -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <div class="opd-desk-right" id="opd-desk-right">
            <!-- Expanded State Desk Panel (Default Open) -->
            <div class="today-visits-panel-card" id="today-visits-expanded-view">
                <div class="today-visits-panel-header">
                    <div>
                        <h6 class="fw-bold mb-0 text-white" style="font-size: 13px;">
                            <i class="uil uil-users-alt me-1 text-teal"></i> Today's Visits
                        </h6>
                        <span class="text-white-50" style="font-size: 11px;">
                            {{ \Carbon\Carbon::today()->format('D, d M Y') }}
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge rounded-pill bg-teal text-white" id="badge-today-count">{{ $todayVisitsCount ?? 0 }}</span>
                        <button type="button" class="btn btn-sm btn-outline-light py-0 px-2" id="btn-refresh-today-visits" title="Refresh List" style="color: #ffffff !important; border-color: rgba(255,255,255,0.4) !important; background-color: rgba(255,255,255,0.12) !important;">
                            <i class="uil uil-refresh text-white"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-light py-0 px-2 btn-collapse-arrow" id="btn-collapse-today-visits" title="Collapse to right side" style="color: #ffffff !important; border-color: rgba(255,255,255,0.4) !important; background-color: rgba(255,255,255,0.12) !important;">
                            <i class="uil uil-angle-right font-size-14 text-white"></i>
                        </button>
                    </div>
                </div>

                <div class="today-visits-panel-body">
                    <!-- Today's Stats Bar -->
                    <div class="row g-2">
                        <div class="col-4">
                            <div class="today-kpi-card">
                                <div class="kpi-label">Visits</div>
                                <div class="kpi-val text-dark" id="tv-stat-count">0</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="today-kpi-card">
                                <div class="kpi-label">Billed</div>
                                <div class="kpi-val text-primary" id="tv-stat-billed" style="font-size: 12px;">₹0</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="today-kpi-card">
                                <div class="kpi-label">Paid</div>
                                <div class="kpi-val text-success" id="tv-stat-paid" style="font-size: 12px;">₹0</div>
                            </div>
                        </div>
                    </div>

                    <!-- Search Filter inside Today's Visits -->
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 py-1" style="font-size: 11.5px;">
                            <i class="uil uil-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0 ps-1 py-1" id="tv-search-filter" placeholder="Filter by Name, UHID, Ref..." style="font-size: 11.5px; height: 32px;">
                    </div>

                    <!-- Scrollable List of Today's Patient Visits -->
                    <div class="today-visits-scrollable" id="today-visits-container">
                        <div class="text-center py-4 text-muted" id="tv-loading">
                            <div class="spinner-border spinner-border-sm text-teal mb-2" role="status"></div>
                            <div style="font-size: 11.5px;">Loading today's visits...</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Collapsed Vertical Tab Bar (Visible when collapsed on desktop) -->
            <div class="today-visits-collapsed-tab" id="tab-expand-today-visits" title="Click to expand Today's Visits" style="display: none;">
                <button type="button" class="btn-expand-tab" id="btn-expand-today-visits">
                    <i class="uil uil-angle-left font-size-18 text-teal mb-2"></i>
                    <span class="vertical-text">TODAY'S VISITS</span>
                    <span class="badge rounded-pill bg-teal text-white mt-2" id="badge-today-count-collapsed">{{ $todayVisitsCount ?? 0 }}</span>
                </button>
            </div>
        </div>

    </div>

    <!-- Mobile Drawer Overlay Backdrop -->
    <div id="today-visits-mobile-backdrop" class="today-visits-mobile-backdrop" style="display: none;"></div>

    <!-- Modal: Add New Referring Doctor -->
    <div class="modal fade" id="addReferralModal" tabindex="-1" aria-labelledby="addReferralModalLabel" aria-hidden="true" style="z-index: 1065;">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
            <div class="modal-content border-0 shadow">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, var(--brand-teal-dark), var(--brand-teal));">
                    <h6 class="modal-title fw-bold text-white mb-0" id="addReferralModalLabel">
                        <i class="uil uil-user-plus me-1"></i> Add Referring Doctor
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="form-add-referral">
                    <div class="modal-body p-3">
                        <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size: 12.5px;">Doctor / Clinic Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="new_ref_name" placeholder="e.g. Dr. Rajesh Verma" required style="font-size: 13px;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size: 12.5px;">Contact / Mobile Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="new_ref_phone" placeholder="10-digit mobile number" maxlength="15" required style="font-size: 13px;">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-semibold" style="font-size: 12.5px;">Degree / Speciality (Optional)</label>
                            <input type="text" class="form-control" id="new_ref_degree" placeholder="e.g. MBBS, MD (Med)" style="font-size: 13px;">
                        </div>
                    </div>
                    <div class="modal-footer bg-light px-3 py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-teal-primary btn-sm" id="btn-save-referral">
                            <i class="uil uil-check"></i> Save Referral
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    window.initialInvestigations = @json($initialServices ?? []);
</script>
<script src="{{ asset('js/lab-billing.js') }}?v={{ filemtime(public_path('js/lab-billing.js')) }}"></script>
@endpush
