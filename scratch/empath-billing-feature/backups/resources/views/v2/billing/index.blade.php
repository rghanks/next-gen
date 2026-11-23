<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Empath HIMS Billing</title>
  
    <!-- App favicon -->
    <link rel="icon" type="image/png" href="{{ asset('assets/images/logos.png')}}">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="{{ asset('assets/css/v2/registration.css') }}?v={{ file_exists(public_path('assets/css/v2/registration.css')) ? filemtime(public_path('assets/css/v2/registration.css')) : '1' }}">
  <link rel="stylesheet" href="{{ asset('assets/css/v2/nav-shell.css') }}?v={{ file_exists(public_path('assets/css/v2/nav-shell.css')) ? filemtime(public_path('assets/css/v2/nav-shell.css')) : '1' }}">
  <link rel="stylesheet" href="{{ asset('assets/css/v2/opd-billing.css') }}?v={{ time() }}">
  <script src="{{ asset('/assets/js/jquery.min.js') }}"></script>
  <style>
    :root {
      color-scheme: light;
      box-sizing: border-box;
      padding-top: env(safe-area-inset-top, 0px);
      padding-bottom: env(safe-area-inset-bottom, 0px);
    }
    html {
      scroll-padding-top: env(safe-area-inset-top, 0px);
    }
    body {
      margin: 0;
      padding: 0;
      font: 14px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
      background: var(--md-sys-color-surface, #f8f8f8);
      color: var(--md-sys-color-on-surface, #030303);
    }
    [hidden]:not([hidden=until-found i]) {
      display: none !important;
    }
  </style>
</head>
<body>

  <a class="skip" href="#view-new">Skip to Billing</a>

  <div class="app" id="app">
    <x-v2.side-menu active="billing" mode="rail" />

    <div class="main">
      <x-v2.topbar :show-hospital-name="false">
        <div class="ob-tabs" role="tablist" aria-label="Billing sections">
          @php
           $can_create_bill   = auth()->user()->can('module-permission', ['opd_billing', 'can_create']);
           $can_read_bill     = auth()->user()->can('module-permission', ['opd_billing', 'can_read']);
           $can_update_bill   = auth()->user()->can('module-permission', ['opd_billing', 'can_update']);
           $can_cancel_bill   = auth()->user()->can('module-permission', ['bill_cancel', 'can_update']);
           $can_create_refund = auth()->user()->can('module-permission', ['refund', 'can_create']);
           $can_read_refund   = auth()->user()->can('module-permission', ['refund', 'can_read']);
           $can_update_refund = auth()->user()->can('module-permission', ['refund', 'can_update']);
           $has_refund_tab    = $can_read_refund || $can_create_refund || $can_update_refund;

           $defaultActiveTab = $can_create_bill ? 'new' : ($can_read_bill ? 'all' : ($has_refund_tab ? 'ref' : 'new'));
           $currentActiveTab = $activeTab ?? $defaultActiveTab;
          @endphp   
          @if($can_create_bill)
          <button type="button" role="tab" id="tab-new" aria-selected="{{ $currentActiveTab === "new" ? "true" : "false" }}" aria-controls="view-new">New bill</button>
          @endif  
          @if($can_read_bill)
          <button type="button" role="tab" id="tab-all" aria-selected="{{ $currentActiveTab === "all" ? "true" : "false" }}" aria-controls="view-all">All bills</button>
          @endif
          @if($has_refund_tab)
          <button type="button" role="tab" id="tab-ref" aria-selected="{{ $currentActiveTab === "ref" ? "true" : "false" }}" aria-controls="view-ref">Refunds</button>
          @endif
        </div>
      </x-v2.topbar>

       @if($can_create_bill || $can_update_bill || $can_cancel_bill)     
        <!-- ═════════════ TAB 1: NEW BILL ═════════════ -->
        <section id="view-new" role="tabpanel" aria-labelledby="tab-new" {{ $currentActiveTab === "new" ? "" : "hidden" }}>
          <!-- Patient search / finder with Advanced Search -->
          <x-v2.patient-search id="finder" placeholder="Search UHID, phone number, ABHA ID or name" />

          <div class="ob-work">
            <!-- Before patient is selected -->
            <div class="ob-empty" id="ob-empty">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/><path d="M9 8h6M9 12h6"/></svg>
              <b>Find the patient to start a bill</b>
              <span>Search by UHID, phone number, ABHA ID or name. A patient not yet registered is registered first, on <a id="reg-link" href="{{ route('registration.index') }}">Registration</a>.</span>
            </div>

                        <!-- Edit pick decision screen -->
            <div class="ob-pick" id="edit-pick" hidden>
              <button type="button" class="btn btn-text ob-back" id="ep-back">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:18px;height:18px"><path d="M15 6l-6 6 6 6"/></svg>
                <span id="ep-back-l">All bills</span>
              </button>
              <div class="vv-top ob-pt">
                <div class="vv-who">
                  <h2 id="ep-name"></h2>
                  <p class="vv-meta" id="ep-meta"></p>
                </div>
              </div>
              <dl class="vv-grid ep-facts" id="ep-facts"></dl>
              <div class="banner banner-warn is-settled-warn" id="ep-settled-warn" hidden style="margin-top:var(--md-sys-spacing-3);margin-bottom:var(--md-sys-spacing-4);padding:14px 16px;border-radius:10px;background:var(--md-sys-color-warning-container,#fff3cd);color:var(--md-sys-color-on-warning-container,#664d03);display:flex;align-items:center;justify-content:space-between;gap:16px;">
                <div style="display:flex;align-items:flex-start;gap:12px">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:22px;height:22px;flex-shrink:0;margin-top:2px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                  <div>
                    <strong style="font-size:14px;display:block">This bill is already settled</strong>
                    <span style="font-size:13px;opacity:0.9">Settled bills cannot be edited. If you want to make changes, please unsettle this bill first and then proceed.</span>
                  </div>
                </div>
                <button type="button" class="btn btn-filled" id="ep-unsettle-btn" style="white-space:nowrap;padding:6px 14px;font-size:13px;height:auto">Unsettle bill</button>
              </div>
              <div class="ep-cards" role="group" aria-label="What to do with this bill">
                @if($can_update_bill)
                <button type="button" class="ep-card" id="ep-edit">
                  <span class="ep-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></span>
                  <span class="ep-t">Edit the bill</span>
                  <span class="ep-d">Correct the doctor, the lines, a discount or what was received — for a bill saved by mistake. If the correction leaves the clinic holding more than the bill asks for, you are asked to refund the difference.</span>
                </button>
                @endif
                @if($can_cancel_bill)
                <button type="button" class="ep-card ep-danger" id="ep-cancel">
                  <span class="ep-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8.5 15.5l7-7"/></svg></span>
                  <span class="ep-t">Cancel and refund</span>
                  <span class="ep-d">Strike the whole bill, or only the lines that were not given, and send what was taken back to the patient. A cancelled bill stays on the record, struck through, with the reason.</span>
                </button>
                @endif
              </div>
            </div>

            <!-- The bill being prepared -->
            <div class="ob-form" id="ob-form" hidden>
                            <button type="button" class="btn btn-text ob-back" id="edit-back" hidden>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:18px;height:18px"><path d="M15 6l-6 6 6 6"/></svg>
                <span id="edit-back-l">All bills</span>
              </button>
              <div class="edit-note" id="edit-note" hidden>
                <div class="edit-note-main">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:20px;height:20px;flex-shrink:0"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                  <div class="en-what">
                    <div class="en-title"><span id="en-verb">Editing Bill</span> <b id="en-no"></b></div>
                    <p class="en-sub" id="en-sub">Changes to lines, doctor or discount will update this bill.</p>
                  </div>
                </div>
                <div class="en-acts">
                  <button type="button" class="btn btn-text" id="edit-stop">Exit edit mode</button>
                </div>
              </div>

              <div class="vv-top ob-pt" id="pt-block">
                <div class="vv-who">
                  <h2 id="pt-name"></h2>
                  <p class="vv-meta" id="pt-meta"></p>
                </div>
                <div class="pt-doc-badge" id="pt-doc-badge" hidden>
                  <label class="doc-label" id="pt-doc-label">
                    <span class="doc-lbl-title">Doctor:</span>
                    <b class="doc-lbl-name" id="pt-doc-name"></b>
                    <span class="doc-lbl-spec" id="pt-doc-spec" style="display:none;"></span>
                  </label>
                </div>
                <button type="button" class="btn btn-text" id="pt-change">Change patient</button>
              </div>

              <!-- Reused Visit Component with Billing mode -->
              <x-v2.registration.visit-step mode="billing" stepNumber="" title="Doctor and consultation" />

              @php
                $discAuthUsers = \App\Models\User::where('org_id', Auth::user()->org_id ?? 0)
                    ->where('active', 1)
                    ->select('id', 'name', 'first_name', 'last_name')
                    ->orderBy('first_name', 'ASC')
                    ->get()
                    ->map(function ($u) {
                        $name = trim($u->name ?: (($u->first_name ?? '') . ' ' . ($u->last_name ?? '')));
                        return ['id' => (string) $u->id, 'name' => $name];
                    })
                    ->values()
                    ->toArray();
                $initialData['disc_auth_users'] = $discAuthUsers;
              @endphp

              <!-- Reused Billing Component -->
              <x-v2.registration.billing-step stepNumber="" title="Billing" emptyText="Add the consultation above, or search a service to add it to the bill." :discAuthUsers="$discAuthUsers" />

              <!-- Cancellation Panel -->
              <section class="panel sec sec-x" id="sec-cancel" aria-labelledby="t-cancel" hidden>
                <div class="sec-head">
                  <h2 class="sec-title" id="t-cancel">Cancel this bill, or lines of it</h2>
                  <label class="check cx-all"><input type="checkbox" id="cx-bill"><span class="c-label">Cancel the whole bill</span></label>
                </div>
                <div class="sec-body">
                  <div class="banner banner-warn" id="cx-warn" role="note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><path d="M12 9v5"/><circle cx="12" cy="17" r="1.1" fill="currentColor" stroke="none"/><path d="M10.3 3.9 2.6 17.1A1.9 1.9 0 0 0 4.3 20h15.4a1.9 1.9 0 0 0 1.7-2.9L13.7 3.9a1.9 1.9 0 0 0-3.4 0Z"/></svg>
                    <div>
                      <div class="b-title" id="cx-warn-t"></div>
                      <p class="b-detail" id="cx-warn-d"></p>
                    </div>
                  </div>
                  <table class="tbl cx-tbl" id="cx-tbl">
                    <thead><tr><th>No.</th><th>Service</th><th class="num">Charges</th><th class="num">Qty.</th><th class="num">Discount</th><th class="num">Amount</th><th class="act">Cancel</th></tr></thead>
                    <tbody id="cx-rows"></tbody>
                  </table>

                  <!-- Dynamic Refund Calculation & Mode Selection -->
                  <div class="cx-calc" id="cx-calc">
                    <div class="cx-calc-card">
                      <div class="cx-calc-item">
                        <span class="cx-calc-label">Subtotal</span>
                        <span class="cx-calc-val" id="cx-subtotal">&#8377;0.00</span>
                      </div>
                      <div class="cx-calc-item">
                        <span class="cx-calc-label">Discount (<span id="cx-disc-pct">0</span>%)</span>
                        <span class="cx-calc-val" id="cx-disc-amt">-&#8377;0.00</span>
                      </div>
                      <div class="cx-calc-item cx-calc-total">
                        <span class="cx-calc-label">Refund Amount</span>
                        <span class="cx-calc-val" id="cx-net-amt">&#8377;0.00</span>
                      </div>
                    </div>

                    <div class="cx-mode-block" id="cx-mode-block">
                      <label class="cx-label">Refund Mode</label>
                      <div class="cx-modes" role="radiogroup" aria-label="Refund Mode">
                        <label class="cx-mode-pill">
                          <input type="radio" name="cx_refund_mode" value="cash" checked>
                          <span>Cash</span>
                        </label>
                        <label class="cx-mode-pill">
                          <input type="radio" name="cx_refund_mode" value="upi">
                          <span>UPI</span>
                        </label>
                        <label class="cx-mode-pill">
                          <input type="radio" name="cx_refund_mode" value="net_banking">
                          <span>Bank transfer</span>
                        </label>
                        <label class="cx-mode-pill">
                          <input type="radio" name="cx_refund_mode" value="cheque">
                          <span>Cheque</span>
                        </label>
                      </div>
                    </div>
                  </div>

                  <div class="cx-foot">
                    <div class="field cx-remark" data-field="cxremark">
                      <label for="cx-remark">Cancellation remark<span class="req">*</span></label>
                      <input id="cx-remark" type="text" placeholder="Why is this being cancelled?" autocomplete="off" maxlength="160">
                      <span class="err" id="cxremark-err" role="alert"></span>
                    </div>
                    <div class="field cx-file">
                      <label for="cx-file">Attachment</label>
                      <input id="cx-file" type="file">
                    </div>
                  </div>
                  <div class="cx-acts">
                    @if($can_create_refund || $can_update_refund)
                    <button type="button" class="btn btn-filled cx-danger" id="cx-refund">Initiate Refund & Cancel Bill</button>
                    @endif
                    @if($can_cancel_bill)
                    <button type="button" class="btn btn-outlined" id="cx-only">Cancel Bill Only</button>
                    @endif
                    <span class="cx-note" id="cx-note"></span>
                  </div>
                </div>
              </section>
            </div>

            <!-- The bill once paid/generated -->
            <div class="ob-paid" id="ob-paid" hidden>
              <div class="vv-bar">
                <button type="button" class="btn btn-filled" id="paid-next">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" style="width:18px;height:18px"><path d="M12 5v14M5 12h14"/></svg>
                  New bill
                </button>
                @if($can_update_bill)
                <button type="button" class="btn btn-outlined" id="paid-edit">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:18px;height:18px"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                  Edit this bill
                </button>
                @endif
                @if($can_create_refund || $can_update_refund || $can_cancel_bill)
                <button type="button" class="btn btn-text" id="paid-refund">
                  Refund / Cancel
                </button>
                @endif
              </div>
              <div class="vv-ok" role="status">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/></svg>
                <div><b id="paid-h"></b><span id="paid-t"></span></div>
              </div>
              <div id="paid-doc"></div>
            </div>

            <!-- The bill once edit is saved -->
            <div class="ob-paid" id="edit-done" hidden>
              <div class="vv-bar">
                <button type="button" class="btn btn-outlined" id="ed-bills">All bills</button>
                <button type="button" class="btn btn-outlined" id="ed-new">New bill</button>
              </div>
              <div class="vv-ok" role="status">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/></svg>
                <div><b id="ed-h"></b><span id="ed-t"></span></div>
              </div>
              <div class="banner banner-warn" id="ed-ask" role="alert" hidden>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><path d="M12 9v5"/><circle cx="12" cy="17" r="1.1" fill="currentColor" stroke="none"/><path d="M10.3 3.9 2.6 17.1A1.9 1.9 0 0 0 4.3 20h15.4a1.9 1.9 0 0 0 1.7-2.9L13.7 3.9a1.9 1.9 0 0 0-3.4 0Z"/></svg>
                <div>
                  <div class="b-title" id="ed-ask-t"></div>
                  <p class="b-detail" id="ed-ask-d"></p>
                </div>
                @if($can_create_refund || $can_update_refund)
                <button type="button" class="btn btn-filled cx-danger" id="ed-refund"></button>
                @endif
              </div>
              <div id="ed-doc"></div>
            </div>
          </div>

          <!-- Sticky Bottom Action Bar -->
          <div class="actionbar" id="ob-bar" hidden>
            <div class="ab-what">
              <div class="ab-line" id="ab-line"></div>
              <p class="ab-sub" id="ab-sub"></p>
            </div>
            <span class="ab-amt" id="ab-amt">&#8377;0.00</span>
            <div class="ab-btns">
              <button type="button" class="btn btn-outlined" id="save-only">Bill later</button>
              <button type="button" class="btn btn-outlined" id="ob-clear">Clear</button>
              <button type="button" class="btn btn-filled" id="collect">Collect &#8377;0.00<span class="kbd">Alt S</span></button>
            </div>
          </div>
        </section>
      @endif 

      @if($can_read_bill)
      <!-- ═════════════ TAB 2: ALL BILLS ═════════════ -->
      <section id="view-all" role="tabpanel" aria-labelledby="tab-all" {{ $currentActiveTab === "all" ? "" : "hidden" }}>
        <div class="finder ab-filter">
          <form id="all-form" class="af-row" autocomplete="off" role="search" aria-label="Find bills">
            <div class="field af-no">
              <label for="f-no">Bill no.</label>
              <input id="f-no" type="text" inputmode="numeric" placeholder="Bill number">
            </div>
            <span class="af-or" aria-hidden="true">or</span>
            <div class="field af-pt">
              <label for="f-pt">UHID, name or phone</label>
              <input id="f-pt" type="text" placeholder="Patient UHID, name or phone">
            </div>
            <div class="field af-d">
              <label for="f-from">From</label>
              <input id="f-from" type="date">
            </div>
            <div class="field af-d">
              <label for="f-to">To</label>
              <input id="f-to" type="date">
            </div>
            <div class="field af-st">
              <span class="lbl" id="f-st-l">Payment</span>
              <div class="seg" role="group" aria-labelledby="f-st-l" id="f-status">
                <button type="button" data-st="all" aria-pressed="true">All</button>
                <button type="button" data-st="paid" aria-pressed="false">Fully paid</button>
                <button type="button" data-st="due" aria-pressed="false">Due</button>
              </div>
            </div>
            <div class="af-btns">
              <button type="submit" class="btn btn-filled">Search</button>
              <button type="button" class="btn btn-text" id="f-clear">Clear</button>
            </div>
          </form>
        </div>

        <div class="ob-work">
          <div id="all-list">
            <div class="al-sum" id="al-sum"></div>
            <div class="panel al-panel">
              <div class="tbl-wrap al-wrap">
                <table class="tbl al-tbl" id="al-tbl">
                  <thead><tr>
                    <th>Bill no.</th><th>Date &amp; time</th><th>Patient</th><th>Doctor</th>
                    <th class="num">Subtotal</th><th class="num">Discount</th><th class="num">Billed</th><th class="num">Paid</th><th class="num">Due</th><th class="act"><span class="sr">Actions</span></th>
                  </tr></thead>
                  <tbody id="al-rows"></tbody>
                </table>
                <p class="al-none" id="al-none" hidden></p>
              </div>
              <div class="al-pager" id="al-pager" hidden>
                <span class="pg-info" id="pg-info" aria-live="polite"></span>
                <label class="pg-size"><span>Rows per page</span>
                  <select id="pg-size" aria-label="Bills per page"><option value="10" selected>10</option><option value="25">25</option><option value="50">50</option></select>
                </label>
                <nav class="pg-nums" id="pg-nums" aria-label="Pages of bills"></nav>
              </div>
            </div>
          </div>
          <div id="all-detail" hidden>
            <button type="button" class="btn btn-text ob-back" id="al-back">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:18px;height:18px"><path d="M15 6l-6 6 6 6"/></svg>
              All bills
            </button>
            <div id="al-doc"></div>
          </div>
        </div>
      </section>
      @endif

      @if($has_refund_tab)
      <!-- ═════════════ TAB 3: REFUNDS ═════════════ -->
      <section id="view-ref" role="tabpanel" aria-labelledby="tab-ref" {{ $currentActiveTab === "ref" ? "" : "hidden" }}>
        <!-- List of all refunds -->
        <div id="ref-list">
          <div class="finder ab-filter">
            <form id="rf-form" class="af-row" autocomplete="off" role="search" aria-label="Find refunds">
              <div class="field af-no">
                <label for="rf-no">Refund no.</label>
                <input id="rf-no" type="text" inputmode="numeric" placeholder="Refund number">
              </div>
              <span class="af-or" aria-hidden="true">or</span>
              <div class="field af-pt">
                <label for="rf-pt">UHID, name or phone</label>
                <input id="rf-pt" type="text" placeholder="Patient UHID, name or phone">
              </div>
              <div class="field af-d"><label for="rf-from">From</label><input id="rf-from" type="date"></div>
              <div class="field af-d"><label for="rf-to">To</label><input id="rf-to" type="date"></div>
              <div class="af-btns">
                <button type="submit" class="btn btn-filled">Search</button>
                <button type="button" class="btn btn-text" id="rf-clear">Clear</button>
              </div>
            </form>
          </div>

          <div class="ob-work">
            <div class="rf-top">
              <div class="al-sum" id="rf-sum"></div>
              @if($can_create_refund || $can_update_refund)
              <div class="rf-right">
                <button type="button" class="btn btn-filled" id="rf-new">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" style="width:18px;height:18px"><path d="M12 5v14M5 12h14"/></svg>
                  Initiate a refund
                </button>
              </div>
              @endif
            </div>

            <div class="rf-tools">
              <div class="seg rf-st" role="group" aria-label="Show refunds that are" id="rf-st">
                <button type="button" data-g="all" aria-pressed="true">All<span class="cnt">0</span></button>
                <button type="button" data-g="open" aria-pressed="false">In process<span class="cnt">0</span></button>
                <button type="button" data-g="issued" aria-pressed="false">Completed<span class="cnt">0</span></button>
                <button type="button" data-g="rejected" aria-pressed="false">Rejected<span class="cnt">0</span></button>
              </div>
            </div>

            <div class="panel al-panel">
              <div class="tbl-wrap al-wrap">
                <table class="tbl al-tbl rf-tbl" id="rf-tbl">
                  <thead><tr>
                    <th>Refund no.</th><th>Status</th><th>Patient</th><th>Against</th>
                    <th class="num">Amount</th><th>Initiated</th><th>Approval</th><th>Issued to patient</th><th class="act">Action</th>
                  </tr></thead>
                  <tbody id="rf-rows"></tbody>
                </table>
                <p class="al-none" id="rf-none" hidden></p>
              </div>
            </div>

            <div class="rf-legend" aria-hidden="true">
              <span><i class="lg-p"></i>In process</span>
              <span><i class="lg-c"></i>Completed</span>
              <span><i class="lg-r"></i>Rejected</span>
            </div>
          </div>
        </div>

        <!-- New refund wizard -->
        <div id="ref-new" hidden>
          <!-- Patient search / finder for Refunds with Advanced Search -->
          <x-v2.patient-search
            id="rn-finder"
            prefix="rn-"
            form-id="rn-find"
            input-id="rn-q"
            results-id="rn-results"
            label="Patient"
            placeholder="UHID, name or mobile number"
            :show-kbd="false"
            :back-button="true"
            back-id="rn-back"
            back-text="All refunds"
          />

          <div class="ob-work">
            <div class="ob-empty" id="rn-empty">
              <b>Find the patient to refund</b>
              <p>Search by UHID, phone number or name. Every bill they have is listed, with what each one can still return.</p>
            </div>

            <div id="rn-body" hidden>
              <div class="vv-top ob-pt rn-who">
                <div class="vv-who">
                  <h2 id="rn-name"></h2>
                  <p class="vv-meta" id="rn-meta"></p>
                </div>
                <button type="button" class="btn btn-text" id="rn-change">Change patient</button>
              </div>

              <div class="rn-cols">
                <section class="panel sec" aria-labelledby="t-rn-bills">
                  <div class="sec-head">
                    <h2 class="sec-title" id="t-rn-bills"><span class="sec-n">1</span>Select the bills to refund against</h2>
                    <span class="sec-actions">
                      <div class="seg rn-filter" role="group" aria-label="Which bills to list" id="rn-filter"></div>
                      <button type="button" class="link link-quiet" id="rn-none-btn">Clear selection</button>
                    </span>
                  </div>
                  <div class="tbl-wrap">
                    <table class="tbl rn-tbl" id="rn-tbl">
                      <thead><tr>
                        <th class="pick"><span class="sr">Select</span></th>
                        <th>Bill no.</th><th>Date &amp; time</th><th>Doctor</th>
                        <th class="num">Billed</th><th class="num">Paid</th><th class="num">Refunded</th><th class="num">Refundable</th><th>Status</th>
                      </tr></thead>
                      <tbody id="rn-rows"></tbody>
                    </table>
                    <p class="al-none" id="rn-nobills" hidden></p>
                  </div>
                </section>

                <section class="panel sec rn-panel" id="rn-step2" aria-labelledby="t-rn-form" hidden>
                  <div class="sec-head">
                    <h2 class="sec-title" id="t-rn-form"><span class="sec-n">2</span>Request the refund</h2>
                    <span class="sec-actions soft-l">An admin approves it before it is paid out</span>
                  </div>
                  <div class="sec-body">
                    <div class="field rowgap" data-field="rnamt">
                      <label for="rn-amt">Refund amount<span class="req">*</span></label>
                      <div class="affix">
                        <span class="prefix">&#8377;</span>
                        <input id="rn-amt" class="is-bad-t" type="text" inputmode="numeric" placeholder="0" autocomplete="off">
                      </div>
                      <span class="err" id="rnamt-err" role="alert"></span>
                    </div>
                    <div class="field rowgap f-mode">
                      <span class="lbl" id="rn-mode-l">Refund mode</span>
                      <div class="seg rn-modes" role="group" aria-labelledby="rn-mode-l" id="rn-mode">
                        <button type="button" data-mode="Cash" aria-pressed="true">Cash</button>
                        <button type="button" data-mode="UPI" aria-pressed="false">UPI</button>
                        <button type="button" data-mode="Card" aria-pressed="false">Card</button>
                        <button type="button" data-mode="Bank transfer" aria-pressed="false">Bank transfer</button>
                      </div>
                    </div>
                    <div class="field rowgap" id="rn-txn-f">
                      <label for="rn-txn">Reference</label>
                      <input id="rn-txn" type="text" autocomplete="off" maxlength="40">
                      <span class="help" id="rn-txn-h">Not needed for cash</span>
                    </div>
                    <div class="field rowgap">
                      <label for="rn-reason">Reason<span class="req">*</span></label>
                      <select id="rn-reason">
                        <option value="Bill cancelled">Bill cancelled</option>
                        <option value="Service not given">Service not given</option>
                        <option value="Overcharged">Overcharged</option>
                        <option value="Duplicate payment">Duplicate payment</option>
                        <option value="Patient left before the service">Patient left before the service</option>
                        <option value="Other">Other</option>
                      </select>
                    </div>
                    <div class="field rowgap f-remark">
                      <label for="rn-remark">Remarks</label>
                      <input id="rn-remark" type="text" placeholder="Optional note for the record" autocomplete="off" maxlength="160">
                    </div>
                  </div>
                </section>
              </div>
            </div>

            <!-- the refund once issued / viewed -->
            <div id="rn-done" hidden>
              <div class="vv-bar">
                <span class="rf-acts" id="rn-acts"></span>
                <button type="button" class="btn btn-outlined" id="rn-tolist">All refunds</button>
                <button type="button" class="btn btn-outlined" id="rn-print">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" style="width:18px;height:18px"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                  Print voucher
                </button>
                <button type="button" class="btn btn-outlined" id="rn-edit-receipt" style="display:inline-flex;align-items:center;gap:6px">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:18px;height:18px"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                  Edit receipt
                </button>
                <button type="button" class="btn btn-filled" id="rn-again">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" style="width:18px;height:18px"><path d="M12 5v14M5 12h14"/></svg>
                  Another refund
                </button>
              </div>
              <div class="vv-ok" role="status">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/></svg>
                <div><b id="rn-done-h"></b><span id="rn-done-t"></span></div>
              </div>
              <div id="rn-done-doc"></div>
            </div>
          </div>

          <div class="actionbar rn-bar" id="rn-bar" hidden>
            <div class="ab-what">
              <div class="ab-line" id="rb-line"></div>
              <p class="ab-sub" id="rb-sub"></p>
            </div>
            <span class="ab-amt" id="rb-amt">&#8377;0.00</span>
            <div class="ab-btns">
              <button type="button" class="btn btn-filled" id="rn-go">Send for approval</button>
            </div>
          </div>
        </div>
      </section>
      @endif
    </div>
  </div>

  <!-- Row overflow kebab menu fixed to viewport -->
  <div id="row-menu" role="menu" aria-labelledby="rm-head" hidden>
    <p class="rm-head" id="rm-head" aria-hidden="true"></p>
    <button type="button" role="menuitem" data-act="print-bill"></button>
    <button type="button" role="menuitem" data-act="print-receipt"></button>
    <div class="rm-sep rm-sep-edit" role="separator"></div>
    <button type="button" role="menuitem" data-act="edit-bill"></button>
    <button type="button" role="menuitem" data-act="edit-receipt"></button>
    <button type="button" role="menuitem" class="rm-danger" data-act="cancel-bill"></button>
    <div class="rm-sep rm-sep-refund" role="separator"></div>
    <button type="button" role="menuitem" class="rm-danger" data-act="refund"></button>
  </div>

  <!-- The approval trail dialog: send, approve, reject, issue -->
  <div class="dlg-scrim" id="rq-ask" hidden>
    <div class="dlg ra-dlg" role="dialog" aria-modal="true" aria-labelledby="rq-t" aria-describedby="rq-d">
      <div class="dlg-head">
        <div><h2 class="dlg-title" id="rq-t"></h2></div>
        <button type="button" class="icon-btn dlg-x" id="rq-x" aria-label="Close dialog">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </div>
      <div class="ra-body rj-body" id="rq-d">
        <p id="rq-d1"></p>
        <p id="rq-d2"></p>
        <div class="field" id="rq-why-f" data-field="rqwhy" hidden style="margin-top:var(--md-sys-spacing-3)">
          <label for="rq-why">Reason for rejecting<span class="req">*</span></label>
          <textarea id="rq-why" maxlength="200" placeholder="The desk sees this, e.g. amount does not match the cancelled lines" style="width:100%;min-height:72px;resize:vertical;font:inherit;padding:8px 10px;border-radius:var(--md-sys-shape-corner-small);border:1px solid var(--md-sys-color-outline)"></textarea>
          <span class="err" id="rqwhy-err" role="alert"></span>
        </div>
      </div>
      <div class="dlg-foot ra-foot">
        <button type="button" class="btn btn-text" id="rq-no">Cancel</button>
        <button type="button" class="btn btn-filled" id="rq-yes"></button>
      </div>
    </div>
  </div>

  <!-- Multi-bill breakdown and rejection reason floating popover -->
  <div class="rf-pop" id="rf-pop" role="dialog" aria-labelledby="rf-pop-h" hidden>
    <b id="rf-pop-h"></b>
    <p id="rf-pop-t"></p>
    <ul class="rf-pop-l" id="rf-pop-l"></ul>
  </div>

  <!-- Confirmation dialog for refund -->
  <div class="dlg-scrim" id="rf-ask" hidden>
    <div class="dlg ra-dlg" role="dialog" aria-modal="true" aria-labelledby="ra-t" aria-describedby="ra-d">
      <div class="dlg-head">
        <div><h2 class="dlg-title" id="ra-t">Confirm Refund</h2></div>
        <button type="button" class="icon-btn dlg-x" id="ra-x" title="Go back" aria-label="Go back">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </div>
      <div class="ra-body" id="ra-d"><p id="ra-d1"></p></div>
      <div class="dlg-foot ra-foot">
        <button type="button" class="btn btn-text" id="ra-no">Not now</button>
        <button type="button" class="btn btn-filled cx-danger" id="ra-yes">Confirm and refund</button>
      </div>
    </div>
  </div>

  <!-- Edit Receipt Dialog -->
  <div class="dlg-scrim" id="er-dlg" hidden>
    <div class="dlg" role="dialog" aria-modal="true" aria-labelledby="er-t" style="width:min(480px,100%)">
      <div class="dlg-head">
        <div>
          <h2 class="dlg-title" id="er-t">Edit Receipt</h2>
          <p class="dlg-sub" id="er-sub">Receipt <span id="er-rcpt-no"></span> · Bill #<span id="er-bill-no"></span></p>
        </div>
        <button type="button" class="icon-btn dlg-x" id="er-x" title="Close" aria-label="Close dialog">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </div>
      <form id="er-form" autocomplete="off">
        <input type="hidden" id="er-payment-id">
        <input type="hidden" id="er-bill-id">
        <div class="ra-body" style="padding:var(--md-sys-spacing-4) var(--md-sys-spacing-5);display:flex;flex-direction:column;gap:12px">
          <div id="er-info" style="background:var(--md-sys-color-surface-container-high);padding:10px 12px;border-radius:var(--md-sys-shape-corner-medium);font-size:12.5px;color:var(--md-sys-color-on-surface-variant)"></div>

          <div class="field">
            <label for="er-mode">Payment Mode <span style="color:var(--md-sys-color-error)">*</span></label>
            <select id="er-mode" required style="width:100%;height:38px;padding:6px 10px;border-radius:var(--md-sys-shape-corner-small);border:1px solid var(--md-sys-color-outline);background:var(--md-sys-color-surface)">
              <option value="cash">Cash</option>
              <option value="upi">UPI</option>
              <option value="card">Card</option>
              <option value="cheque">Cheque</option>
              <option value="neft_rtgs">Bank Transfer / NEFT</option>
            </select>
          </div>

          <div class="field">
            <label for="er-amt">Amount Received (₹) <span style="color:var(--md-sys-color-error)">*</span></label>
            <div class="affix">
              <span class="prefix">₹</span>
              <input type="number" id="er-amt" step="0.01" min="0.01" required placeholder="0.00">
            </div>
            <span class="err" id="er-amt-err" role="alert" style="color:var(--md-sys-color-error);font-size:12px" hidden></span>
          </div>

          <div class="field" id="er-txn-wrap">
            <label for="er-txn">Transaction ID / Reference</label>
            <input type="text" id="er-txn" placeholder="Txn ID / Ref" maxlength="64">
          </div>

          <div class="field">
            <label for="er-reason">Reason for Update <span style="color:var(--md-sys-color-error)">*</span></label>
            <input type="text" id="er-reason" placeholder="e.g. Changed payment mode from Cash to UPI" required maxlength="150">
          </div>
        </div>
        <div class="dlg-foot" style="justify-content:flex-end">
          <button type="button" class="btn btn-text" id="er-cancel">Cancel</button>
          <button type="submit" class="btn btn-filled" id="er-submit">Update Receipt</button>
        </div>
      </form>
    </div>
  </div>

  <x-v2.registration.add-ref-dialog />
  <x-v2.registration.vitals-dialog />

  <!-- Toast notification snackbar -->
  <div class="snackbar" id="snackbar" role="status" aria-live="polite" hidden></div>

  <!-- Global context & routes -->
  <script>
    window.INITIAL_DATA = @json($initialData ?? []);
    window.USER_PERMISSIONS = @json($userPermissions ?? []);
    window.ROUTES = {
      saveVisit: "{{ route('visit.save') }}",
      addRefList: "{{ route('patient.add_ref_list') }}",
      genOpdBill: "{{ route('patient.gen_opd_bill') }}",
      sendWhatsAppBill: "{{ route('patient.send_wp_gen_bill') }}",
      getConsultCharges: "{{ url('/getconsultcharges') }}",
      getchargesDetails: "{{ url('/getcharges_details') }}/",
      search: "{{ route('registration.search') }}",
      listBills: "{{ route('v2.billing.list') }}",
      billDetails: "{{ url('v2/billing/bill') }}/",
      collectDue: "{{ route('v2.billing.collect_due') }}",
      updateReceipt: "{{ route('v2.billing.update_receipt') }}",
      showBill: "{{ route('visit.bill_show', '') }}",
      showBillWithCancelled: "{{ route('visit.bill_show.cancelled', '') }}",
      showBillPaymentReceipt: "{{ route('visit.bill_payment_receipt_show', '') }}",
      paymenthistoryReceipt: "{{ route('paymenthistory_receipt', '') }}",
      registrationIndex: "{{ route('registration.index') }}",
      patientTodayDraft: "{{ url('v2/billing/patient-draft-today') }}/",
      draftBill: "{{ url('v2/registration/draft-bill') }}/",
      refundsList: "{{ route('v2.billing.refunds') }}",
      patientRefundableBills: "{{ url('v2/billing/patient-refundable-bills') }}/",
      saveInitiateRefund: "{{ url('save-initiate-refund') }}/",
      issueRefund: "{{ url('issue-refund') }}/",
      approveRefund: "{{ url('approve-refund') }}/",
      rejectRefund: "{{ url('reject-refund') }}/",
      initiateRefundPage: "{{ url('initiate-refund') }}/",
      saveCancelBillItems: "{{ route('bills.save_cancel_bill_items') }}",
      unsettleBill: "{{ route('patient.bill_unsettle_mark') }}",
      getPatientVitals: "{{ url('v2/clinical/patient-vitals-logs') }}/",
      savePatientVitals: "{{ route('patient.save_vitals') }}",
      patientVisits: "{{ url('v2/registration/patient-visits') }}/"
    };
  </script>
  <script src="{{ asset('assets/js/v2/nav-shell.js') }}?v={{ file_exists(public_path('assets/js/v2/nav-shell.js')) ? filemtime(public_path('assets/js/v2/nav-shell.js')) : '1' }}" charset="utf-8"></script>
  <script src="{{ asset('assets/js/v2/vitals-modal.js') }}?v={{ time() }}" charset="utf-8"></script>
  <script src="{{ asset('assets/js/v2/opd-billing.js') }}?v={{ time() }}" charset="utf-8"></script>
</body>
</html>

