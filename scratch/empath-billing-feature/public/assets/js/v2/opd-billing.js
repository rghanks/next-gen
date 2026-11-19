/**
 * Empath HIMS - OPD Billing V2 Module
 * Dedicated modular JavaScript for OPD Billing & All Bills
 * Follows Pure jQuery standard and V2 Design System guidelines
 */
(function ($) {
  'use strict';

  // CSRF Setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  // State Management
  var state = {
    activeTab: 'new',
    patient: null,
    workflowStep: 1,
    visitPrepared: false,
    preparingVisit: false,
    savingBill: false,
    chargeRequest: 0,
    patientRequest: 0,
    billRequestId: null,
    billRequestPayload: null,
    doctor: null,
    consultation: null,
    addConsultation: false,
    refBy: '',
    services: [], // Array of { id, name, code, rate, qty, disc, discPct, isConsult }
    billDiscount: 0,
    billDiscountPct: false,
    payments: [{ mode: 'Cash', amt: '', txn: '' }],
    payAuto: true,
    remark: '',
    billId: null,
    draftDoctorId: null,
    isLoadingDraft: false,
    consultPrimaryId: null,
    regPrimaryId: null,
    // All bills pagination & filters
    allBills: {
      page: 1,
      size: 10,
      total: 0,
      bills: [],
      summary: null,
      activeMenuNo: null
    },
    // Refunds module state
    refunds: {
      page: 1,
      size: 10,
      total: 0,
      data: [],
      summary: null,
      patient: null,
      bills: [],
      selectedBills: [],
      mode: 'Cash',
      amount: 0,
      reason: 'Doctor unavailable',
      remarks: '',
      txn: '',
      origin: null
    },
    // Bill edit & cancellation state
    editing: {
      active: false,
      billId: null,
      billNo: null,
      billData: null,
      mode: null
    }
  };

  window.billingState = state;
  window.state = state;

  var I_EDIT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';
  var I_RCPT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2Z"/><path d="M9 8h6M9 12h6"/></svg>';
  var I_CANCEL = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8.5 15.5l7-7"/></svg>';
  var I_BACK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10h12a5 5 0 0 1 0 10h-5"/><path d="M7 6l-4 4 4 4"/></svg>';
  var PRINT_IC = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 9V3h10v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M7 14h10v7H7z"/></svg>';
  var WA_IC = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2.2a9.7 9.7 0 0 0-8.3 14.8L2.4 21.6l4.7-1.2A9.7 9.7 0 1 0 12 2.2zm0 17.7a8 8 0 0 1-4.1-1.1l-.3-.2-2.8.7.8-2.7-.2-.3A8 8 0 1 1 12 19.9zm4.4-6c-.2-.1-1.4-.7-1.7-.8-.2-.1-.4-.1-.5.1l-.8 1c-.1.2-.3.2-.5.1a6.6 6.6 0 0 1-3.3-2.9c-.2-.4.3-.4.8-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.5-.4h-.5a.9.9 0 0 0-.7.3 2.8 2.8 0 0 0-.9 2.1 4.9 4.9 0 0 0 1 2.6 11.2 11.2 0 0 0 4.3 3.8c1.6.7 2.2.7 3 .6.5-.1 1.4-.6 1.6-1.2.2-.6.2-1.1.1-1.2l-.5-.2z"/></svg>';

  var TXN_HINT = {
    Cash: 'Not needed for cash',
    UPI: 'UPI reference ID',
    Card: 'Last 4 digits / Auth code',
    Cheque: 'Cheque number & bank',
    'Bank transfer': 'UTR / Reference number'
  };

  // Exact mapping:
  // cash      => Cash
  // upi       => UPI
  // card      => Card
  // cheque    => Cheque
  // neft_rtgs => Bank transfer
  function backendToFrontendMode(backendMode) {
    if (!backendMode) return 'Cash';
    var key = String(backendMode).trim().toLowerCase();
    if (key === 'upi') return 'UPI';
    if (key === 'card') return 'Card';
    if (key === 'cheque') return 'Cheque';
    if (key === 'neft_rtgs' || key === 'bank transfer' || key === 'bank_transfer') return 'Bank transfer';
    return 'Cash';
  }

  function frontendToBackendMode(frontendMode) {
    if (!frontendMode) return 'cash';
    var key = String(frontendMode).trim();
    if (key === 'UPI' || key.toLowerCase() === 'upi') return 'upi';
    if (key === 'Card' || key.toLowerCase() === 'card') return 'card';
    if (key === 'Cheque' || key.toLowerCase() === 'cheque') return 'cheque';
    if (key === 'Bank transfer' || key.toLowerCase() === 'bank transfer' || key.toLowerCase() === 'neft_rtgs') return 'neft_rtgs';
    return 'cash';
  }

  var MODE_TO_BACKEND = {
    Cash: 'cash',
    UPI: 'upi',
    Card: 'card',
    Cheque: 'cheque',
    'Bank transfer': 'neft_rtgs'
  };

  // Utilities
  function esc(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, function (m) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
    });
  }

  function inr(n, dec) {
    n = parseFloat(n) || 0;
    return '₹' + n.toLocaleString('en-IN', {
      minimumFractionDigits: dec !== undefined ? dec : 2,
      maximumFractionDigits: dec !== undefined ? dec : 2
    });
  }

  function digits(s) {
    return String(s || '').replace(/\D/g, '');
  }

  function discAmt(v, pct, gross) {
    var n = parseFloat(String(v || '').replace(/[^\d.]/g, '')) || 0;
    if (pct) n = Math.round(gross * Math.min(Math.max(n, 0), 100) / 100);
    return Math.max(0, Math.min(n, gross));
  }

  function discEq(item) {
    var gross = item.rate * item.qty;
    var d = discAmt(item.disc, item.discPct, gross);
    if (item.discPct && d > 0) return '= ' + inr(d, 0);
    return '';
  }

  function canDiscount() {
    return true;
  }

  function canEditBill() {
    var perms = window.USER_PERMISSIONS || {};
    return !!(perms.opd_billing && perms.opd_billing.can_update);
  }

  function canCancelBill() {
    var perms = window.USER_PERMISSIONS || {};
    return !!(perms.bill_cancel && perms.bill_cancel.can_update);
  }

  function canCreateRefund() {
    var perms = window.USER_PERMISSIONS || {};
    return !!(perms.refund && perms.refund.can_create);
  }

  function canUpdateRefund() {
    var perms = window.USER_PERMISSIONS || {};
    return !!(perms.refund && perms.refund.can_update);
  }

  function canInitiateRefund() {
    return canCreateRefund() || canUpdateRefund();
  }

  function canIssueRefund() {
    return canCreateRefund() || canUpdateRefund();
  }

  function canApproveRefund() {
    return canUpdateRefund();
  }

  function canEditRefundReceipt() {
    return canUpdateRefund() || canEditBill();
  }

  function canRefund() {
    return canInitiateRefund();
  }

  function canReadRefunds() {
    var perms = window.USER_PERMISSIONS || {};
    return !!(perms.refund && (perms.refund.can_read || perms.refund.can_create || perms.refund.can_update));
  }

  function docName(d) {
    if (!d) return '—';
    var n = (typeof d === 'string' ? d : (d.name || '')).trim();
    if (!n || n === '—' || n === '-') return '—';
    var clean = n.replace(/^(dr\.?\s*)+/i, '').trim();
    return clean ? 'Dr. ' + clean : '—';
  }

  function getBillDoctorName(bill) {
    if (!bill) return '—';
    var raw = bill.doctor || bill.doctor_name || (state.doctor ? state.doctor.name : '');
    var formatted = docName(raw);
    if (formatted && formatted !== '—') return formatted;
    if (bill.doctor_id && window.INITIAL_DATA && window.INITIAL_DATA.doctors) {
      var found = null;
      $.each(window.INITIAL_DATA.doctors, function (i, d) {
        if (String(d.id) === String(bill.doctor_id)) {
          found = d;
          return false;
        }
      });
      if (found) return docName(found);
    }
    return raw ? (typeof raw === 'string' ? raw : (raw.name || '—')) : '—';
  }

  function snack(msg) {
    var $snack = $('#snackbar');
    $snack.text(msg).removeAttr('hidden').stop(true, true).fadeIn(150);
    clearTimeout(snack._timer);
    snack._timer = setTimeout(function () {
      $snack.fadeOut(200, function () {
        $snack.attr('hidden', true);
      });
    }, 3500);
  }

  // Open browser print preview directly on the same page without navigating or rendering in page layout
  function printUrlOnSamePage(url) {
    if (!url || url === '#') {
      snack('No printable document available.');
      return false;
    }
    snack('Opening print preview...');
    var oldIframe = document.getElementById('print-doc-frame');
    if (oldIframe && oldIframe.parentNode) {
      oldIframe.parentNode.removeChild(oldIframe);
    }
    var iframe = document.createElement('iframe');
    iframe.id = 'print-doc-frame';
    iframe.style.position = 'fixed';
    iframe.style.left = '-9999px';
    iframe.style.top = '-9999px';
    iframe.style.width = '1px';
    iframe.style.height = '1px';
    iframe.style.border = 'none';
    iframe.style.opacity = '0.01';
    iframe.onload = function () {
      try {
        setTimeout(function () {
          if (iframe.contentWindow) {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
          }
        }, 250);
      } catch (e) {
        console.error('Print preview error:', e);
        try { window.open(url, '_blank'); } catch (err) { }
      }
    };
    iframe.src = url;
    document.body.appendChild(iframe);
    return false;
  }
  window.printUrlOnSamePage = printUrlOnSamePage;

  // --- TAB NAVIGATION ---
  function switchTab(tabId, updateUrl) {
    if (!tabId) tabId = 'new';
    if (tabId === 'ref' && !canReadRefunds()) {
      tabId = $('#tab-new').length ? 'new' : 'all';
    }
    state.activeTab = tabId;
    closeRowMenu();

    try {
      sessionStorage.setItem('opd_billing_active_tab', tabId);
    } catch (e) { }

    if (updateUrl !== false && window.history && window.history.replaceState) {
      try {
        var url = new URL(window.location.href);
        url.searchParams.set('tab', tabId);
        window.history.replaceState({ tab: tabId }, '', url.toString());
      } catch (e) { }
    }

    if (tabId === 'new') {
      $('#tab-new').attr('aria-selected', 'true');
      $('#tab-all').attr('aria-selected', 'false');
      $('#tab-ref').attr('aria-selected', 'false');
      $('#view-new').removeAttr('hidden');
      $('#view-all').attr('hidden', true);
      $('#view-ref').attr('hidden', true);
    } else if (tabId === 'all') {
      $('#tab-new').attr('aria-selected', 'false');
      $('#tab-all').attr('aria-selected', 'true');
      $('#tab-ref').attr('aria-selected', 'false');
      $('#view-new').attr('hidden', true);
      $('#view-all').removeAttr('hidden');
      $('#view-ref').attr('hidden', true);
      if (!state.allBills.bills.length) {
        fetchBills();
      }
    } else if (tabId === 'ref') {
      $('#tab-new').attr('aria-selected', 'false');
      $('#tab-all').attr('aria-selected', 'false');
      $('#tab-ref').attr('aria-selected', 'true');
      $('#view-new').attr('hidden', true);
      $('#view-all').attr('hidden', true);
      $('#view-ref').removeAttr('hidden');
      if (!state.refunds.data.length) {
        fetchRefunds(1);
      }
    }
  }

  // ══════════════════════════════════════════════════════════════════════
  // ════════════════ COMMON PATIENT SEARCH & ADVANCED SEARCH ══════════════
  // ══════════════════════════════════════════════════════════════════════

  function initPatientSearch(cfg) {
    var $container = $(cfg.container);
    if (!$container.length) return;

    var $form = $(cfg.form || ($container.find('form').first()));
    var $input = $(cfg.input);
    var $advBtn = $(cfg.advBtn);
    var $advPanel = $(cfg.advPanel);
    var $advName = $(cfg.advName);
    var $advPhone = $(cfg.advPhone);
    var $advGender = $(cfg.advGender);
    var $advCount = $(cfg.advCount);
    var $advClear = $(cfg.advClear);
    var $results = $(cfg.results);

    var searchTimer = null;
    var resList = [];
    var resIdx = -1;

    function isAdvOpen() {
      return $advPanel.length && !$advPanel.is(':hidden');
    }

    function getAdvCriteria() {
      if (!isAdvOpen()) return { name: '', phone: '', gender: '', n: 0 };
      var name = ($advName.val() || '').trim();
      var phone = ($advPhone.val() || '').trim();
      var gender = ($advGender.val() || '').trim();
      var n = (name ? 1 : 0) + (phone ? 1 : 0) + (gender ? 1 : 0);
      return { name: name, phone: phone, gender: gender, n: n };
    }

    function updateAdvBadge() {
      var a = getAdvCriteria();
      if ($advCount.length) {
        if (a.n > 0) {
          $advCount.text(' · ' + a.n).removeAttr('hidden');
        } else {
          $advCount.text('').attr('hidden', true);
        }
      }
    }

    function clearAdvFilters() {
      if ($advName.length) $advName.val('');
      if ($advPhone.length) $advPhone.val('');
      if ($advGender.length) $advGender.val('');
      updateAdvBadge();
    }

    function closeResults() {
      resList = [];
      resIdx = -1;
      $results.attr('hidden', true).empty();
    }

    function renderList(patients) {
      resList = patients || [];
      resIdx = -1;

      if (!resList.length) {
        $results.html('<p class="res-none">Nobody matches that.</p>').removeAttr('hidden');
        return;
      }

      var html = resList.map(function (p, i) {
        var name = p.name || ((p.title ? p.title + ' ' : '') + (p.first || p.first_name || '') + ' ' + (p.last || p.last_name || '')).trim() || 'Patient';
        var uhid = p.uhid || p.id || '';
        var age = p.age ? (p.age + ' yrs') : (p.dob ? p.dob : '');
        var gender = p.gender || p.sex || '';
        var mobile = p.mobile || p.mobile_no || '';
        var abha = p.abha || p.abha_number || '';

        var metaParts = [];
        if (uhid) metaParts.push('UHID ' + uhid);
        if (age) metaParts.push(age);
        if (gender) metaParts.push(gender);
        if (mobile) metaParts.push('+91 ' + mobile);
        if (abha) metaParts.push('ABHA ' + abha);

        var lastSeen = p.last_seen;
        var lastPill = (!lastSeen || lastSeen === '—' || lastSeen === 'Never seen')
          ? 'Never seen'
          : (lastSeen === 'Today'
            ? 'Seen today'
            : (/^last/i.test(lastSeen) ? lastSeen : 'Last ' + lastSeen));

        return '<button type="button" class="res" role="option" aria-selected="false" data-idx="' + i + '">'
          + '<span>'
          + '<span class="r-name">' + esc(name) + '</span>'
          + '<span class="r-meta">' + esc(metaParts.join(' · ')) + '</span>'
          + '</span>'
          + '<span class="pill">' + esc(lastPill) + '</span>'
          + '</button>';
      }).join('');

      $results.html(html).removeAttr('hidden');
    }

    function doSearch(immediate) {
      clearTimeout(searchTimer);

      var execute = function () {
        var q = $input.val() ? $input.val().trim() : '';
        var adv = getAdvCriteria();

        if (!q && !adv.n) {
          closeResults();
          return;
        }

        var searchParams = {
          q: q || (adv.phone || adv.name),
          term: q || (adv.phone || adv.name),
          name: adv.name,
          phone: adv.phone,
          gender: adv.gender
        };

        $.ajax({
          url: window.ROUTES.search,
          type: 'GET',
          data: searchParams,
          success: function (data) {
            var patients = (data && data.patients) ? data.patients : (Array.isArray(data) ? data : []);
            renderList(patients);
          },
          error: function () {
            closeResults();
          }
        });
      };

      if (immediate) {
        execute();
      } else {
        searchTimer = setTimeout(execute, 250);
      }
    }

    function moveRes(step) {
      if (!resList.length) return;
      resIdx = (resIdx + step + resList.length) % resList.length;
      var $resButtons = $results.find('.res');
      $resButtons.each(function (i) {
        var isCur = (i === resIdx);
        $(this).attr('aria-selected', isCur ? 'true' : 'false');
        if (isCur) {
          this.scrollIntoView({ block: 'nearest' });
        }
      });
    }

    function pickCurrent() {
      if (resIdx > -1 && resList[resIdx]) {
        pickPatient(resList[resIdx]);
      }
    }

    function pickPatient(patient) {
      closeResults();
      $input.val('');
      clearAdvFilters();
      if (isAdvOpen()) {
        $advPanel.attr('hidden', true);
        $advBtn.attr('aria-expanded', 'false');
      }
      if (typeof cfg.onSelect === 'function') {
        cfg.onSelect(patient);
      }
    }

    // Bind event listeners with namespace
    $input.off('input.ps keydown.ps')
      .on('input.ps', function () { doSearch(false); })
      .on('keydown.ps', function (e) {
        if (e.key === 'ArrowDown' && resList.length) {
          e.preventDefault();
          moveRes(1);
        } else if (e.key === 'ArrowUp' && resList.length) {
          e.preventDefault();
          moveRes(-1);
        } else if (e.key === 'Enter') {
          if (resIdx > -1 && resList.length) {
            e.preventDefault();
            pickCurrent();
          }
        } else if (e.key === 'Escape') {
          closeResults();
        }
      });

    $form.off('submit.ps').on('submit.ps', function (e) {
      e.preventDefault();
      if (resIdx > -1 && resList.length) {
        pickCurrent();
      } else {
        doSearch(true);
      }
    });

    $advBtn.off('click.ps').on('click.ps', function () {
      var open = !$advPanel.is(':hidden');
      if (open) {
        var had = getAdvCriteria().n;
        clearAdvFilters();
        $advPanel.attr('hidden', true);
        $advBtn.attr('aria-expanded', 'false');
        if (had) {
          doSearch(true);
        }
      } else {
        $advPanel.removeAttr('hidden');
        $advBtn.attr('aria-expanded', 'true');
        if ($advName.length) $advName.focus();
      }
    });

    $advClear.off('click.ps').on('click.ps', function () {
      clearAdvFilters();
      closeResults();
      if ($advName.length) $advName.focus();
    });

    $advPanel.find('input, select').off('input.ps change.ps')
      .on('input.ps change.ps', function () {
        updateAdvBadge();
      });

    $results.off('click.ps', '.res')
      .on('click.ps', '.res', function (e) {
        e.preventDefault();
        var idx = parseInt($(this).data('idx'), 10);
        if (!isNaN(idx) && resList[idx]) {
          pickPatient(resList[idx]);
        }
      });

    var nsSuffix = (cfg.container || '').replace(/[^a-zA-Z0-9]/g, '_');
    $(document).off('click.ps_' + nsSuffix).on('click.ps_' + nsSuffix, function (e) {
      if (!$(e.target).closest(cfg.container).length) {
        $results.attr('hidden', true);
      }
    });

    return {
      close: closeResults,
      search: doSearch,
      clearAdv: clearAdvFilters
    };
  }

  function selectPatient(p) {
    if (p) {
      p.mobile_no = p.mobile_no || p.mobile;
      p.mobile = p.mobile || p.mobile_no;
    }
    state.patient = p;
    $('#results').attr('hidden', true).empty();
    $('#q').val('');

    var name = p.name || ((p.first_name || '') + ' ' + (p.last_name || '')).trim() || 'Patient';
    var ageStr = p.age ? p.age + ' yrs' : '';
    var catName = (window.INITIAL_DATA.categories && window.INITIAL_DATA.categories[p.category_id || p.catId]) ? window.INITIAL_DATA.categories[p.category_id || p.catId] : 'General';
    var metaHtml = '<span><b>UHID:</b> ' + esc(p.uhid) + '</span> · '
      + '<span>' + esc([ageStr, p.gender].filter(Boolean).join(' / ')) + '</span> · '
      + '<span>' + esc(catName) + '</span> · '
      + '<span>+91 ' + esc(p.mobile_no || p.mobile || '—') + '</span>';

    $('#pt-name').text(name).attr('data-patient-id', p.id);
    $('#pt-meta').html(metaHtml);
    $('#pt-doc-badge').attr('hidden', true).hide();
    $('#sec-visit, .sec-v, #sec-doc').show();

    $('#ob-empty').attr('hidden', true);
    $('#ob-paid').attr('hidden', true);
    $('#ob-form').removeAttr('hidden');
    $('#ob-bar').removeAttr('hidden');

    resetBillForm();
    populateDoctors();

    setWorkflowStep(2);
    $(document).trigger('billing:patient-selected');
    snack(name + ' · ' + p.uhid + ' selected. Create the visit to continue.');
  }

  function loadDraftBillData(d, onDone) {
    state.isLoadingDraft = true;
    state.billId = d.bill_id;
    state.visitId = d.visit_id || null;
    state.draftDoctorId = d.doctor_id ? String(d.doctor_id) : null;
    state.consultPrimaryId = d.consult_primary_id || null;
    state.regPrimaryId = d.reg_primary_id || null;
    $('#consult').prop('checked', !!d.consult_primary_id);

    if (d.consult_charge !== undefined && d.consult_charge !== null) {
      state.draftConsultCharge = parseFloat(d.consult_charge) || 0;
    }

    if (d.bill_discount && parseFloat(d.bill_discount) > 0) {
      $('#bdisc').val(parseFloat(d.bill_discount));
      state.billDiscountPct = !!d.bill_discount_pct;
      $('[data-bunit="inr"]').attr('aria-pressed', state.billDiscountPct ? 'false' : 'true');
      $('[data-bunit="pct"]').attr('aria-pressed', state.billDiscountPct ? 'true' : 'false');
    } else {
      $('#bdisc').val(!canDiscount() ? '0.00' : '');
      state.billDiscount = 0;
      state.billDiscountPct = false;
      $('[data-bunit="inr"]').attr('aria-pressed', 'true');
      $('[data-bunit="pct"]').attr('aria-pressed', 'false');
    }

    if (d.remarks) {
      $('#remark').val(d.remarks);
    } else {
      $('#remark').val('');
    }

    // Reset services list for draft extras
    state.services = [];

    (d.extras || []).forEach(function (x) {
      var sId = x.id;
      var catalog = window.INITIAL_DATA.services || [];
      var sObj = catalog.find(function (c) { return String(c.id || c.service_id) === String(sId); });
      if (!sObj) {
        sObj = {
          id: sId,
          service_id: sId,
          service_name: x.name || ('Service #' + sId),
          name: x.name || ('Service #' + sId),
          charges: x.price !== undefined ? x.price : 0
        };
        catalog.push(sObj);
      }

      state.services.push({
        primary_id: x.primary_id || null,
        id: 'svc_' + sId,
        service_id: sId,
        name: x.name || (sObj.service_name || sObj.name),
        code: x.code || '',
        cat: '',
        rate: (x.price !== undefined && x.price !== null && x.price !== '') ? (+x.price || 0) : getServiceRate(sObj),
        is_editable: (x.is_editable !== undefined) ? !!x.is_editable : isServiceRateEditable(sObj, (state.patient && (state.patient.category_id || state.patient.catId)) || 1),
        qty: +x.qty || 1,
        disc: String(x.disc || ''),
        discPct: !!x.discPct,
        isConsult: false
      });
    });

    if (d.doctor_id) {
      $('#doc').val(d.doctor_id);
      var docs = window.INITIAL_DATA.doctors || [];
      for (var i = 0; i < docs.length; i++) {
        if (String(docs[i].id) === String(d.doctor_id)) {
          state.doctor = docs[i];
          break;
        }
      }
      var dSpec = state.doctor ? (state.doctor.spec || state.doctor.specialization || '') : '';
      if (dSpec) $('#spec').val(dSpec);
    }

    if (d.ref_by) {
      state.refBy = String(d.ref_by);
      paintBillingRefby();
    }

    setRegistrationItem(parseFloat(d.reg_charge) || 0, d.reg_primary_id);
    var registration = state.services.find(function (s) { return s.isRegistration; });
    if (registration) {
      registration.disc = String(d.reg_disc || '');
      registration.discPct = !!d.reg_disc_pct;
    }
    if (d.consult_primary_id) {
      state.services.unshift({
        primary_id: d.consult_primary_id, id: 'consult_draft', service_id: window.INITIAL_DATA.consult_service_id,
        name: 'Consultation', rate: parseFloat(d.consult_charge) || 0, qty: 1,
        disc: String(d.consult_disc || ''), discPct: !!d.consult_disc_pct, isConsult: true
      });
    }
    renderBillTable();
    calculateTotals();
    snack((state.patient ? state.patient.name || state.patient.first_name || 'Patient' : 'Patient') + ' · draft bill #' + d.bill_id + ' loaded with saved services.');

    if (d.doctor_id) {
      fetchDoctorCharges(d.doctor_id, function (chargesAvailable) {
        state.isLoadingDraft = false;
        if (!chargesAvailable && $('#billing-visit-kind').val() === '1') return;
        if (onDone) onDone();
      });
    } else {
      state.isLoadingDraft = false;
      if (onDone) onDone();
    }
  }

  function changePatient() {
    state.patient = null;
    resetBillForm();
    setWorkflowStep(1);
    $('#ob-form').attr('hidden', true);
    $('#ob-paid').attr('hidden', true);
    $('#ob-bar').attr('hidden', true);
    $('#ob-empty').removeAttr('hidden');
    $('#adv').attr('hidden', true);
    $('#adv-btn').attr('aria-expanded', 'false');
    $('#adv-count').text('').attr('hidden', true);
    $('#a-name, #a-phone, #a-gender').val('');
    $('#q').val('').focus();
  }

  function workflowEditing() {
    return state.editing && state.editing.active;
  }

  function requestId() {
    return '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, function (c) {
      return (c ^ window.crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16);
    });
  }

  function setWorkflowStep(step) {
    state.workflowStep = step;
    $('#billing-patient').attr('hidden', step !== 1);
    $('#finder').attr('hidden', step !== 1);
    $('#billing-patient-hint').removeAttr('hidden');
    if (step === 1) $('#billing-register').attr('hidden', true);
    $('#sec-visit').toggle(step === 2);
    $('#sec-bill').toggle(step === 3);
    $('#billing-visit-summary').attr('hidden', step !== 3);
    $('#ob-bar').attr('hidden', step !== 3);
  }

  function invalidateVisit(clearBill) {
    if (workflowEditing()) return;
    state.isLoadingDraft = false;
    state.visitPrepared = false;
    state.patientRequest++;
    if (clearBill) {
      state.visitId = null;
      state.billId = null;
      state.consultPrimaryId = null;
      state.regPrimaryId = null;
      state.services.forEach(function (s) { s.primary_id = null; });
    }
    if (state.patient) setWorkflowStep(2);
  }

  function setRegistrationItem(amount, primaryId) {
    state.services = state.services.filter(function (s) { return !s.isRegistration; });
    if (amount <= 0) return;
    var serviceId = window.INITIAL_DATA.reg_service_id;
    if (!serviceId) {
      $('#billing-visit-error').text('Registration service is not configured. Ask an administrator to configure it before billing.');
      return;
    }
    state.services.push({
      id: 'registration', service_id: serviceId, primary_id: primaryId || null,
      name: 'Registration', rate: amount, qty: 1, disc: '', discPct: false,
      isConsult: false, isRegistration: true, is_editable: false
    });
  }

  function updateVisitKind() {
    var clinical = $('#billing-visit-kind').val() === '1';
    $('#sec-visit [data-field="charge"], #sec-visit .ob-consult').toggle(clinical);
    $('#consult').prop('checked', clinical).prop('disabled', !clinical || !state.consultation);
    updateConsultationItem();
  }

  function prepareBillingVisit() {
    if (!state.patient || state.preparingVisit) return;
    var clinical = $('#billing-visit-kind').val() === '1';
    if (!state.doctor || !$('#doc').val()) {
      $('#billing-visit-error').text('Select the doctor for this visit.');
      $('#doc').trigger('focus');
      return;
    }
    if (clinical && (!state.consultation || $('#charge').is(':disabled'))) {
      $('#billing-visit-error').text('Select an available consultation before creating the visit.');
      $('#charge').trigger('focus');
      return;
    }
    var token = ++state.patientRequest;
    var patientId = state.patient.id || state.patient.uhid;
    var $controls = $('#billing-start, #pt-change, #billing-visit-kind, #doc, #spec, #ref_by, #charge, #consult');
    var disabled = $controls.map(function () { return this.disabled; }).get();
    state.preparingVisit = true;
    $controls.prop('disabled', true);
    $('#billing-visit-error').empty();
    $.ajax({
      url: window.ROUTES.prepareBillingVisit,
      type: 'POST',
      data: {
        patient_id: patientId, doctor_id: state.doctor.id,
        doctor_charges: clinical && state.consultation ? state.consultation.amt : 0,
        add_visit: clinical ? 1 : 0, ref_by: state.refBy || '', visit_id: state.visitId || ''
      },
      success: function (resp) {
        if (token !== state.patientRequest) return;
        if (!resp.success || !resp.visit_id) {
          $('#billing-visit-error').text(resp.message || 'The visit could not be saved.');
          return;
        }
        state.visitId = resp.visit_id;
        var showBilling = function () {
          if (token !== state.patientRequest) return;
          state.visitPrepared = true;
          $('#billing-visit-description').text('Visit ' + (resp.visit_no || resp.visit_id) + ' · ' + docName(state.doctor) + ' · ' + (clinical ? (state.consultation ? state.consultation.name : 'Doctor consultation') : 'Services only'));
          setWorkflowStep(3);
          renderBillTable();
          calculateTotals();
          $('#svc-q').trigger('focus');
        };
        if (resp.draft_bill_id) {
          if (String(state.billId) === String(resp.draft_bill_id)) {
            showBilling();
            return;
          }
          $.ajax({
            url: window.ROUTES.draftBill + resp.draft_bill_id,
            success: function (draftResp) {
              if (token !== state.patientRequest) return;
              if (draftResp.success && draftResp.draft_bill) loadDraftBillData(draftResp.draft_bill, showBilling);
              else $('#billing-visit-error').text('The draft could not be loaded. Retry to resume this visit.');
            },
            error: function () {
              if (token === state.patientRequest) $('#billing-visit-error').text('The visit was saved, but its draft could not be loaded. Retry to resume it.');
            }
          });
        } else {
          state.billId = null;
          if (resp.registration_charge > 0 && !window.INITIAL_DATA.reg_service_id) {
            $('#billing-visit-error').text('Registration service is not configured. Ask an administrator to configure it before billing.');
            return;
          }
          setRegistrationItem(parseFloat(resp.registration_charge) || 0);
          showBilling();
        }
      },
      error: function (xhr) {
        if (token !== state.patientRequest) return;
        var resp = xhr.responseJSON || {};
        $('#billing-visit-error').text(resp.message || 'The visit could not be saved. Your selections are kept; retry to continue.');
      },
      complete: function () {
        state.preparingVisit = false;
        $controls.each(function (i) { this.disabled = disabled[i]; });
      }
    });
  }

  // --- REFERRAL DOCTORS (REF. BY) ---
  var refUsersList = (window.INITIAL_DATA && Array.isArray(window.INITIAL_DATA.ref_users)) ? window.INITIAL_DATA.ref_users.slice() : [];

  function paintBillingRefby() {
    var $refBy = $('#ref_by');
    if (!$refBy.length) return;
    var isEnabled = (window.INITIAL_DATA && window.INITIAL_DATA.is_ref_by_enabled !== undefined) ? window.INITIAL_DATA.is_ref_by_enabled : true;
    var $wrap = $('#f-ref-by');
    $wrap.toggle(isEnabled);
    if (!isEnabled) {
      $wrap.attr('hidden', true);
    } else {
      $wrap.removeAttr('hidden');
    }
    var $grid = $wrap.closest('.grid4');
    if ($grid.length) {
      $grid.toggleClass('has-3cols', !isEnabled);
    }
    if (!isEnabled) return;

    $refBy.empty().append('<option value="">— Select Ref. By —</option>');
    refUsersList.forEach(function (r) {
      var lbl = r.name + (r.mobile_no ? (' (' + r.mobile_no + ')') : '');
      $refBy.append('<option value="' + esc(r.id) + '">' + esc(lbl) + '</option>');
    });

    var canAdd = (window.USER_PERMISSIONS && window.USER_PERMISSIONS.add_referred_by && window.USER_PERMISSIONS.add_referred_by.can_create);
    if (canAdd) {
      $refBy.append('<option value="__new__">+ Add new referring doctor…</option>');
    }

    if (state.refBy) {
      $refBy.val(String(state.refBy));
    }
  }

  function openAddRefModal() {
    var $dlg = $('#add-ref-dlg');
    if (!$dlg.length) return;
    $('#ref-doc-name').val('');
    $('#ref-doc-contact').val('');
    $('#ref-doc-name-err').hide();
    $('#ref-doc-contact-err').hide();
    $dlg.removeAttr('hidden');
    setTimeout(function () { $('#ref-doc-name').focus(); }, 100);
  }

  function closeAddRefModal() {
    var $dlg = $('#add-ref-dlg');
    if ($dlg.length) $dlg.attr('hidden', true);
    var $refBy = $('#ref_by');
    if ($refBy.length && $refBy.val() === '__new__') {
      $refBy.val(state.refBy || '');
    }
  }

  function submitAddRef() {
    var name = ($('#ref-doc-name').val() || '').trim();
    var contact = ($('#ref-doc-contact').val() || '').trim();
    var cleanContact = contact.replace(/\D/g, '');
    var $nameErr = $('#ref-doc-name-err');
    var $contErr = $('#ref-doc-contact-err');

    if (!name) {
      $nameErr.text('Please enter doctor or referrer name.').show();
      $('#ref-doc-name').focus();
      return;
    }
    $nameErr.hide();

    if (!cleanContact || cleanContact.length !== 10) {
      $contErr.text('Please enter a valid 10-digit contact number.').show();
      $('#ref-doc-contact').focus();
      return;
    }
    $contErr.hide();

    var $btn = $('#add-ref-submit');
    $btn.prop('disabled', true);

    $.ajax({
      url: window.ROUTES.addRefList,
      type: 'POST',
      data: {
        _token: $('meta[name="csrf-token"]').attr('content'),
        name: name,
        contact: cleanContact
      },
      success: function (res) {
        if (res && res.status === 'success') {
          var newDoc = { id: res.id, name: res.name || name, mobile_no: cleanContact };
          refUsersList.push(newDoc);
          state.refBy = String(res.id);
          paintBillingRefby();
          closeAddRefModal();
          snack('“' + (res.name || name) + '” added to referral doctor list.');
        } else {
          var errTxt = (res && res.message) ? res.message : 'Failed to add referring doctor.';
          if (/contact|mobile/i.test(errTxt)) {
            $contErr.text(errTxt).show();
            $('#ref-doc-contact').focus();
          } else {
            $nameErr.text(errTxt).show();
          }
          snack(errTxt);
        }
      },
      error: function (xhr) {
        var errTxt = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to add referring doctor.';
        if (/contact|mobile/i.test(errTxt)) {
          $contErr.text(errTxt).show();
          $('#ref-doc-contact').focus();
        } else {
          $nameErr.text(errTxt).show();
        }
        snack(errTxt);
      },
      complete: function () {
        $btn.prop('disabled', false);
      }
    });
  }

  // --- DOCTOR & CONSULTATION ---
  function populateDoctors() {
    var docs = window.INITIAL_DATA.doctors || [];
    var specs = window.INITIAL_DATA.specs || [];

    var $spec = $('#spec').empty().append('<option value="">All</option>');
    specs.forEach(function (s) {
      $spec.append('<option value="' + esc(s) + '">' + esc(s) + '</option>');
    });

    var $doc = $('#doc').empty().append('<option value="">Choose a doctor</option>');
    docs.forEach(function (d) {
      var s = d.spec || d.specialization || '';
      $doc.append('<option value="' + esc(d.id) + '" data-spec="' + esc(s) + '">' + esc(docName(d)) + '</option>');
    });

    $('#charge').empty().append('<option value="">Choose a doctor first</option>').prop('disabled', true);
    $('#consult').prop('disabled', true);

    paintBillingRefby();
    populateDiscountAuthorizers();
  }

  function populateDiscountAuthorizers() {
    var $select = $('#discount_auth_by');
    if (!$select.length) return;
    var currentVal = $select.val();

    var users = [];
    if (window.INITIAL_DATA && Array.isArray(window.INITIAL_DATA.disc_auth_users) && window.INITIAL_DATA.disc_auth_users.length) {
      users = window.INITIAL_DATA.disc_auth_users;
    } else if (window.INITIAL_DATA && Array.isArray(window.INITIAL_DATA.doctors) && window.INITIAL_DATA.doctors.length) {
      users = window.INITIAL_DATA.doctors;
    }

    if (users.length) {
      $select.empty().append('<option value="">Select Auth By</option>');
      users.forEach(function (u) {
        var uid = u.id || '';
        var uname = u.name || ((u.first_name || '') + ' ' + (u.last_name || '')).trim() || ('User #' + uid);
        $select.append('<option value="' + esc(uid) + '">' + esc(uname) + '</option>');
      });
    }

    if (currentVal && $select.find('option[value="' + currentVal + '"]').length) {
      $select.val(currentVal);
    }
  }

  function setDoctorAndSpec(doctorId, doctorName, specName) {
    var docs = (window.INITIAL_DATA && window.INITIAL_DATA.doctors) ? window.INITIAL_DATA.doctors : [];
    var specs = (window.INITIAL_DATA && window.INITIAL_DATA.specs) ? window.INITIAL_DATA.specs : [];

    // 1. Identify doctor object in docs
    var docObj = null;
    if (doctorId) {
      $.each(docs, function (i, d) {
        if (String(d.id) === String(doctorId)) {
          docObj = d;
          return false;
        }
      });
    }

    // Determine target specialization
    var targetSpec = specName || (docObj ? (docObj.specialization || docObj.spec) : '');

    // 2. Populate and preselect Specialisation
    var $spec = $('#spec').empty().append('<option value="">All</option>');
    var specFound = false;
    specs.forEach(function (s) {
      if (targetSpec && String(s).toLowerCase() === String(targetSpec).toLowerCase()) {
        specFound = true;
        targetSpec = s;
      }
      $spec.append('<option value="' + esc(s) + '">' + esc(s) + '</option>');
    });
    if (targetSpec && !specFound) {
      $spec.append('<option value="' + esc(targetSpec) + '">' + esc(targetSpec) + '</option>');
    }
    $spec.val(targetSpec || '');

    // 3. Populate and preselect Doctor
    var $doc = $('#doc').empty().append('<option value="">Choose a doctor</option>');
    var doctorFound = false;
    docs.forEach(function (d) {
      var s = d.spec || d.specialization || '';
      if (!targetSpec || s === targetSpec) {
        var isSel = (doctorId && String(d.id) === String(doctorId));
        if (isSel) doctorFound = true;
        $doc.append('<option value="' + esc(d.id) + '" data-spec="' + esc(s) + '">' + esc(docName(d)) + '</option>');
      }
    });

    // If doctor not in the filtered list (e.g. inactive doctor from older bill)
    if (doctorId && !doctorFound) {
      var displayName = doctorName || (docObj ? docObj.name : ('Doctor #' + doctorId));
      if (displayName && !/^Dr/i.test(displayName)) displayName = '' + displayName;
      $doc.append('<option value="' + esc(doctorId) + '" data-spec="' + esc(targetSpec || '') + '">' + esc(displayName) + '</option>');
    }

    if (doctorId) {
      $doc.val(String(doctorId));
      state.doctor = docObj || { id: doctorId, name: doctorName || '', spec: targetSpec, specialization: targetSpec };
    } else {
      $doc.val('');
      state.doctor = null;
    }
  }

  function onSpecChange() {
    var chosenSpec = $('#spec').val();
    var docs = window.INITIAL_DATA.doctors || [];
    var $doc = $('#doc').empty().append('<option value="">Choose a doctor</option>');

    docs.forEach(function (d) {
      var s = d.spec || d.specialization || '';
      if (!chosenSpec || s === chosenSpec) {
        $doc.append('<option value="' + esc(d.id) + '" data-spec="' + esc(s) + '">' + esc(docName(d)) + '</option>');
      }
    });

    if (state.doctor && state.doctor.id) {
      if ($doc.find('option[value="' + esc(state.doctor.id) + '"]').length > 0) {
        $doc.val(String(state.doctor.id));
      }
    }

    onDocChange();
  }

  function onDocChange() {
    invalidateVisit(true);
    state.chargeRequest++;
    var docId = $('#doc').val();
    state.doctor = null;
    state.consultation = null;

    if (!docId) {
      $('#charge').empty().append('<option value="">Choose a doctor first</option>').prop('disabled', true);
      $('#consult').prop('disabled', true);
      if (state.billId) {
        state.billId = null;
        state.draftDoctorId = null;
        state.consultPrimaryId = null;
        state.regPrimaryId = null;
        (state.services || []).forEach(function (s) { s.primary_id = null; });
      }
      updateConsultationItem();
      return;
    }

    var docs = window.INITIAL_DATA.doctors || [];
    for (var i = 0; i < docs.length; i++) {
      if (String(docs[i].id) === String(docId)) {
        state.doctor = docs[i];
        break;
      }
    }

    // Sync spec if empty or different
    if (state.doctor && (state.doctor.specialization || state.doctor.spec)) {
      var sp = state.doctor.specialization || state.doctor.spec;
      if (!$('#spec').val() || $('#spec').val() !== sp) {
        if ($('#spec').find('option[value="' + esc(sp) + '"]').length > 0) {
          $('#spec').val(sp);
        }
      }
    }

    // Auto-match spec if not chosen
    var dSpec = state.doctor ? (state.doctor.spec || state.doctor.specialization || '') : '';
    if (dSpec && !$('#spec').val()) {
      $('#spec').val(dSpec);
    }

    fetchDoctorCharges(docId);
  }

  function fetchDoctorCharges(docId, onDone) {
    if (!docId) {
      if (typeof onDone === 'function') onDone();
      return;
    }

    var chargeToken = ++state.chargeRequest;
    $('#charge').empty().append('<option value="">Loading consultation charges...</option>').prop('disabled', true);
    $('#consult').prop('disabled', true);

    var catId = (state.patient && (state.patient.category_id || state.patient.catId)) || 1;
    var uhid = (state.patient && (state.patient.uhid || state.patient.id)) || 0;

    var baseUrl = (window.ROUTES && window.ROUTES.getConsultCharges) ? window.ROUTES.getConsultCharges : '/getconsultcharges';
    var cleanBase = baseUrl.replace(/\/+$/, '');
    var url = cleanBase + '/' + encodeURIComponent(docId) + '/' + encodeURIComponent(uhid);

    $.ajax({
      url: url,
      type: 'GET',
      data: { category_id: catId },
      success: function (res) {
        if (chargeToken !== state.chargeRequest) return;
        var $charge = $('#charge').empty();
        var charges = [];
        if (res && res.charges && Array.isArray(res.charges)) {
          charges = res.charges;
        } else if (Array.isArray(res)) {
          charges = res;
        }

        if (charges.length) {
          charges.forEach(function (c) {
            var name = c.comment || c.name || c.charge_name || 'Consultation';
            var amt = parseFloat(c.ch !== undefined ? c.ch : (c.charge || c.charges || 0)) || 0;
            var label = name + ' — ' + inr(amt, 0);
            $charge.append('<option value="' + esc(c.id) + '" data-amt="' + amt + '" data-name="' + esc(name) + '">' + esc(label) + '</option>');
          });

          // Smart default: if total_visit > 0 pick follow-up, else pick first visit
          var preferredIdx = 0;
          if (state.draftConsultCharge !== undefined && state.draftConsultCharge !== null) {
            charges.forEach(function (c, idx) {
              var amt = parseFloat(c.ch !== undefined ? c.ch : (c.charge || c.charges || 0)) || 0;
              if (amt === state.draftConsultCharge) preferredIdx = idx;
            });
            delete state.draftConsultCharge;
          } else if (res && res.total_visit && res.total_visit > 0) {
            charges.forEach(function (c, idx) {
              if (/follow/i.test(c.comment || '')) preferredIdx = idx;
            });
          } else {
            charges.forEach(function (c, idx) {
              if (/first|new/i.test(c.comment || '')) preferredIdx = idx;
            });
          }

          $charge.prop('disabled', false);
          $charge.prop('selectedIndex', preferredIdx);
          var $selectedOpt = $charge.find('option:selected');
          state.consultation = {
            id: $selectedOpt.val(),
            name: $selectedOpt.data('name') || 'Consultation',
            amt: parseFloat($selectedOpt.data('amt')) || 0
          };
          $('#consult').prop('disabled', $('#billing-visit-kind').val() === '0');
          updateConsultationItem();
        } else {
          state.consultation = null;
          $charge.append('<option value="">No consultation charges configured</option>').prop('disabled', true);
          $('#consult').prop('disabled', true);
          updateConsultationItem();
        }
        if (typeof onDone === 'function') onDone(charges.length > 0);
      },
      error: function () {
        if (chargeToken !== state.chargeRequest) return;
        state.consultation = null;
        $('#charge').empty().append('<option value="">Charges unavailable — select the doctor to retry</option>').prop('disabled', true);
        $('#consult').prop('disabled', true);
        updateConsultationItem();
        $('#billing-visit-error').text('Consultation charges could not be loaded. Select the doctor again to retry.');
        if (typeof onDone === 'function') onDone(false);
      }
    });
  }

  function onChargeChange() {
    invalidateVisit(false);
    var $opt = $('#charge').find('option:selected');
    if ($opt.val()) {
      state.consultation = {
        id: $opt.val(),
        name: $opt.data('name') || 'Consultation',
        amt: parseFloat($opt.data('amt')) || 0
      };
    } else {
      state.consultation = null;
    }
    updateConsultationItem();
  }

  function updateConsultationItem() {
    var hasConsult = $('#consult').is(':checked') && !$('#consult').is(':disabled');
    state.addConsultation = hasConsult;

    var oldConsult = state.services.find(function (s) { return s.isConsult; });
    var oldDisc = oldConsult ? oldConsult.disc : '';
    var oldDiscPct = oldConsult ? oldConsult.discPct : false;

    // Remove existing consultation item if present
    state.services = state.services.filter(function (s) { return !s.isConsult; });

    if (hasConsult && state.consultation) {
      state.services.unshift({
        primary_id: state.consultPrimaryId || null,
        id: 'consult_' + state.consultation.id,
        service_id: (window.INITIAL_DATA && window.INITIAL_DATA.consult_service_id) ? window.INITIAL_DATA.consult_service_id : null,
        name: (state.doctor ? docName(state.doctor) + ' - ' : '') + state.consultation.name,
        code: 'CONSULT',
        rate: state.consultation.amt,
        qty: 1,
        disc: oldDisc,
        discPct: oldDiscPct,
        isConsult: true
      });
    }

    renderBillTable();
    calculateTotals();
  }

  // --- SERVICE FINDER & RATE RESOLVER ---
  function serviceRate(s, catId) {
    if (!s) return 0;
    var p = s.price || s.prices || {};
    if (catId && p[catId] !== undefined) return +p[catId];
    if (p.default !== undefined) return +p.default;
    if (p.cash !== undefined) return +p.cash;
    for (var k in p) { if (p[k] !== undefined) return +p[k]; }
    return parseFloat(s.charges || s.rate || 0) || 0;
  }

  function getServiceRate(s) {
    var catId = (state.patient && (state.patient.category_id || state.patient.catId)) || 1;
    return serviceRate(s, catId);
  }

  function isServiceRateEditable(s, catId) {
    if (!s) return false;
    var e = s.is_editable || s.editable;
    if (typeof e === 'object' && e !== null) {
      if (catId && e[catId] !== undefined) return !!e[catId];
      if (e.default !== undefined) return !!e.default;
      if (e.cash !== undefined) return !!e.cash;
      for (var k in e) { if (e[k]) return true; }
      return false;
    }
    return !!e;
  }

  var svcHits = [], svcIdx = -1;

  function handleServiceSearch() {
    var rawQ = $('#svc-q').val().trim().toLowerCase();
    if (!rawQ) {
      svcHits = [];
      svcIdx = -1;
      closeSvc();
      return;
    }

    var catalog = window.INITIAL_DATA.services || [];
    var qNum = rawQ.replace(/[^\d]/g, '');

    svcHits = catalog.filter(function (s) {
      var idStr = String(s.id || s.service_id || '');
      var code = String(s.code || '').toLowerCase();
      var nameGrp = (String(s.name || s.service_name || '') + ' ' + String(s.group || '')).toLowerCase();
      return nameGrp.indexOf(rawQ) > -1
        || (qNum && idStr.indexOf(qNum) > -1)
        || (code && code.indexOf(rawQ.replace(/\s+/g, '')) > -1);
    }).slice(0, 20);

    svcIdx = svcHits.length ? 0 : -1;
    paintSvc();
  }

  function paintSvc() {
    var $ul = $('#svc-list'), $q = $('#svc-q');
    if (!$ul.length || !$q.length) return;
    $ul.removeAttr('hidden');
    $q.attr('aria-expanded', 'true');

    var catId = (state.patient && (state.patient.category_id || state.patient.catId)) || 1;

    if (!svcHits.length) {
      $ul.html('<li class="sv-none">No service matches "' + esc($q.val().trim()) + '".</li>');
      return;
    }

    var html = svcHits.map(function (s, i) {
      var sCode = s.code || ('#' + (s.id || s.service_id));
      var sId = s.id || s.service_id;
      var sName = s.name || s.service_name || ('Service #' + sId);
      var sGrp = s.group || s.cat_name || s.category_name || '';
      var rate = serviceRate(s, catId);

      return '<li role="option" id="svc-o' + i + '" data-svc="' + sId + '" data-idx="' + i + '" aria-selected="' + (i === svcIdx) + '">'
        + '<span class="sv-code">' + esc(sCode) + '</span>'
        + '<span class="sv-name">' + esc(sName) + '<span class="sv-grp">#' + esc(sId) + (sGrp ? ' · ' + esc(sGrp) : '') + '</span></span>'
        + '<span class="sv-rate">' + inr(rate, 0) + '</span>'
        + '</li>';
    }).join('');

    $ul.html(html);

    if (svcIdx > -1) {
      $q.attr('aria-activedescendant', 'svc-o' + svcIdx);
      var $active = $('#svc-o' + svcIdx);
      if ($active.length && $active[0].scrollIntoView) {
        $active[0].scrollIntoView({ block: 'nearest' });
      }
    }
  }

  function closeSvc() {
    var $ul = $('#svc-list'), $q = $('#svc-q');
    $ul.attr('hidden', true);
    $q.attr('aria-expanded', 'false').removeAttr('aria-activedescendant');
  }

  function resolvePatientInsuranceId() {
    var catId = (state.patient && (state.patient.category_id || state.patient.catId));
    if (catId && catId !== 'cash' && catId !== 'General') {
      return catId;
    }
    if (window.INITIAL_DATA && window.INITIAL_DATA.categories) {
      var cats = window.INITIAL_DATA.categories;
      for (var k in cats) {
        if (/cash/i.test(cats[k])) return k;
      }
      var firstKey = Object.keys(cats)[0];
      if (firstKey) return firstKey;
    }
    return 1;
  }

  var isFetchingSvcCharge = false;

  function pickSvc(sId) {
    if (isFetchingSvcCharge) return;

    var catalog = window.INITIAL_DATA.services || [];
    var s = catalog.find(function (item) { return String(item.id || item.service_id) === String(sId); });
    var sName = s ? (s.name || s.service_name || ('Service #' + sId)) : ('Service #' + sId);
    var iid = resolvePatientInsuranceId();
    var billid = state.billId || 1;

    var $q = $('#svc-q');
    closeSvc();
    isFetchingSvcCharge = true;
    $q.prop('disabled', true);

    var baseUrl = (window.ROUTES && window.ROUTES.getchargesDetails) ? window.ROUTES.getchargesDetails : '/getcharges_details/';
    if (baseUrl.charAt(baseUrl.length - 1) !== '/') baseUrl += '/';
    var url = baseUrl + sId + '/' + iid + '/' + billid;

    $.ajax({
      url: url,
      type: 'GET',
      dataType: 'json',
      success: function (res) {
        var charges = res && res.charges;
        var rawRate = (charges && (charges.charges !== undefined && charges.charges !== null && charges.charges !== ''))
          ? charges.charges
          : (charges && (charges.opd !== undefined && charges.opd !== null && charges.opd !== ''))
            ? charges.opd
            : null;

        if (charges === null || charges === undefined || rawRate === null || rawRate === undefined || rawRate === '') {
          alert('Charges are not set for "' + (res && res.service ? res.service : sName) + '". Please configure the service charge first.');
          return;
        }

        var rate = parseFloat(rawRate) || 0;
        var isEditable = (charges.is_editable !== undefined) ? !!charges.is_editable : false;

        if (!s) {
          s = {
            id: sId,
            service_id: sId,
            name: (res && res.service) || sName,
            group: ''
          };
        }

        addServiceItem(s, rate, isEditable);
      },
      error: function () {
        alert('Failed to fetch charges from server for "' + sName + '". Please check your connection.');
      },
      complete: function () {
        isFetchingSvcCharge = false;
        $q.prop('disabled', false).val('').focus();
      }
    });
  }

  function addServiceItem(s, overrideRate, overrideEditable) {
    var catId = resolvePatientInsuranceId();
    var rate = (overrideRate !== undefined && overrideRate !== null) ? overrideRate : serviceRate(s, catId);
    var isEditable = (overrideEditable !== undefined && overrideEditable !== null) ? !!overrideEditable : isServiceRateEditable(s, catId);
    var sId = s.id || s.service_id;

    // Check if item already exists
    var existing = null;
    for (var i = 0; i < state.services.length; i++) {
      if (String(state.services[i].service_id) === String(sId)) {
        existing = state.services[i];
        break;
      }
    }

    if (existing) {
      existing.qty = (existing.qty || 1) + 1;
      if (overrideRate !== undefined && overrideRate !== null) {
        existing.rate = overrideRate;
      }
      if (overrideEditable !== undefined && overrideEditable !== null) {
        existing.is_editable = !!overrideEditable;
      }
    } else {
      state.services.push({
        primary_id: null,
        id: 'svc_' + sId,
        service_id: sId,
        name: s.name || s.service_name || ('Service #' + sId),
        code: s.code || ('#' + sId),
        cat: s.group || s.cat_name || s.category_name || '',
        rate: rate,
        is_editable: isEditable,
        qty: 1,
        disc: '',
        discPct: false,
        isConsult: false
      });
    }

    $('#svc-q').val('');
    closeSvc();
    renderBillTable();
    calculateTotals();
    snack((s.name || s.service_name || 'Service') + ' added to bill.');
  }

  // --- BILL TABLE & ARITHMETIC ---
  function renderBillTable() {
    var $tbody = $('#bill-rows');
    if (!state.services.length) {
      $tbody.empty();
      $('#bill-empty').removeAttr('hidden');
      return;
    }

    $('#bill-empty').attr('hidden', true);

    var hasDiscountPerm = canDiscount();
    var rows = state.services.map(function (item, index) {
      var gross = (item.rate * item.qty);
      var discountAmt = discAmt(item.disc, item.discPct, gross);
      var netAmt = Math.max(0, gross - discountAmt);
      var isPct = !!item.discPct;

      var qtyOptions = '';
      for (var q = 1; q <= 10; q++) {
        qtyOptions += '<option value="' + q + '"' + (item.qty === q ? ' selected' : '') + '>' + q + '</option>';
      }

      var eqText = (isPct && discountAmt > 0) ? '= ' + inr(discountAmt, 0) : '';
      var displayVal = (item.disc !== undefined && item.disc !== null && item.disc !== '') ? item.disc : (!hasDiscountPerm ? '0.00' : '');

      return '<tr data-index="' + index + '"><td class="c-sn">' + (index + 1) + '</td>'
        + '<td>'
        + '<span class="b-what">' + esc(item.name) + '</span>'
        + (item.cat ? '<span class="b-sub">' + esc(item.cat) + '</span>' : '')
        + '</td>'
        + '<td class="num">'
        + (item.is_editable
          ? '<input type="text" inputmode="numeric" maxlength="7" class="line-rate" data-item-rate="' + index + '" value="' + esc(item.rate) + '" aria-label="Rate for ' + esc(item.name) + '">'
          : inr(item.rate, 0))
        + '</td>'
        + '<td class="num">'
        + ((item.isConsult || item.isRegistration) ? '1' : '<select class="qty" data-item-qty="' + index + '">' + qtyOptions + '</select>')
        + '</td>'
        + '<td class="num c-disc">'
        + '<div class="ldisc">'
        + '<span class="dunit" role="group" aria-label="Discount unit">'
        + '<button type="button" data-dunit="inr" data-item-idx="' + index + '" aria-pressed="' + (!isPct) + '"' + (!hasDiscountPerm ? ' disabled' : '') + '>₹</button>'
        + '<button type="button" data-dunit="pct" data-item-idx="' + index + '" aria-pressed="' + (isPct) + '"' + (!hasDiscountPerm ? ' disabled' : '') + '>%</button>'
        + '</span>'
        + '<input type="text" inputmode="numeric" maxlength="' + (isPct ? 3 : 6) + '" placeholder="0" class="line-disc" data-item-disc="' + index + '" value="' + esc(displayVal) + '"' + (!hasDiscountPerm ? ' readonly disabled' : '') + '>'
        + '</div>'
        + '<span class="d-eq">' + esc(eqText) + '</span>'
        + '</td>'
        + '<td class="num l-amt">' + inr(netAmt) + '</td>'
        + '<td>'
        + ((item.isConsult || item.isRegistration) ? '' : '<button type="button" class="btn-text x" data-item-del="' + index + '" title="Remove item" style="color:var(--md-sys-color-error);font-size:16px">&times;</button>')
        + '</td>'
        + '</tr>';
    }).join('');

    $tbody.html(rows);
  }

  function calculateTotals() {
    var subtotal = 0;
    var totalItemDiscount = 0;

    state.services.forEach(function (s) {
      var gross = (s.rate * s.qty);
      var d = discAmt(s.disc, s.discPct, gross);
      subtotal += gross;
      totalItemDiscount += d;
    });

    var subAfterItemDisc = Math.max(0, subtotal - totalItemDiscount);

    var bdiscRaw = $('#bdisc').val();
    var bdiscVal = parseFloat(String(bdiscRaw || '').replace(/[^\d.]/g, '')) || 0;
    if (state.billDiscountPct) {
      if (bdiscVal > 100) bdiscVal = 100;
    } else {
      if (bdiscVal > subAfterItemDisc) bdiscVal = subAfterItemDisc;
    }
    state.billDiscount = bdiscVal;
    var billDiscAmt = discAmt(bdiscVal, state.billDiscountPct, subAfterItemDisc);
    var totalDiscount = totalItemDiscount + billDiscAmt;

    var payable = Math.max(0, subAfterItemDisc - billDiscAmt);

    // Update Totals UI
    $('#t-sub').text(inr(subtotal));

    if (totalItemDiscount > 0) {
      $('#t-disc-row').removeAttr('hidden');
      $('#t-disc').text('-' + inr(totalItemDiscount));
    } else {
      $('#t-disc-row').attr('hidden', true);
    }

    $('#t-bdisc').text('-' + inr(billDiscAmt));

    var authRowEl = document.getElementById('tot-auth-row');
    if (totalDiscount > 0) {
      if (authRowEl) {
        authRowEl.removeAttribute('hidden');
        authRowEl.hidden = false;
        authRowEl.style.display = 'grid';
      }
      $('#tot-auth-row').removeAttr('hidden').show();
    } else {
      if (authRowEl) {
        authRowEl.setAttribute('hidden', 'true');
        authRowEl.hidden = true;
        authRowEl.style.display = 'none';
      }
      $('#tot-auth-row').attr('hidden', true).hide();
      $('#discount_auth_by').val('').removeClass('is-invalid');
    }
    if (!canDiscount()) {
      $('#bdisc').prop('disabled', true).prop('readonly', true);
      $('[data-bunit]').prop('disabled', true);
      if (!$('#bdisc').val()) {
        $('#bdisc').val('0.00');
      }
    }
    $('#t-pay').text(inr(payable));

    // Update First payment row amount if user hasn't explicitly split payments (only in new bill mode, never in edit mode)
    if (state.payAuto && (!state.editing || !state.editing.active) && state.payments.length === 1) {
      state.payments[0].amt = payable > 0 ? payable : '';
      $('#pay-rows .pay-row').first().find('[data-pk="amt"]').val(payable > 0 ? payable : '');
    }

    // Update Payment Totals
    var totalCollected = 0;
    state.payments.forEach(function (p) {
      totalCollected += parseFloat(p.amt) || 0;
    });

    $('#pay-got').text(inr(totalCollected));

    var due = payable - totalCollected;
    var $balBox = $('#pay-bal-box');
    var $balVal = $('#pay-bal');
    var $balKey = $('#pay-bal-k');

    $balVal.text(inr(Math.abs(due)));
    if (due > 0) {
      $balBox.removeClass('is-paid is-ok').addClass('is-due');
      $balKey.text('Due');
    } else if (due < 0) {
      $balBox.removeClass('is-due').addClass('is-paid is-ok');
      $balKey.text('Advance');
    } else {
      $balBox.removeClass('is-due').addClass('is-paid is-ok');
      $balKey.text('Paid in full');
    }

    // Bottom Action Bar
    var svcCount = state.services.length;
    var docLabel = state.doctor ? docName(state.doctor) : 'No doctor';
    $('#ab-line').text(docLabel + ' · ' + svcCount + ' ' + (svcCount === 1 ? 'item' : 'items'));
    $('#ab-sub').text(due < 0 ? inr(Math.abs(due)) + ' advance' : (due === 0 ? 'Paid in full' : inr(due) + ' due'));
    $('#ab-amt').text(inr(payable));
    if (state.editing && state.editing.active && state.editing.mode === 'edit') {
      $('#collect').html('Save changes<span class="kbd">Alt S</span>');
    } else {
      $('#collect').html('Collect ' + inr(totalCollected) + '<span class="kbd">Alt S</span>');
    }
  }

  // --- MULTI-MODE PAYMENT MATRIX ---
  function renderPaymentRows() {
    var $rows = $('#pay-rows').empty();
    var modes = ['Cash', 'UPI', 'Card', 'Cheque', 'Bank transfer'];

    state.payments.forEach(function (p, idx) {
      var selectedMode = backendToFrontendMode(p.mode);
      p.mode = selectedMode;
      var modeOptions = modes.map(function (m) {
        return '<option value="' + m + '"' + (m === selectedMode ? ' selected' : '') + '>' + m + '</option>';
      }).join('');

      var rowHtml = '<div class="pay-grid pay-row" data-pay="' + idx + '">'
        + '<select data-pk="mode" data-pay-idx="' + idx + '" aria-label="Payment mode ' + (idx + 1) + '">' + modeOptions + '</select>'
        + '<div class="affix"><span class="prefix">₹</span><input type="text" inputmode="numeric" maxlength="7" data-pk="amt" data-pay-idx="' + idx + '" value="' + (p.amt || '') + '" placeholder="0" aria-label="Amount by ' + selectedMode + '"></div>'
        + '<input type="text" data-pk="txn" data-pay-idx="' + idx + '" value="' + (p.txn || '') + '" placeholder="' + (TXN_HINT[selectedMode] || '') + '"' + (selectedMode === 'Cash' ? ' disabled' : '') + ' aria-label="Transaction ID for ' + selectedMode + '" autocomplete="off">'
        + '<span>' + (idx > 0 ? '<button type="button" class="btn-text x" data-pay-del="' + idx + '" title="Remove payment">&times;</button>' : '') + '</span>'
        + '</div>';

      $rows.append(rowHtml);
    });
  }

  function addPaymentRow() {
    state.payAuto = false;
    state.payments.push({ mode: 'UPI', amt: '', txn: '' });
    renderPaymentRows();
    calculateTotals();
  }

  function removePaymentRow(idx) {
    if (state.payments.length > 1) {
      state.payments.splice(idx, 1);
      if (state.payments.length === 1) {
        state.payAuto = true;
      }
      renderPaymentRows();
      calculateTotals();
    }
  }

  function resetBillForm() {
    state.visitPrepared = false;
    state.patientRequest++;
    state.chargeRequest++;
    state.billRequestId = null;
    state.billRequestPayload = null;
    state.visitId = null;
    state.doctor = null;
    state.consultation = null;
    state.addConsultation = false;
    state.services = [];
    state.billDiscount = 0;
    state.billDiscountPct = false;
    state.payments = [{ mode: 'Cash', amt: '', txn: '' }];
    state.payAuto = true;
    state.remark = '';
    state.billId = null;
    state.draftDoctorId = null;
    state.isLoadingDraft = false;
    state.consultPrimaryId = null;
    state.regPrimaryId = null;
    state.refBy = '';

    $('#doc').val('');
    $('#spec').val('');
    $('#ref_by').val('');
    $('#charge').empty().append('<option value="">Select consultation...</option>');
    $('#billing-visit-kind').val('1');
    $('#billing-visit-error').empty();
    $('#sec-visit [data-field="charge"], #sec-visit .ob-consult').show();
    $('#consult').prop('checked', true);
    $('#bdisc').val('');
    $('#discount_auth_by').val('').removeClass('is-invalid');
    $('#tot-auth-row').removeClass('is-visible').attr('hidden', true).hide().css('display', 'none');
    $('[data-bunit="inr"]').attr('aria-pressed', 'true');
    $('[data-bunit="pct"]').attr('aria-pressed', 'false');
    $('#remark').val('');
    if (!state.editing || !state.editing.active) {
      $('#pt-doc-badge').attr('hidden', true).hide();
      $('#sec-visit, .sec-v, #sec-doc').show();
    }
    renderPaymentRows();
    renderBillTable();
    calculateTotals();
    if (!workflowEditing()) setWorkflowStep(state.patient ? 2 : 1);
  }

  // --- SUBMIT BILL ---
  function submitBill(isDraft) {
    if (state.savingBill) return;
    if (window.BillingGuard && window.BillingGuard.isActive()) {
      window.BillingGuard.showAlert();
      return;
    }
    isDraft = (isDraft === true);
    if (!workflowEditing() && (!state.visitPrepared || state.workflowStep !== 3 || !state.visitId || state.preparingVisit)) {
      snack('Create or confirm the visit before billing.');
      return;
    }

    if (!state.patient) {
      snack('Please select a patient first.');
      $('#q').focus();
      return;
    }

    if (!state.services.length) {
      snack('Please add a consultation or at least one service to the bill.');
      $('#svc-q').focus();
      return;
    }

    var payable = parseFloat($('#t-pay').text().replace(/[^\d.]/g, '')) || 0;
    if (payable < 0) {
      snack('Bill amount cannot be negative.');
      return;
    }

    var totalCollected = 0;
    state.payments.forEach(function (p) {
      totalCollected += parseFloat(p.amt) || 0;
    });

    if (totalCollected < 0) {
      snack('Collection amount cannot be negative.');
      return;
    }

    var primaryIdsList = [];
    var serviceIds = [];
    var chargesList = [];
    var qtyList = [];
    var discList = [];
    var subtotal = 0;
    var totalItemDiscount = 0;

    state.services.forEach(function (s) {
      serviceIds.push(s.service_id);
      chargesList.push(s.rate);
      qtyList.push(s.qty);
      var gross = s.rate * s.qty;
      var d = discAmt(s.disc, s.discPct, gross);
      discList.push(d);
      var pid = s.primary_id || (s.isConsult ? state.consultPrimaryId : '') || '';
      primaryIdsList.push(pid);
      subtotal += gross;
      totalItemDiscount += d;
    });

    var subAfterItemDisc = Math.max(0, subtotal - totalItemDiscount);
    var billDiscAmt = discAmt($('#bdisc').val(), state.billDiscountPct, subAfterItemDisc);
    var totalDiscount = totalItemDiscount + billDiscAmt;

    var discountAuthBy = $('#discount_auth_by').val() || '';
    if (totalDiscount > 0 && !discountAuthBy) {
      snack('Please select who authorized the discount.');
      var authRowEl = document.getElementById('tot-auth-row');
      if (authRowEl) {
        authRowEl.removeAttribute('hidden');
        authRowEl.hidden = false;
        authRowEl.style.display = 'grid';
      }
      $('#tot-auth-row').removeAttr('hidden').show();
      $('#discount_auth_by').addClass('is-invalid').focus();
      return;
    }
    $('#discount_auth_by').removeClass('is-invalid');

    var paymentMap = {};
    var trxMap = {};
    var totalPaid = 0;
    var hasNegativePayment = false;
    var missingTrx = false;

    if (!isDraft) {
      state.payments.forEach(function (p) {
        var backendMode = frontendToBackendMode(p.mode);
        var amtVal = parseFloat(p.amt) || 0;
        if (amtVal < 0) {
          hasNegativePayment = true;
        } else if (amtVal > 0) {
          paymentMap[backendMode] = (paymentMap[backendMode] || 0) + amtVal;
          totalPaid += amtVal;
          if (p.txn && p.txn.trim()) {
            trxMap[backendMode] = p.txn.trim();
          } else if (p.mode === 'UPI' || p.mode === 'Card') {
            missingTrx = p.mode;
          }
        }
      });

      if (hasNegativePayment) {
        snack('Payment mode amount cannot be negative.');
        return;
      }

      if (missingTrx) {
        snack('Please enter Transaction ID for ' + missingTrx + ' payment.');
        return;
      }

      if (!Object.keys(paymentMap).length) {
        paymentMap['cash'] = 0;
        totalPaid = 0;
      }
    } else {
      paymentMap['cash'] = 0;
      totalPaid = 0;
    }

    var $collBtn = $('#collect');
    var $saveBtn = $('#save-only');
    var $contextControls = $('#pt-change, #billing-edit-visit, #ob-clear, #tab-new, #tab-all, #tab-ref');
    state.savingBill = true;
    $contextControls.prop('disabled', true);
    $collBtn.prop('disabled', true);
    $saveBtn.prop('disabled', true);

    var payload = {
      primary_id: primaryIdsList,
      patient_id: state.patient.id || state.patient.uhid,
      uhid: state.patient.uhid,
      doctor_id: state.doctor ? state.doctor.id : '',
      doctor_charges: (state.consultation ? state.consultation.amt : 0),
      add_visit: state.addConsultation && state.consultation ? 1 : 0,
      ref_by: (function () {
        var r = state.refBy || ($('#ref_by').length ? $('#ref_by').val() : '') || '';
        return (r && r !== '__new__') ? r : '';
      })(),
      action: isDraft ? 'save_as_draft' : '',
      service_id: serviceIds,
      charges: chargesList,
      qty: qtyList,
      discountArr: discList,
      discount: billDiscAmt,
      discount_auth_by: (totalDiscount > 0 ? discountAuthBy : ''),
      discount_type: state.billDiscountPct ? 1 : 0,
      payment: paymentMap,
      trx: trxMap,
      remarks: $('#remark').val() || '',
      total: payable,
      total_paid: totalPaid
    };

    if (state.editing && state.editing.active && state.editing.billId) {
      payload.bill_no = state.editing.billId;
      payload.action = 'edit_bill';
    } else if (state.billId) {
      payload.bill_no = state.billId;
      if (!isDraft) {
        payload.action2 = 'gen_bill_from_draft';
      } else {
        payload.action = 'save_as_draft';
      }
    }

    if (!workflowEditing()) {
      payload.prepared_visit_id = state.visitId;
      var serialized = JSON.stringify(payload);
      if (serialized !== state.billRequestPayload) state.billRequestId = requestId();
      state.billRequestPayload = serialized;
      payload.request_id = state.billRequestId;
    }
    if (window.BillingGuard) {
      window.BillingGuard.start('Billing is in progress, do not refresh or move from page.');
    }

    $.ajax({
      url: workflowEditing() ? window.ROUTES.genOpdBill : window.ROUTES.saveBillingWorkflow,
      type: 'POST',
      data: payload,
      success: function (res) {

        if (res && (res.status === 'error' || (res.message && (res.message.indexOf('cannot be edited') > -1 || res.message.indexOf('already settled') > -1 || res.message.indexOf('locked') > -1)))) {
          snack(res.message || 'Bill cannot be edited.');
          return;
        }

        if (res && (res.status === 'success' || res.success || res.bill_id)) {
          var createdId = res.bill_id || res.id;
          if (state.editing && state.editing.active) {
            snack(res.message || ('Bill #' + createdId + ' updated successfully.'));
            var updatedBillId = state.editing.billId;
            leaveEditMode();
            if (typeof fetchBills === 'function') {
              fetchBills();
            }
            showBillDocument(updatedBillId);
            return;
          }
          if (isDraft) {
            state.billId = createdId;
            state.draftDoctorId = state.doctor ? String(state.doctor.id) : null;
            snack('Draft bill #' + createdId + ' saved · bill later.');
          } else {
            showBillDocument(createdId);
            snack('Bill ' + createdId + ' generated successfully.');
          }
        } else {
          snack(res.message || 'Failed to generate bill.');
        }
      },
      error: function (xhr) {
        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error generating bill. Please try again.';
        snack(msg);
      },
      complete: function () {
        state.savingBill = false;
        $contextControls.prop('disabled', false);
        if (window.BillingGuard) {
          window.BillingGuard.stop();
        }
        $collBtn.prop('disabled', false);
        $saveBtn.prop('disabled', false);
      }
    });
  }

  function showBillDocument(billId) {
    $.ajax({
      url: window.ROUTES.billDetails + billId,
      type: 'GET',
      success: function (bill) {
        $('#ob-form').attr('hidden', true);
        $('#ob-bar').attr('hidden', true);
        $('#ob-paid').removeAttr('hidden');

        var due = parseFloat(bill.due) || 0;
        var paid = parseFloat(bill.paid) || 0;

        $('#paid-h').text(due > 0 ? (paid > 0 ? 'Bill ' + bill.no + ' saved · part paid' : 'Bill ' + bill.no + ' saved · unpaid') : 'Bill ' + bill.no + ' paid');
        $('#paid-t').text((paid > 0 ? inr(paid) + ' collected.' : 'No payment collected.') + (due > 0 ? ' ' + inr(due) + ' is due; collect it from this bill or from All bills.' : ''));
        $('#ob-paid .vv-ok').toggleClass('is-part', due > 0);

        $('#paid-doc').html(renderBillDocHTML(bill));
        window.scrollTo(0, 0);
      },
      error: function () {
        snack('Bill saved, but failed to load preview.');
      }
    });
  }

  // --- RENDER BILL DOCUMENT ---
  function renderBillDocHTML(b) {
    var p = b.patient || {};
    var fullName = p.name || ((p.first_name || '') + ' ' + (p.last_name || '')).trim() || 'Patient';
    var ageStr = p.age ? p.age + ' yrs' : (p.dob ? p.dob : '');
    var meta = '<span><b>UHID:</b> ' + esc(p.uhid || b.uhid) + '</span> · '
      + '<span>' + esc([ageStr, p.gender].filter(Boolean).join(' / ')) + '</span> · '
      + '<span>+91 ' + esc(p.mobile_no || p.mobile || '—') + '</span>';

    var due = parseFloat(b.due) || 0;
    var paid = parseFloat(b.paid) || 0;
    var billed = parseFloat(b.billed || b.total) || 0;
    var subtotal = parseFloat(b.subtotal) || billed;
    var discount = parseFloat(b.discount) || 0;

    var itemsRows = (b.items || []).map(function (it) {
      var rate = parseFloat(it.rate || it.charges) || 0;
      var q = parseInt(it.qty) || 1;
      var d = parseFloat(it.disc || it.discount) || 0;
      var net = (rate * q) - d;
      return '<tr>'
        + '<td><span class="b-what">' + esc(it.name || it.service_name) + '</span></td>'
        + '<td class="num">' + inr(rate, 0) + '</td>'
        + '<td class="num">' + q + '</td>'
        + '<td class="num">' + (d > 0 ? '-' + inr(d, 0) : '—') + '</td>'
        + '<td class="num">' + inr(net) + '</td>'
        + '</tr>';
    }).join('');

    var displayNo = b.bill_number || b.no || b.id;
    var isCancelled = parseInt(b.is_cancelled) === 1;
    var isRefund = parseInt(b.is_refund_bill) === 1 || parseInt(b.is_refunded) === 1 || parseFloat(b.refunded || 0) > 0 || parseInt(b.has_refunds) === 1;
    var canEditThisBill = canEditBill() && !isCancelled && !isRefund;
    var canCancelThisBill = canCancelBill() && !isCancelled && !isRefund;

    var paysRows = (b.payments || []).map(function (py) {
      var editBtn = canEditThisBill ? ' <button type="button" class="btn-text" data-act="edit-receipt-item" data-bill-id="' + b.id + '" data-bill-no="' + esc(displayNo) + '" data-payment-id="' + py.id + '" data-amount="' + py.amount + '" data-mode="' + esc(py.mode) + '" data-txn="' + esc(py.txn || '') + '" title="Edit receipt" style="margin-left:8px;font-size:12px;color:var(--md-sys-color-primary);vertical-align:middle;padding:0 4px">Edit</button>' : '';
      return '<tr>'
        + '<td>' + esc(py.mode) + editBtn + '</td>'
        + '<td>' + esc(py.txn || '—') + '</td>'
        + '<td>' + esc(py.date || b.date) + (py.collected_by ? ' <span class="soft">· ' + esc(py.collected_by) + '</span>' : '') + '</td>'
        + '<td class="num">' + inr(py.amount) + '</td>'
        + '</tr>';
    }).join('');

    var collectDueForm = '';
    if (due > 0) {
      collectDueForm = '<form class="bd-collect" data-collect-bill="' + b.id + '" autocomplete="off">'
        + '<h3 class="pay-h" style="margin-top:0">Collect the due</h3>'
        + '<div class="bd-crow">'
        + '<select data-c="mode" aria-label="Payment mode">'
        + '<option selected>Cash</option><option>UPI</option><option>Card</option><option>Cheque</option><option>Bank transfer</option>'
        + '</select>'
        + '<div class="affix"><span class="prefix">₹</span><input type="text" inputmode="numeric" maxlength="7" data-c="amt" placeholder="Up to ₹' + due + '" aria-label="Amount collected now"></div>'
        + '<input type="text" data-c="txn" placeholder="Not needed for cash" disabled aria-label="Transaction ID">'
        + '<button type="submit" class="btn btn-filled">Collect</button>'
        + '</div>'
        + '<span class="err pay-err" data-c="err" role="alert" style="color:var(--md-sys-color-error);font-size:12px;margin-top:4px"></span>'
        + '</form>';
    }

    var statusPills = '';
    if (isCancelled) {
      statusPills += ' <span class="pill pill-err">Cancelled</span>';
    }
    if (isRefund) {
      statusPills += ' <span class="pill pill-refund">Refund</span>';
    }
    if (!isCancelled) {
      if (due > 0) {
        statusPills += ' <span class="pill pill-warn" style="background:var(--md-sys-color-warning-container);color:var(--md-sys-color-on-warning-container)">' + inr(due) + ' due</span>';
      } else {
        statusPills += ' <span class="pill pill-ok" style="background:var(--md-sys-color-success-container);color:var(--md-sys-color-on-success-container)">Paid</span>';
      }
    }

    return '<div class="vv-top ob-pt">'
      + '<div class="vv-who"><h2>' + esc(fullName) + '</h2><p class="vv-meta">' + meta + '</p></div>'
      + '</div>'
      + '<section class="panel sec sec-b bd-panel" aria-label="Bill ' + displayNo + '">'
      + '<div class="sec-head">'
      + '<h2 class="sec-title">Bill ' + esc(displayNo) + statusPills + '</h2>'
      + '<div class="sec-actions">'
      + '<button type="button" class="btn btn-outlined" data-doc-act="print-bill" data-bill-id="' + b.id + '">' + PRINT_IC + ' Print bill</button>'
      + '<button type="button" class="btn btn-outlined" data-doc-act="print-receipt" data-bill-id="' + b.id + '">' + PRINT_IC + ' Print receipt</button>'
      + (canEditThisBill ? '<button type="button" class="btn btn-outlined" data-doc-act="edit-bill" data-bill-id="' + b.id + '">' + I_EDIT + ' Edit bill</button>' : '')
      + (!canEditThisBill && canCancelThisBill ? '<button type="button" class="btn btn-outlined cx-danger" data-doc-act="cancel-bill" data-bill-id="' + b.id + '">' + I_CANCEL + ' Cancel bill</button>' : '')
      + '<button type="button" class="btn btn-outlined bd-wa" data-doc-act="wa" data-bill-id="' + b.id + '" aria-label="Send bill ' + displayNo + ' on WhatsApp">' + WA_IC + ' WhatsApp</button>'
      + '</div>'
      + '</div>'
      + '<div class="vv-body" style="padding:var(--md-sys-spacing-4)">'
      + '<dl class="vv-grid bd-facts" style="display:grid;gap:12px">'
      + '<div><dt style="font-size:12px;color:var(--md-sys-color-on-surface-variant)">Date &amp; time</dt><dd style="font-weight:600">' + esc(b.date) + '</dd></div>'
      + '<div><dt style="font-size:12px;color:var(--md-sys-color-on-surface-variant)">Doctor</dt><dd style="font-weight:600">' + esc(docName(b.doctor)) + '</dd></div>'
      + '<div><dt style="font-size:12px;color:var(--md-sys-color-on-surface-variant)">Payment category</dt><dd style="font-weight:600">' + esc(b.category || 'General') + '</dd></div>'
      + (b.ref_doctor_name ? ('<div><dt style="font-size:12px;color:var(--md-sys-color-on-surface-variant)">Ref. By</dt><dd style="font-weight:600">' + esc(b.ref_doctor_name) + '</dd></div>') : '')
      + '<div><dt style="font-size:12px;color:var(--md-sys-color-on-surface-variant)">Collected by</dt><dd style="font-weight:600">' + esc(b.collected_by || 'Front desk') + '</dd></div>'
      + '</dl>'
      + '<table class="bill bd-tbl" style="width:100%;margin-top:16px">'
      + '<thead><tr><th>Item</th><th class="num">Rate</th><th class="num">Qty</th><th class="num">Discount</th><th class="num">Amount</th></tr></thead>'
      + '<tbody>' + itemsRows + '</tbody>'
      + '</table>'
      + '<div class="totals" style="margin-top:16px">'
      + '<div class="tot"><span>Subtotal</span><span class="t-v">' + inr(subtotal) + '</span></div>'
      + (discount > 0 ? '<div class="tot"><span>Bill discount' + (b.discount_auth_by_name ? ' <small style="color:var(--md-sys-color-on-surface-variant)">(Auth by: ' + esc(b.discount_auth_by_name) + ')</small>' : '') + '</span><span class="t-v">-' + inr(discount) + '</span></div>' : '')
      + '<div class="tot tot-big"><span>Billed</span><span class="t-v">' + inr(billed) + '</span></div>'
      + '</div>'
      + '<table class="pv-vt bd-pays" style="width:100%;margin-top:16px">'
      + '<thead><tr><th>Mode</th><th>Transaction ID</th><th>Collected</th><th class="num">Amount</th></tr></thead>'
      + '<tbody>' + paysRows + '</tbody>'
      + '<tfoot><tr><td colspan="3" style="font-weight:600">Paid</td><td class="num" style="font-weight:600">' + inr(paid) + '</td></tr>'
      + (due > 0 ? '<tr class="bd-due"><td colspan="3" style="color:var(--md-sys-color-warning);font-weight:600">Due</td><td class="num" style="color:var(--md-sys-color-warning);font-weight:600">' + inr(due) + '</td></tr>' : '')
      + '</tfoot>'
      + '</table>'
      + collectDueForm
      + (b.remarks ? '<p class="vv-note" style="margin-top:12px;font-size:13px;color:var(--md-sys-color-on-surface-variant)">Remark: ' + esc(b.remarks) + '</p>' : '')
      + '</div>'
      + '</section>';
  }

  // --- ALL BILLS (TAB 2) ---
  function fetchBills(resetPage) {
    if (resetPage) state.allBills.page = 1;

    var billNo = $('#f-no').val().trim();
    var q = $('#f-pt').val().trim();
    var from = $('#f-from').val();
    var to = $('#f-to').val();
    var status = $('#f-status button[aria-pressed="true"]').data('st') || 'all';

    var params = {
      bill_no: billNo,
      search: q,
      from_date: from,
      to_date: to,
      status: status,
      page: state.allBills.page,
      per_page: state.allBills.size
    };

    $.ajax({
      url: window.ROUTES.listBills,
      type: 'GET',
      data: params,
      success: function (res) {
        state.allBills.bills = res.bills || [];
        state.allBills.total = res.total || 0;
        state.allBills.summary = res.summary || {};
        renderAllBillsTable();
        renderSummaryStrip();
        renderPagination();
      },
      error: function () {
        snack('Failed to load bills.');
      }
    });
  }

  function renderSummaryStrip() {
    var sum = state.allBills.summary || {};
    var totalBills = state.allBills.total || 0;
    var from = $('#f-from').val();
    var to = $('#f-to').val();

    var dateRangeStr = '';
    if (from && to) {
      dateRangeStr = '<span class="al-range">' + esc(from) + ' to ' + esc(to) + '</span>';
    }

    var html = '<span><b>' + totalBills + '</b> ' + (totalBills === 1 ? 'bill' : 'bills') + '</span> · '
      + '<span><b>' + inr(sum.billed || 0) + '</b> billed</span> · '
      + '<span><b>' + inr(sum.paid || 0) + '</b> paid</span> · '
      + '<span class="' + ((sum.due || 0) > 0 ? 'al-due' : '') + '"><b>' + inr(sum.due || 0) + '</b> due</span>'
      + dateRangeStr;

    $('#al-sum').html(html);
  }

  var CHEV_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>';

  function mvLabel(mv) {
    if (!mv || !mv.length) return '';
    var outCount = mv.filter(function (m) { return m.out; }).length;
    var inCount = mv.length - outCount;
    var parts = [];
    if (inCount) parts.push(inCount + (inCount === 1 ? ' receipt' : ' receipts'));
    if (outCount) parts.push(outCount + (outCount === 1 ? ' refund' : ' refunds'));
    return parts.join(' · ');
  }

  function renderAllBillsTable() {
    var $tbody = $('#al-rows');
    var bills = state.allBills.bills;

    if (!bills.length) {
      $tbody.empty();
      $('#al-none').text('No bills found matching your filter criteria.').removeAttr('hidden');
      $('#al-pager').attr('hidden', true);
      return;
    }

    $('#al-none').attr('hidden', true);

    var html = bills.map(function (b) {
      var isCancelled = parseInt(b.is_cancelled) === 1;
      var isRefund = parseInt(b.is_refund_bill) === 1 || parseInt(b.is_refunded) === 1 || parseFloat(b.refunded || 0) > 0 || parseInt(b.has_refunds) === 1;
      var due = isCancelled ? 0 : (parseFloat(b.due) || 0);
      var patName = b.patient_name || 'Patient';
      var patMeta = [b.uhid, b.mobile].filter(Boolean).join(' · ');
      var displayNo = b.bill_number || b.no || b.id;
      var mv = b.movements || [];
      var hasPays = mv.length > 0;
      var rf = parseFloat(b.refunded) || 0;

      var pillsHtml = '';
      if (isCancelled || isRefund) {
        pillsHtml = '<div class="al-stps">'
          + (isCancelled ? '<span class="pill pill-err al-stp">Cancelled</span>' : '')
          + (isRefund ? '<span class="pill pill-refund al-stp">Refund</span>' : '')
          + '</div>';
      }

      var billRow = '<tr data-bill-row="' + b.id + '"'
        + ' data-cancelled="' + (isCancelled ? 'true' : 'false') + '"'
        + ' class="' + (isCancelled ? 'is-cancelled ' : '') + (hasPays ? 'has-pays' : '') + '">'
        + '<td class="al-no al-bno" data-k="Bill no.">'
        + '<button type="button" class="link al-no" data-open-bill="' + b.id + '">' + esc(displayNo) + '</button>'
        + pillsHtml
        + (hasPays ? '<button type="button" class="al-exp" data-exp="' + esc(displayNo) + '" aria-expanded="false" aria-label="' + esc(mvLabel(mv)) + ' against bill ' + esc(displayNo) + '">' + CHEV_SVG + esc(mvLabel(mv)) + '</button>' : '')
        + '</td>'
        + '<td data-k="Date & time">' + esc(b.date) + '</td>'
        + '<td class="al-pt" data-k="Patient">'
        + '<b>' + esc(patName) + '</b>'
        + '<span class="soft-l">' + esc(patMeta) + '</span>'
        + '</td>'
        + '<td data-k="Doctor">' + esc(docName(b.doctor)) + '</td>'
        + '<td class="num" data-k="Subtotal">' + inr(b.subtotal, 0) + '</td>'
        + '<td class="num" data-k="Discount">' + (b.discount > 0 ? '-' + inr(b.discount, 0) : '—') + '</td>'
        + '<td class="num" data-k="Billed"><b>' + inr(b.billed, 0) + '</b></td>'
        + '<td class="num" data-k="Paid">' + inr(b.paid, 0) + (rf > 0 ? '<span class="soft-l">−' + inr(rf, 0) + ' refunded</span>' : '') + '</td>'
        + '<td class="num' + (due > 0 ? ' al-due' : '') + '" data-k="Due">' + (isCancelled ? '<span class="soft-l">—</span>' : (due > 0 ? '<b>' + inr(due, 0) + '</b>' : '—')) + '</td>'
        + '<td class="al-act" data-k="Actions">'
        + '<button type="button" class="b-ic" data-doc-act="print-bill" data-bill-id="' + b.id + '" title="Print bill">' + PRINT_IC + '</button>'
        + '<button type="button" class="b-wa" data-wa="' + b.id + '" title="Send WhatsApp">' + WA_IC + '</button>'
        + '<button type="button" class="al-more" data-more="' + esc(displayNo) + '" data-bill-id="' + b.id + '" title="More actions" aria-expanded="false">'
        + '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>'
        + '</button>'
        + '</td>'
        + '</tr>';

      var moveRows = (hasPays ? mv.map(function (m, i) {
        var isOut = !!m.out;
        var isLast = i === mv.length - 1;
        var amtFormatted = inr(m.amount, 0);
        var kind = isOut ? 'Refund' : 'Receipt';
        var rcptText = m.receipt_no || (isOut ? 'RF-' + m.id : 'RC-' + m.id);

        return '<tr class="al-pay' + (isOut ? ' is-out' : '') + (isLast ? ' is-last' : '') + '" data-of="' + esc(displayNo) + '" hidden>'
          + '<td data-k="' + (isOut ? 'Refund no.' : 'Receipt no.') + '" class="ap-no">'
          + '<span class="ap-kind">' + kind + '</span>'
          + '<button type="button" class="link ap-link" data-payment-id="' + m.id + '" data-bill-id="' + b.id + '" data-is-out="' + (isOut ? '1' : '0') + '" aria-label="Open ' + (isOut ? 'refund ' : 'receipt ') + esc(rcptText) + '">' + esc(rcptText) + '</button>'
          + '</td>'
          + '<td data-k="Date & time">' + esc(m.date || '') + (m.time ? '<span class="soft-l">' + esc(m.time) + '</span>' : '') + '</td>'
          + '<td data-k="Mode" colspan="5" class="ap-mode">' + esc(m.mode || 'Cash')
          + (m.txn ? '<span class="soft-l">' + esc(m.txn) + '</span>' : '')
          + (m.by ? '<span class="soft-l">' + (isOut ? 'Refunded by ' : 'Collected by ') + esc(m.by) + '</span>' : '')
          + '</td>'
          + '<td class="num ap-amt" data-k="' + (isOut ? 'Given back' : 'Received') + '">'
          + (isOut ? '−' : '') + amtFormatted
          + '</td>'
          + '<td colspan="2" class="ap-end">'
          + '<button type="button" class="b-ic" data-doc-act="print-receipt" data-bill-id="' + b.id + '" data-payment-id="' + m.id + '" title="Print receipt">' + PRINT_IC + '</button>'
          + ((canEditBill() || (isOut && canEditRefundReceipt())) && !isCancelled ? '<button type="button" class="b-ic" data-act="edit-receipt-item" data-bill-id="' + b.id + '" data-bill-no="' + esc(displayNo) + '" data-payment-id="' + m.id + '" data-amount="' + m.amount + '" data-mode="' + esc(m.mode) + '" data-txn="' + esc(m.txn || '') + '" data-patient-name="' + esc(b.patient_name || '') + '" data-is-out="' + (isOut ? '1' : '0') + '" title="' + (isOut ? 'Edit refund receipt' : 'Edit receipt') + '">' + I_EDIT + '</button>' : '')
          + '</td>'
          + '</tr>';
      }).join('') : '');

      return billRow + moveRows;
    }).join('');

    $tbody.html(html);
  }

  function renderPagination() {
    var total = state.allBills.total;
    var page = state.allBills.page;
    var size = state.allBills.size;
    var totalPages = Math.ceil(total / size) || 1;

    if (total <= size) {
      $('#al-pager').attr('hidden', true);
      return;
    }

    $('#al-pager').removeAttr('hidden');
    var start = ((page - 1) * size) + 1;
    var end = Math.min(page * size, total);
    $('#pg-info').text('Showing ' + start + '–' + end + ' of ' + total);

    var $nums = $('#pg-nums').empty();

    // Prev Button
    $nums.append('<button type="button" class="pg-btn" data-pg="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + ' aria-label="Previous page"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg></button>');

    for (var p = 1; p <= totalPages; p++) {
      if (p === 1 || p === totalPages || (p >= page - 2 && p <= page + 2)) {
        $nums.append('<button type="button" class="pg-btn" data-pg="' + p + '"' + (p === page ? ' aria-current="page"' : '') + '>' + p + '</button>');
      } else if (p === page - 3 || p === page + 3) {
        $nums.append('<span class="pg-gap">…</span>');
      }
    }

    // Next Button
    $nums.append('<button type="button" class="pg-btn" data-pg="' + (page + 1) + '"' + (page >= totalPages ? ' disabled' : '') + ' aria-label="Next page"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg></button>');
  }

  // Floating Row Menu
  function openRowMenu($btn, billNo, billId) {
    closeRowMenu();
    state.allBills.activeMenuNo = billNo;
    state.allBills.activeMenuId = billId;

    var $menu = $('#row-menu');
    $('#rm-head').text('Bill ' + billNo);

    // Find the bill record from current bills list
    var bill = (state.allBills.bills || []).find(function (b) {
      return String(b.id) === String(billId) || String(b.no) === String(billNo) || String(b.bill_number) === String(billNo);
    });

    var isCancelled = (bill && parseInt(bill.is_cancelled) === 1) || $btn.closest('tr').data('cancelled') === true;
    var isRefund = bill && (parseInt(bill.is_refund_bill) === 1 || parseInt(bill.is_refunded) === 1 || parseFloat(bill.refunded || 0) > 0 || parseInt(bill.has_refunds) === 1);

    // Dynamic RBAC & state visibility:
    // Never show edit option against cancelled bill or refund bill
    var hasEdit = canEditBill() && !isCancelled && !isRefund;
    var hasCancel = canCancelBill() && !isCancelled && !isRefund;
    var hasRefund = canRefund() && !isCancelled && !isRefund;

    $menu.find('[data-act="edit-bill"], [data-act="edit-receipt"]').toggle(hasEdit);
    $menu.find('[data-act="cancel-bill"]').toggle(hasCancel && !hasEdit);
    $menu.find('.rm-sep-edit').toggle(hasEdit || (hasCancel && !hasEdit));

    $menu.find('[data-act="refund"]').toggle(hasRefund);
    $menu.find('.rm-sep-refund').toggle(hasRefund);

    $btn.attr('aria-expanded', 'true');
    $menu.removeAttr('hidden');

    var rect = $btn[0].getBoundingClientRect();
    var menuW = $menu.outerWidth();
    var menuH = $menu.outerHeight();

    var left = Math.min(Math.max(8, rect.right - menuW), Math.max(8, window.innerWidth - menuW - 8));
    var top = rect.bottom + 6;
    if (top + menuH > window.innerHeight - 8) {
      top = Math.max(8, rect.top - menuH - 6);
    }

    $menu.css({ left: left + 'px', top: top + 'px' });
  }

  function closeRowMenu() {
    var $menu = $('#row-menu');
    if (!$menu.is(':hidden')) {
      $menu.attr('hidden', true);
      if (state.allBills.activeMenuNo) {
        $('[data-more="' + state.allBills.activeMenuNo + '"]').attr('aria-expanded', 'false');
      }
      state.allBills.activeMenuNo = null;
      state.allBills.activeMenuId = null;
    }
  }

  function openBillDetails(billId) {
    $.ajax({
      url: window.ROUTES.billDetails + billId,
      type: 'GET',
      success: function (bill) {
        $('#all-list').attr('hidden', true);
        $('#all-detail').removeAttr('hidden');
        $('#al-doc').html(renderBillDocHTML(bill));
        window.scrollTo(0, 0);
      },
      error: function () {
        snack('Failed to load bill details.');
      }
    });
  }

  function backToAllBillsList() {
    $('#all-detail').attr('hidden', true);
    $('#all-list').removeAttr('hidden');
  }

  // --- EDIT RECEIPT MODAL ---
  function openReceiptEditModal(data) {
    var isOut = data.isOut === '1' || data.isOut === 1 || data.isOut === true;
    if (!canEditBill() && !(isOut && canEditRefundReceipt())) {
      snack('You do not have permission to edit receipts.');
      return;
    }

    var $dlg = $('#er-dlg');
    var prefix = isOut ? 'RF-' : 'RC-';

    $('#er-payment-id').val(data.paymentId);
    $('#er-bill-id').val(data.billId);
    $('#er-t').text(isOut ? 'Edit Refund Receipt' : 'Edit Receipt');
    $('#er-rcpt-no').text(prefix + data.paymentId);
    $('#er-bill-no').text(data.billNo || data.billId);
    $('#er-submit').text(isOut ? 'Update Refund Receipt' : 'Update Receipt');

    var infoText = '<b>' + esc(data.patientName || 'Patient') + '</b>';
    if (data.billed !== undefined) {
      infoText += ' · Total Billed: <b>' + inr(data.billed) + '</b>';
    }
    if (data.due !== undefined) {
      infoText += ' · Current Due: <b>' + inr(data.due) + '</b>';
    }
    $('#er-info').html(infoText);

    var rawMode = String(data.mode || 'cash').trim().toLowerCase();
    var modeVal = 'cash';
    if (rawMode === 'upi') modeVal = 'upi';
    else if (rawMode === 'card') modeVal = 'card';
    else if (rawMode === 'cheque') modeVal = 'cheque';
    else if (rawMode === 'neft_rtgs' || rawMode === 'bank transfer' || rawMode === 'bank_transfer') modeVal = 'neft_rtgs';
    else modeVal = 'cash';

    $('#er-mode').val(modeVal);
    $('#er-amt').val(parseFloat(data.amount) || '');
    $('#er-txn').val(data.txn || '');
    $('#er-reason').val('');
    $('#er-amt-err').attr('hidden', true);

    if (modeVal === 'cash') {
      $('#er-txn').val('').prop('disabled', true).attr('placeholder', 'Not needed for cash');
    } else {
      $('#er-txn').prop('disabled', false).attr('placeholder', (TXN_HINT[backendToFrontendMode(modeVal)] || 'Ref / Txn no.'));
    }

    $dlg.removeAttr('hidden');
    $('#er-amt').focus();
  }

  function closeReceiptEditModal() {
    $('#er-dlg').attr('hidden', true);
  }

  function openBillReceiptEdit(billId, billNo) {
    var bill = (state.allBills.bills || []).find(function (b) {
      return String(b.id) === String(billId) || String(b.no) === String(billNo);
    });

    var movements = (bill && bill.movements) ? bill.movements : [];
    var inMovements = movements.filter(function (m) { return !m.out; });
    var targetMv = (inMovements.length > 0) ? inMovements[0] : (movements.length > 0 ? movements[0] : null);

    if (targetMv && movements.length === 1) {
      openReceiptEditModal({
        billId: bill.id,
        billNo: bill.bill_number || bill.no || bill.id,
        paymentId: targetMv.id,
        amount: targetMv.amount,
        mode: targetMv.mode,
        txn: targetMv.txn,
        isOut: !!targetMv.out,
        patientName: bill.patient_name,
        billed: bill.billed || bill.total,
        paid: bill.paid,
        due: bill.due
      });
    } else {
      $.ajax({
        url: window.ROUTES.billDetails + billId,
        type: 'GET',
        success: function (b) {
          var pays = (b.payments || []).filter(function (p) { return parseFloat(p.amount) > 0; });
          if (!pays.length) {
            snack('No payment receipt found for this bill.');
            return;
          }
          var p = pays[0];
          openReceiptEditModal({
            billId: b.id,
            billNo: b.bill_number || b.no || b.id,
            paymentId: p.id,
            amount: p.amount,
            mode: p.mode,
            txn: p.txn,
            isOut: false,
            patientName: (b.patient ? b.patient.name : ''),
            billed: b.billed || b.total,
            paid: b.paid,
            due: b.due
          });
        },
        error: function () {
          snack('Failed to load bill receipt details.');
        }
      });
    }
  }

  // --- EDIT RECEIPT MODAL ---
  function openReceiptEditModal(data) {
    if (!canEditBill()) {
      snack('You do not have permission to edit receipts.');
      return;
    }

    var $dlg = $('#er-dlg');
    $('#er-payment-id').val(data.paymentId);
    $('#er-bill-id').val(data.billId);
    $('#er-rcpt-no').text('RC-' + data.paymentId);
    $('#er-bill-no').text(data.billNo || data.billId);

    var infoText = '<b>' + esc(data.patientName || 'Patient') + '</b>';
    if (data.billed !== undefined) {
      infoText += ' · Total Billed: <b>' + inr(data.billed) + '</b>';
    }
    if (data.due !== undefined) {
      infoText += ' · Current Due: <b>' + inr(data.due) + '</b>';
    }
    $('#er-info').html(infoText);

    var rawMode = String(data.mode || 'cash').trim().toLowerCase();
    var modeVal = 'cash';
    if (rawMode === 'upi') modeVal = 'upi';
    else if (rawMode === 'card') modeVal = 'card';
    else if (rawMode === 'cheque') modeVal = 'cheque';
    else if (rawMode === 'neft_rtgs' || rawMode === 'bank transfer' || rawMode === 'bank_transfer') modeVal = 'neft_rtgs';
    else modeVal = 'cash';

    $('#er-mode').val(modeVal);
    $('#er-amt').val(parseFloat(data.amount) || '');
    $('#er-txn').val(data.txn || '');
    $('#er-reason').val('');
    $('#er-amt-err').attr('hidden', true);

    if (modeVal === 'cash') {
      $('#er-txn').val('').prop('disabled', true).attr('placeholder', 'Not needed for cash');
    } else {
      $('#er-txn').prop('disabled', false).attr('placeholder', (TXN_HINT[backendToFrontendMode(modeVal)] || 'Ref / Txn no.'));
    }

    $dlg.removeAttr('hidden');
    $('#er-amt').focus();
  }

  function closeReceiptEditModal() {
    $('#er-dlg').attr('hidden', true);
  }

  function openBillReceiptEdit(billId, billNo) {
    var bill = (state.allBills.bills || []).find(function (b) {
      return String(b.id) === String(billId) || String(b.no) === String(billNo);
    });

    var inMovements = (bill && bill.movements) ? bill.movements.filter(function (m) { return !m.out; }) : [];

    if (inMovements.length === 1) {
      var m = inMovements[0];
      openReceiptEditModal({
        billId: bill.id,
        billNo: bill.bill_number || bill.no || bill.id,
        paymentId: m.id,
        amount: m.amount,
        mode: m.mode,
        txn: m.txn,
        patientName: bill.patient_name,
        billed: bill.billed || bill.total,
        paid: bill.paid,
        due: bill.due
      });
    } else {
      $.ajax({
        url: window.ROUTES.billDetails + billId,
        type: 'GET',
        success: function (b) {
          var pays = (b.payments || []).filter(function (p) { return parseFloat(p.amount) > 0; });
          if (!pays.length) {
            snack('No payment receipt found for this bill.');
            return;
          }
          var p = pays[0];
          openReceiptEditModal({
            billId: b.id,
            billNo: b.bill_number || b.no || b.id,
            paymentId: p.id,
            amount: p.amount,
            mode: p.mode,
            txn: p.txn,
            patientName: (b.patient ? b.patient.name : ''),
            billed: b.billed || b.total,
            paid: b.paid,
            due: b.due
          });
        },
        error: function () {
          snack('Failed to load bill receipt details.');
        }
      });
    }
  }

  // --- COLLECT DUE ACTION ---
  function handleCollectDue(form) {
    var $form = $(form);
    var billId = $form.data('collect-bill');
    var mode = $form.find('[data-c="mode"]').val();
    var amt = parseFloat($form.find('[data-c="amt"]').val()) || 0;
    var txn = $form.find('[data-c="txn"]').val().trim();
    var $err = $form.find('[data-c="err"]');

    if (amt <= 0) {
      $err.text('Enter a valid amount collected now.');
      $form.find('[data-c="amt"]').focus();
      return;
    }

    if (window.BillingGuard && window.BillingGuard.isActive()) {
      window.BillingGuard.showAlert();
      return;
    }

    var $btn = $form.find('button[type="submit"]');
    $btn.prop('disabled', true);
    if (window.BillingGuard) {
      window.BillingGuard.start('Payment collection is in progress, do not refresh or move from page.');
    }

    $.ajax({
      url: window.ROUTES.collectDue,
      type: 'POST',
      data: {
        bill_id: billId,
        amount: amt,
        payment_mode: frontendToBackendMode(mode),
        txn_id: txn
      },
      success: function (res) {
        if (res.status === 'success' || res.success) {
          snack(inr(amt) + ' collected on bill ' + (res.bill_no || '') + '.');
          // Reload the bill document
          openBillDetails(billId);
          // If in All Bills tab, also refresh the list
          fetchBills();
        } else {
          $err.text(res.message || 'Failed to collect payment.');
        }
      },
      error: function (xhr) {
        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error processing collection.';
        $err.text(msg);
      },
      complete: function () {
        if (window.BillingGuard) {
          window.BillingGuard.stop();
        }
        $btn.prop('disabled', false);
      }
    });
  }

  // --- EVENT ATTACHMENTS ---
  $(document).ready(function () {
    // Ensure service search input is visible if user has billing permission
    var perms = window.USER_PERMISSIONS || {};
    var canCreate = (perms.opd_billing && perms.opd_billing.can_create !== undefined)
      ? !!perms.opd_billing.can_create
      : true;
    if (canCreate) {
      $('#svc-find').removeAttr('hidden');
    }
    if (!canDiscount()) {
      $('#bdisc').prop('disabled', true).prop('readonly', true);
      $('[data-bunit]').prop('disabled', true);
      if (!$('#bdisc').val()) {
        $('#bdisc').val('0.00');
      }
    }

    // Populate row menu item HTML
    $('#row-menu [data-act="print-bill"]').html(PRINT_IC + '<span>Print bill</span>');
    $('#row-menu [data-act="print-receipt"]').html(PRINT_IC + '<span>Print receipt</span>');
    $('#row-menu [data-act="edit-bill"]').html(I_EDIT + '<span>Edit bill</span>');
    $('#row-menu [data-act="edit-receipt"]').html(I_RCPT + '<span>Edit receipt</span>');
    $('#row-menu [data-act="cancel-bill"]').html(I_CANCEL + '<span>Cancel bill</span>');
    $('#row-menu [data-act="refund"]').html(I_BACK + '<span>Issue refund</span>');

    // Hide unauthorized actions
    if (!canRefund()) {
      $('#rf-new').attr('hidden', true);
      $('#rn-bar').attr('hidden', true);
      $('#ed-refund').attr('hidden', true);
      $('#cx-refund').attr('hidden', true);
    }
    if (!canCancelBill()) {
      $('#cx-only').attr('hidden', true);
      $('#ep-cancel').attr('hidden', true);
    }
    if (!canEditBill()) {
      $('#ep-edit').attr('hidden', true);
      $('#paid-edit').attr('hidden', true);
    }
    if (!canRefund() && !canCancelBill()) {
      $('#paid-refund').attr('hidden', true);
    }

    // Default dates for All Bills filter (1st of month to today)
    var today = new Date();
    var firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    function toISODate(d) {
      return d.toISOString().split('T')[0];
    }
    $('#f-from').val(toISODate(firstDay));
    $('#f-to').val(toISODate(today));

    // Tab switching event handlers
    $('#tab-new').on('click', function () { switchTab('new'); });
    $('#tab-all').on('click', function () { switchTab('all'); });
    $('#tab-ref').on('click', function () { switchTab('ref'); });

    // Determine initial tab from query param, hash, backend initial data, or sessionStorage
    function resolveInitialTab() {
      var urlParams = new URLSearchParams(window.location.search);
      var qTab = (urlParams.get('tab') || '').toLowerCase().trim();
      var hash = (window.location.hash || '').replace(/^#/, '').toLowerCase().trim();

      function mapTab(raw) {
        if (!raw) return null;
        if (raw === 'ref' || raw === 'refund' || raw === 'refunds') return 'ref';
        if (raw === 'all' || raw === 'bills' || raw === 'all-bills' || raw === 'all_bills') return 'all';
        if (raw === 'new' || raw === 'bill' || raw === 'new-bill') return 'new';
        return null;
      }

      var fromQuery = mapTab(qTab);
      if (fromQuery) return fromQuery;

      var fromHash = mapTab(hash);
      if (fromHash) return fromHash;

      var fromInitial = mapTab(window.INITIAL_DATA && window.INITIAL_DATA.activeTab);
      if (fromInitial) return fromInitial;

      var fromSession = null;
      try {
        fromSession = mapTab(sessionStorage.getItem('opd_billing_active_tab'));
      } catch (e) { }
      if (fromSession) return fromSession;

      var resolved = fromQuery || fromHash || fromInitial || fromSession;
      if (resolved === 'ref' && !canReadRefunds()) resolved = null;
      if (resolved === 'new' && !canCreate) resolved = null;
      if (resolved === 'all' && (!perms.opd_billing || !perms.opd_billing.can_read)) resolved = null;

      if (!resolved) {
        if ($('#tab-new').length && canCreate) return 'new';
        if ($('#tab-all').length) return 'all';
        if ($('#tab-ref').length && canReadRefunds()) return 'ref';
        return 'new';
      }
      return resolved;
    }

    setWorkflowStep(1);
    populateDiscountAuthorizers();
    var initialTab = resolveInitialTab();
    switchTab(initialTab, true);

    window.addEventListener('popstate', function () {
      var currentTab = resolveInitialTab();
      switchTab(currentTab, false);
    });

    // Patient search on New Bill tab
    initPatientSearch({
      container: '#finder',
      form: '#find-form',
      input: '#q',
      advBtn: '#adv-btn',
      advPanel: '#adv',
      advName: '#a-name',
      advPhone: '#a-phone',
      advGender: '#a-gender',
      advCount: '#adv-count',
      advClear: '#adv-clear',
      results: '#results',
      onSelect: function (patient) {
        selectPatient(patient);
      }
    });

    $('#pt-change').on('click', changePatient);
    $(document).off('billing:select-patient.billingWorkflow').on('billing:select-patient.billingWorkflow', function (e, patient) { selectPatient(patient); });
    $('#billing-start').off('.billingWorkflow').on('click.billingWorkflow', prepareBillingVisit);
    $('#billing-edit-visit').off('.billingWorkflow').on('click.billingWorkflow', function () { invalidateVisit(false); });
    $('#billing-visit-kind').off('.billingWorkflow').on('change.billingWorkflow', function () { invalidateVisit(true); updateVisitKind(); });

    // Doctor & spec
    $('#spec').on('change', onSpecChange);
    $('#doc').on('change', onDocChange);
    $('#charge').on('change', onChargeChange);
    $('#consult').on('change', updateConsultationItem);

    // Ref. By
    paintBillingRefby();
    $('#ref_by').on('change', function () {
      if ($(this).val() === '__new__') {
        openAddRefModal();
        return;
      }
      state.refBy = $(this).val();
      invalidateVisit(false);
    });
    $('#add-ref-btn').on('click', openAddRefModal);
    $('#add-ref-x, #add-ref-cancel').on('click', closeAddRefModal);
    $('#add-ref-submit').on('click', submitAddRef);
    $('#add-ref-dlg').on('click', function (e) {
      if (e.target === this) closeAddRefModal();
    });

    // Service catalog finder
    $('#svc-q').on('input', handleServiceSearch);
    $('#svc-q').on('click', function () {
      if ($('#svc-list').is(':hidden')) handleServiceSearch();
    });
    $('#svc-q').on('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if ($('#svc-list').is(':hidden')) handleServiceSearch();
        if (!svcHits.length) return;
        svcIdx = (svcIdx + (e.key === 'ArrowDown' ? 1 : -1) + svcHits.length) % svcHits.length;
        paintSvc();
      } else if (e.key === 'Enter') {
        e.preventDefault();
        e.stopPropagation();
        if (svcIdx > -1 && svcHits.length) {
          pickSvc(svcHits[svcIdx].id || svcHits[svcIdx].service_id);
        }
      } else if (e.key === 'Escape') {
        closeSvc();
      }
    });

    $(document).on('click', '#svc-list li[data-svc]', function () {
      var sId = $(this).data('svc');
      if (sId) pickSvc(sId);
    });

    // Close dropdowns when clicking outside
    $(document).on('click', function (e) {
      if (!$(e.target).closest('#svc-find').length) {
        closeSvc();
      }
      if (!$(e.target).closest('#finder').length) {
        $('#results').attr('hidden', true);
      }
      if (!$(e.target).closest('#row-menu, .al-more').length) {
        closeRowMenu();
      }
    });

    // Bill row quantity change
    $(document).on('change', '[data-item-qty]', function () {
      var idx = parseInt($(this).data('item-qty'));
      if (state.services[idx]) {
        state.services[idx].qty = parseInt($(this).val()) || 1;
        renderBillTable();
        calculateTotals();
      }
    });

    // Bill row discount unit toggle (₹ vs %)
    $(document).on('click', '[data-dunit]', function () {
      if (!canDiscount()) return;
      var unit = $(this).data('dunit');
      var idx = parseInt($(this).data('item-idx'));
      var item = state.services[idx];
      if (item) {
        var isPct = (unit === 'pct');
        item.discPct = isPct;
        var gross = item.rate * item.qty;
        if (isPct) {
          if (parseFloat(item.disc) > 100) item.disc = '100';
        } else {
          if (parseFloat(item.disc) > gross) item.disc = String(gross);
        }
        renderBillTable();
        calculateTotals();
        var $inp = $('#bill-rows [data-item-disc="' + idx + '"]');
        if ($inp.length) {
          $inp.focus().select();
        }
      }
    });

    // Bill row discount value input
    $(document).on('input change keyup paste blur', '[data-item-disc]', function () {
      if (!canDiscount()) return;
      var idx = parseInt($(this).data('item-disc'));
      var item = state.services[idx];
      if (item) {
        var only = $(this).val().replace(/[^\d.]/g, '');
        var gross = item.rate * item.qty;
        if (item.discPct) {
          if (parseFloat(only) > 100) only = '100';
        } else {
          if (parseFloat(only) > gross) only = String(gross);
        }
        if (only !== $(this).val()) {
          $(this).val(only);
        }
        item.disc = only;

        var d = discAmt(item.disc, item.discPct, gross);
        var net = Math.max(0, gross - d);

        var $row = $(this).closest('tr');
        $row.find('.l-amt').text(inr(net));
        var eq = (item.discPct && d > 0) ? '= ' + inr(d, 0) : '';
        $row.find('.d-eq').text(eq);

        calculateTotals();
      }
    });

    // Bill row editable rate input
    $(document).on('input', '[data-item-rate]', function () {
      var idx = parseInt($(this).data('item-rate'));
      var item = state.services[idx];
      if (item) {
        var only = $(this).val().replace(/[^\d.]/g, '');
        var newRate = parseFloat(only) || 0;
        item.rate = newRate;
        var gross = item.rate * item.qty;
        if (!item.discPct && parseFloat(item.disc) > gross) {
          item.disc = String(gross);
          $('#bill-rows [data-item-disc="' + idx + '"]').val(item.disc);
        }
        var d = discAmt(item.disc, item.discPct, gross);
        var net = Math.max(0, gross - d);

        var $row = $(this).closest('tr');
        $row.find('.l-amt').text(inr(net));
        var eq = (item.discPct && d > 0) ? '= ' + inr(d, 0) : '';
        $row.find('.d-eq').text(eq);

        calculateTotals();
      }
    });

    // Remove bill item
    $(document).on('click', '[data-item-del]', function () {
      var idx = parseInt($(this).data('item-del'));
      if (state.services[idx] && !state.services[idx].isConsult) {
        state.services.splice(idx, 1);
        renderBillTable();
        calculateTotals();
      }
    });

    // Bill discount unit toggle (₹ vs %)
    $('[data-bunit]').on('click', function () {
      if (!canDiscount()) return;
      var unit = $(this).data('bunit');
      $('[data-bunit]').attr('aria-pressed', 'false');
      $(this).attr('aria-pressed', 'true');
      state.billDiscountPct = (unit === 'pct');
      var only = $('#bdisc').val().replace(/[^\d.]/g, '');
      var subtotal = 0, totalItemDiscount = 0;
      state.services.forEach(function (s) {
        var gross = s.rate * s.qty;
        subtotal += gross;
        totalItemDiscount += discAmt(s.disc, s.discPct, gross);
      });
      var subAfterItemDisc = Math.max(0, subtotal - totalItemDiscount);
      if (state.billDiscountPct) {
        if (parseFloat(only) > 100) only = '100';
      } else {
        if (parseFloat(only) > subAfterItemDisc) only = String(subAfterItemDisc);
      }
      $('#bdisc').val(only);
      calculateTotals();
      $('#bdisc').focus().select();
    });

    $('#bdisc').on('input change keyup paste blur', function () {
      if (!canDiscount()) return;
      var only = $(this).val().replace(/[^\d.]/g, '');
      var subtotal = 0, totalItemDiscount = 0;
      state.services.forEach(function (s) {
        var gross = s.rate * s.qty;
        subtotal += gross;
        totalItemDiscount += discAmt(s.disc, s.discPct, gross);
      });
      var subAfterItemDisc = Math.max(0, subtotal - totalItemDiscount);
      if (state.billDiscountPct) {
        if (parseFloat(only) > 100) only = '100';
      } else {
        if (parseFloat(only) > subAfterItemDisc) only = String(subAfterItemDisc);
      }
      if (only !== $(this).val()) {
        $(this).val(only);
      }
      calculateTotals();
    });

    $(document).on('change', '#discount_auth_by', function () {
      if ($(this).val()) {
        $(this).removeClass('is-invalid');
      }
    });

    // Payment matrix row interactions
    $('#pay-add').on('click', addPaymentRow);

    $(document).on('click', '[data-pay-del]', function () {
      var idx = parseInt($(this).data('pay-del'));
      removePaymentRow(idx);
    });

    $(document).on('change', '[data-pk="mode"]', function () {
      var idx = parseInt($(this).data('pay-idx'));
      var mode = $(this).val();
      if (state.payments[idx]) {
        state.payments[idx].mode = mode;
        var $row = $(this).closest('.pay-row');
        var $txn = $row.find('[data-pk="txn"]');
        $txn.prop('disabled', mode === 'Cash');
        $txn.attr('placeholder', TXN_HINT[mode] || '');
        if (mode === 'Cash') $txn.val('');
      }
    });

    $(document).on('input', '[data-pk="amt"]', function () {
      var idx = parseInt($(this).data('pay-idx'));
      if (state.payments[idx]) {
        var only = $(this).val().replace(/[^\d.]/g, '');
        if (only !== $(this).val()) {
          $(this).val(only);
        }
        state.payments[idx].amt = only;
        state.payAuto = false;
        calculateTotals();
      }
    });

    $(document).on('input', '[data-pk="txn"]', function () {
      var idx = parseInt($(this).data('pay-idx'));
      if (state.payments[idx]) {
        state.payments[idx].txn = $(this).val();
      }
    });

    // Clear, Save Draft and Collect buttons
    $('#ob-clear').on('click', resetBillForm);
    $('#save-only').on('click', function () {
      submitBill(true);
    });
    $('#collect').on('click', function () {
      submitBill(false);
    });

    // Paid view New Bill button
    $('#paid-next').on('click', function () {
      resetBillForm();
      changePatient();
    });

    // All bills filter status buttons (All, Fully paid, Due)
    $('#f-status button').on('click', function () {
      $('#f-status button').attr('aria-pressed', 'false');
      $(this).attr('aria-pressed', 'true');
    });

    $('#all-form').on('submit', function (e) {
      e.preventDefault();
      fetchBills(true);
    });

    $('#f-clear').on('click', function () {
      $('#f-no').val('');
      $('#f-pt').val('');
      $('#f-status button').attr('aria-pressed', 'false');
      $('#f-status button[data-st="all"]').attr('aria-pressed', 'true');
      var t = new Date();
      $('#f-to').val(toISODate(t));
      $('#f-from').val(toISODate(new Date(t.getFullYear(), t.getMonth(), 1)));
      fetchBills(true);
    });

    // Pagination clicks
    $(document).on('click', '.pg-btn', function () {
      var p = $(this).data('pg');
      if (p && p >= 1) {
        state.allBills.page = p;
        fetchBills(false);
      }
    });

    $('#pg-size').on('change', function () {
      state.allBills.size = parseInt($(this).val()) || 10;
      fetchBills(true);
    });

    // Open bill details
    $(document).on('click', '[data-open-bill]', function () {
      var billId = $(this).data('open-bill');
      if (billId) openBillDetails(billId);
    });

    $('#al-back').on('click', backToAllBillsList);

    // Toggle receipts & movements accordion under bill row
    $(document).on('click', '.al-exp', function (e) {
      e.stopPropagation();
      var $btn = $(this);
      var billNo = $btn.data('exp');
      var isExpanded = $btn.attr('aria-expanded') === 'true';
      var $parentRow = $btn.closest('tr');
      var $childRows = $('tr.al-pay[data-of="' + billNo + '"]');

      $btn.attr('aria-expanded', !isExpanded);
      $parentRow.toggleClass('is-open', !isExpanded);
      $childRows.prop('hidden', isExpanded);
    });

    // Print/preview individual payment receipt or refund slip
    $(document).on('click', '.ap-link', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var paymentId = $(this).data('payment-id');
      var billId = $(this).data('bill-id');
      var isOut = $(this).data('is-out') === '1' || $(this).data('is-out') === 1;

      var url = '';
      if (window.ROUTES && window.ROUTES.paymenthistoryReceipt && paymentId) {
        url = window.ROUTES.paymenthistoryReceipt + '/' + paymentId + '?header=true';
      } else if (window.ROUTES && window.ROUTES.showBillPaymentReceipt && billId) {
        url = window.ROUTES.showBillPaymentReceipt + '/' + billId;
      } else {
        url = '/show_bill/' + billId;
      }

      printUrlOnSamePage(url);
    });

    // Kebab Menu trigger
    $(document).on('click', '.al-more', function (e) {
      e.stopPropagation();
      var billNo = $(this).data('more');
      var billId = $(this).data('bill-id');
      var isExpanded = $(this).attr('aria-expanded') === 'true';
      if (isExpanded) {
        closeRowMenu();
      } else {
        openRowMenu($(this), billNo, billId);
      }
    });

    $('#row-menu [data-act]').on('click', function () {
      var act = $(this).data('act');
      var no = state.allBills.activeMenuNo;
      var id = state.allBills.activeMenuId || no;
      closeRowMenu();

      if (act === 'print-bill') {
        var base = (window.ROUTES && window.ROUTES.showBill) ? window.ROUTES.showBill : '/show_bill';
        var url = base.replace(/\/+$/, '') + '/' + id;
        printUrlOnSamePage(url);
      } else if (act === 'print-receipt') {
        var base = (window.ROUTES && window.ROUTES.showBillPaymentReceipt) ? window.ROUTES.showBillPaymentReceipt : '/show_bill_payment_receipt';
        var url = base.replace(/\/+$/, '') + '/' + id;
        printUrlOnSamePage(url);
      } else if (act === 'refund') {
        if (!canRefund()) {
          snack('You do not have permission to issue refunds.');
          return;
        }
        switchTab('ref');
        openNewRefund(null, [id], no);
      } else if (act === 'edit-bill') {
        if (!canEditBill()) {
          snack('You do not have permission to edit bills.');
          return;
        }
        var targetBill = (state.allBills.bills || []).find(function (b) {
          return String(b.id) === String(id) || String(b.no) === String(no);
        });
        if (targetBill && parseInt(targetBill.is_cancelled) === 1) {
          snack('Cancelled bills cannot be edited.');
          return;
        }
        if (targetBill && (parseInt(targetBill.is_refund_bill) === 1 || parseInt(targetBill.is_refunded) === 1 || parseFloat(targetBill.refunded || 0) > 0 || parseInt(targetBill.has_refunds) === 1)) {
          snack('Bills with refunds cannot be edited.');
          return;
        }
        openEditPick(id);
      } else if (act === 'edit-receipt') {
        if (!canEditBill()) {
          snack('You do not have permission to edit receipts.');
          return;
        }
        var targetBill = (state.allBills.bills || []).find(function (b) {
          return String(b.id) === String(id) || String(b.no) === String(no);
        });
        if (targetBill && parseInt(targetBill.is_cancelled) === 1) {
          snack('Cancelled bills cannot be edited.');
          return;
        }
        if (targetBill && (parseInt(targetBill.is_refund_bill) === 1 || parseInt(targetBill.is_refunded) === 1 || parseFloat(targetBill.refunded || 0) > 0 || parseInt(targetBill.has_refunds) === 1)) {
          snack('Bills with refunds cannot be edited.');
          return;
        }
        openBillReceiptEdit(id, no);
      } else if (act === 'cancel-bill') {
        if (!canCancelBill()) {
          snack('You do not have permission to cancel bills.');
          return;
        }
        var targetBill = (state.allBills.bills || []).find(function (b) {
          return String(b.id) === String(id) || String(b.no) === String(no);
        });
        if (targetBill && parseInt(targetBill.is_cancelled) === 1) {
          snack('This bill is already cancelled.');
          return;
        }
        openEditPick(id, 'cancel');
      }
    });

    // Print Bill button
    $(document).on('click', '[data-doc-act="print-bill"]', function () {
      var id = $(this).data('bill-id') || $(this).data('print');
      if (id) {
        var base = (window.ROUTES && window.ROUTES.showBill) ? window.ROUTES.showBill : '/show_bill';
        var url = base.replace(/\/+$/, '') + '/' + id;
        printUrlOnSamePage(url);
      }
    });

    // Print Receipt button (and table data-print)
    $(document).on('click', '[data-doc-act="print-receipt"], [data-print-receipt]', function () {
      var billId = $(this).data('bill-id');
      var paymentId = $(this).data('payment-id');
      var url = '';
      if (window.ROUTES && window.ROUTES.paymenthistoryReceipt && paymentId) {
        url = window.ROUTES.paymenthistoryReceipt + '/' + paymentId + '?header=true';
      } else if (window.ROUTES && window.ROUTES.showBillPaymentReceipt && billId) {
        url = window.ROUTES.showBillPaymentReceipt + '/' + billId;
      } else if (billId) {
        url = '/show_bill_payment_receipt/' + billId;
      }
      if (url) {
        printUrlOnSamePage(url);
      }
    });

    // Edit Bill button in document view
    $(document).on('click', '[data-doc-act="edit-bill"]', function () {
      var id = $(this).data('bill-id');
      if (id) {
        if (!canEditBill()) {
          snack('You do not have permission to edit bills.');
          return;
        }
        openEditPick(id);
      }
    });

    // Cancel Bill button in document view
    $(document).on('click', '[data-doc-act="cancel-bill"]', function () {
      var id = $(this).data('bill-id');
      if (id) {
        if (!canCancelBill()) {
          snack('You do not have permission to cancel bills.');
          return;
        }
        openEditPick(id, 'cancel');
      }
    });

    $(document).on('click', '[data-doc-act="wa"], [data-wa]', function () {
      var id = $(this).data('bill-id') || $(this).data('wa');
      if (id) {
        $.ajax({
          url: window.ROUTES.sendWhatsAppBill,
          type: 'POST',
          data: { bill_id: id },
          success: function (res) {
            snack('Bill sent on WhatsApp successfully.');
          },
          error: function () {
            snack('Failed to send bill on WhatsApp.');
          }
        });
      }
    });

    // Collect Due form in Bill Document
    $(document).on('submit', '[data-collect-bill]', function (e) {
      e.preventDefault();
      handleCollectDue(this);
    });

    $(document).on('change', '[data-c="mode"]', function () {
      var mode = $(this).val();
      var $form = $(this).closest('form');
      var $txn = $form.find('[data-c="txn"]');
      $txn.prop('disabled', mode === 'Cash');
      $txn.attr('placeholder', TXN_HINT[mode] || '');
      if (mode === 'Cash') $txn.val('');
    });

    // --- EDIT RECEIPT EVENTS ---
    $(document).on('click', '[data-act="edit-receipt-item"]', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (!canEditBill()) {
        snack('You do not have permission to edit receipts.');
        return;
      }

      var $el = $(this);
      var paymentId = $el.data('payment-id');
      var billId = $el.data('bill-id');
      var billNo = $el.data('bill-no') || billId;
      var amt = $el.data('amount');
      var mode = $el.data('mode');
      var txn = $el.data('txn');

      var bill = (state.allBills.bills || []).find(function (b) {
        return String(b.id) === String(billId);
      });

      openReceiptEditModal({
        billId: billId,
        billNo: billNo,
        paymentId: paymentId,
        amount: amt,
        mode: mode,
        txn: txn,
        patientName: bill ? bill.patient_name : '',
        billed: bill ? (bill.billed || bill.total) : undefined,
        paid: bill ? bill.paid : undefined,
        due: bill ? bill.due : undefined
      });
    });

    $('#er-mode').on('change', function () {
      var mode = $(this).val();
      if (mode === 'cash') {
        $('#er-txn').val('').prop('disabled', true).attr('placeholder', 'Not needed for cash');
      } else {
        $('#er-txn').prop('disabled', false).attr('placeholder', (TXN_HINT[backendToFrontendMode(mode)] || 'Ref / Txn no.'));
      }
    });

    $('#er-x, #er-cancel').on('click', function () {
      closeReceiptEditModal();
    });

    $('#er-form').on('submit', function (e) {
      e.preventDefault();
      if (!canEditBill()) {
        snack('You do not have permission to edit receipts.');
        return;
      }

      var paymentId = $('#er-payment-id').val();
      var billId = $('#er-bill-id').val();
      var amt = parseFloat($('#er-amt').val());
      var mode = $('#er-mode').val();
      var txn = $('#er-txn').val().trim();
      var reason = $('#er-reason').val().trim();

      if (!paymentId || isNaN(amt) || amt <= 0) {
        $('#er-amt-err').text('Please enter a valid amount greater than 0.').removeAttr('hidden');
        $('#er-amt').focus();
        return;
      }
      $('#er-amt-err').attr('hidden', true);

      if (mode !== 'cash' && !txn && (mode === 'upi' || mode === 'card')) {
        snack('Transaction ID is required for ' + backendToFrontendMode(mode) + '.');
        $('#er-txn').focus();
        return;
      }

      if (!reason) {
        snack('Please enter a reason for updating this receipt.');
        $('#er-reason').focus();
        return;
      }

      var $btn = $('#er-submit');
      var origText = $btn.text();
      $btn.prop('disabled', true).text('Updating...');

      var csrfToken = $('meta[name="csrf-token"]').attr('content') || (window.INITIAL_DATA ? window.INITIAL_DATA.csrfToken : '');

      $.ajax({
        url: window.ROUTES.updateReceipt,
        type: 'POST',
        data: {
          _token: csrfToken,
          payment_id: paymentId,
          amount: amt,
          payment_mode: mode,
          transaction_id: txn,
          remarks: reason
        },
        success: function (res) {
          $btn.prop('disabled', false).text(origText);
          if (res.status === 'success' || res.success) {
            closeReceiptEditModal();
            snack(res.message || 'Receipt updated successfully.');
            // Refresh views if applicable
            if (billId) {
              if (!$('#all-detail').is(':hidden')) {
                openBillDetails(billId);
              }
            }
            // If viewing refund detail voucher screen, update it live
            if (!$('#rn-done').is(':hidden') && state.refunds.currentRefund) {
              state.refunds.currentRefund.amount = res.amount || amt;
              state.refunds.currentRefund.mode = res.mode || mode;
              $('#rn-done-h').text(inr(res.amount || amt) + ' refunded to ' + (state.refunds.currentRefund.patient_name || 'Patient'));
              $('#rn-done-t').text('Refund ' + state.refunds.currentRefund.refund_no + ' · ' + (res.mode || mode) + ' · against ' + (state.refunds.currentRefund.bills || []).map(function (n) { return 'bill ' + n; }).join(', '));
              $('#rn-done-doc').html(renderRefundDoc(state.refunds.currentRefund));
            }
            if (typeof fetchBills === 'function') {
              fetchBills();
            }
            if (typeof fetchRefunds === 'function' && state.activeTab === 'ref') {
              fetchRefunds();
            }
          } else {
            snack(res.message || 'Failed to update receipt.');
          }
        },
        error: function (xhr) {
          $btn.prop('disabled', false).text(origText);
          var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error updating receipt.';
          snack(msg);
        }
      });
    });

    // Keyboard shortcuts
    $(document).on('keydown', function (e) {
      var tag = (e.target.tagName || '').toUpperCase();
      var isInput = tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA';

      if (e.key === '/' && !isInput) {
        if (state.activeTab === 'new') {
          e.preventDefault();
          $('#q').focus().select();
          return;
        } else if (state.activeTab === 'ref' && !$('#ref-new').is(':hidden')) {
          e.preventDefault();
          $('#rn-q').focus().select();
          return;
        }
      }

      if (e.key === 'Escape') {
        closeRowMenu();
        if (!$('#er-dlg').is(':hidden')) {
          closeReceiptEditModal();
          return;
        }
        if (!$('#all-detail').is(':hidden')) {
          backToAllBillsList();
          return;
        }
        $('#results').attr('hidden', true);
        $('#rn-results').attr('hidden', true);
        $('#svc-list').attr('hidden', true);
      }

      if (e.altKey && !e.ctrlKey && !e.metaKey) {
        var k = (e.key || '').toLowerCase();
        if (k === 's' && state.activeTab === 'new') {
          e.preventDefault();
          submitBill();
        } else if (k === 'f') {
          e.preventDefault();
          if (state.activeTab === 'new') {
            $('#adv-btn').trigger('click');
          } else if (state.activeTab === 'ref' && !$('#ref-new').is(':hidden')) {
            $('#rn-adv-btn').trigger('click');
          }
        }
      }
    });
  });



  // ══════════════════════════════════════════════════════════════════════
  // ═════════════════════ REFUNDS SUBSYSTEM (TAB 3) ══════════════════════
  // ══════════════════════════════════════════════════════════════════════

  var RF_ST_META = {
    pending:  { t: 'Pending',   cls: 'pill-info' },
    approved: { t: 'Approved',  cls: 'pill-info' },
    issued:   { t: 'Completed', cls: 'pill-ok' },
    rejected: { t: 'Rejected',  cls: 'pill-err' }
  };

  function isOpenRefund(st) {
    var s = (st || '').toLowerCase();
    return s === 'pending' || s === 'approved';
  }

  function stamp(at, by) {
    if (!at) return '<span class="rf-wait">—</span>';
    return esc(at) + (by ? ('<span class="soft-l">' + esc(by) + '</span>') : '');
  }

  function rfActs(r, where) {
    var n = r.id || r.refund_no;
    var st = (r.status || '').toLowerCase();
    var out = '';

    if (st === 'pending') {
      if (canApproveRefund()) {
        out = '<button type="button" class="btn btn-ok" data-rf-act="approve" data-no="' + n + '">Approve</button>' +
          '<button type="button" class="btn btn-rej" data-rf-act="reject" data-no="' + n + '">Reject</button>';
      } else {
        out = '<span class="rf-note">With admin</span>' +
          (where === 'list' ? ' <button type="button" class="btn btn-text" data-rf-act="view" data-no="' + n + '">View</button>' : '');
      }
    } else if (st === 'approved') {
      if (canIssueRefund()) {
        out = '<button type="button" class="btn btn-filled" data-rf-act="issue" data-no="' + n + '">Issue refund amount</button>';
      } else {
        out = '<span class="rf-note">With the desk to issue</span>' +
          (where === 'list' ? ' <button type="button" class="btn btn-text" data-rf-act="view" data-no="' + n + '">View</button>' : '');
      }
    }

    if (where === 'list' && (st === 'issued' || st === 'rejected')) {
      out = '<button type="button" class="btn btn-text" data-rf-act="view" data-no="' + n + '">View</button>';
    }
    return out;
  }

  function paintRole() {
    var canInit = canInitiateRefund();
    $('#rf-new').prop('hidden', !canInit);
    $('#rn-again').prop('hidden', !canInit);
  }

  function fetchRefunds(page) {
    page = page || state.refunds.page || 1;
    state.refunds.page = page;

    var params = {
      page: page,
      per_page: state.refunds.size || 10,
      refund_no: $('#rf-no').val().trim(),
      search: $('#rf-pt').val().trim(),
      from_date: $('#rf-from').val(),
      to_date: $('#rf-to').val(),
      status_group: state.refunds.filterGroup || 'all'
    };

    paintRole();

    $.ajax({
      url: window.ROUTES.refundsList,
      type: 'GET',
      data: params,
      success: function (res) {
        if (res.status === 'success' || res.data) {
          state.refunds.data = res.data || [];
          state.refunds.total = (res.meta && res.meta.total) ? res.meta.total : state.refunds.data.length;
          renderRefundsSummary(res.summary);
          renderRefundsTable(state.refunds.data);
        }
      },
      error: function () {
        snack('Could not load refunds list.');
      }
    });
  }

  function renderRefundsSummary(sum) {
    if (!sum) {
      $('#rf-sum').empty();
      return;
    }
    var count = sum.total_count || 0;
    var total = sum.total_amount || 0;
    var waitCount = sum.pending_count || 0;
    var waitSum = sum.pending_amount || 0;
    var giveCount = sum.approved_count || 0;
    var giveSum = sum.approved_amount || 0;

    var html = count > 0
      ? '<span><b>' + count + '</b> ' + (count === 1 ? 'refund' : 'refunds') + '</span>' +
        '<span>Refunded <b>' + inr(total) + '</b></span>' +
        (waitCount ? '<span>Awaiting approval <b>' + waitCount + ' · ' + inr(waitSum) + '</b></span>' : '') +
        (giveCount ? '<span>Approved, to issue <b>' + giveCount + ' · ' + inr(giveSum) + '</b></span>' : '')
      : '';

    $('#rf-sum').html(html);

    var curGroup = state.refunds.filterGroup || 'all';
    var openCount = (sum.open_count !== undefined) ? sum.open_count : (waitCount + giveCount);
    var completedCount = (sum.completed_count !== undefined) ? sum.completed_count : count;
    var rejectedCount = sum.rejected_count || 0;

    $('#rf-st button[data-g="all"] .cnt').text(count);
    $('#rf-st button[data-g="open"] .cnt').text(openCount);
    $('#rf-st button[data-g="issued"] .cnt').text(completedCount);
    $('#rf-st button[data-g="rejected"] .cnt').text(rejectedCount);
    $('#rf-st button[data-g]').each(function () {
      $(this).attr('aria-pressed', $(this).data('g') === curGroup ? 'true' : 'false');
    });
  }

  function renderRefundsTable(rows) {
    var $tbody = $('#rf-rows');
    $tbody.empty();

    if (!rows || !rows.length) {
      var msg = !state.refunds.total ? 'No refund has been initiated yet.'
        : 'No refund in this group. Widen dates, or check the number.';
      $('#rf-none').text(msg).removeAttr('hidden');
      $('#rf-tbl').attr('hidden', true);
      return;
    }
    $('#rf-none').attr('hidden', true);
    $('#rf-tbl').removeAttr('hidden');

    var html = '';
    $.each(rows, function (i, r) {
      var st = (r.status || 'issued').toLowerCase();
      var stObj = RF_ST_META[st] || { t: 'Completed', cls: 'pill-ok' };
      var bills = r.bills && r.bills.length ? r.bills : [r.bill_id || r.bill_no];
      var firstBill = bills[0];

      var againstHtml = 'Bill ' + esc(firstBill);
      if (bills.length > 1) {
        againstHtml += '<button type="button" class="rf-more" data-bills="' + r.id + '" aria-expanded="false" aria-label="All ' + bills.length + ' bills in R' + r.refund_no + '">+' + (bills.length - 1) + '</button>';
      }
      againstHtml += '<span class="soft-l" title="' + esc(r.reason) + '">' + esc(r.reason) + '</span>';

      var apprHtml = '';
      if (st === 'pending') {
        apprHtml = '<span class="rf-wait">Waiting</span>';
      } else if (st === 'rejected') {
        apprHtml = '<span class="rf-rej">' + esc(r.rejected_at || r.approved_at || r.date) + '</span>' +
          (r.rejected_why ? '<button type="button" class="rf-why-btn" data-why="' + r.id + '" aria-expanded="false" aria-label="Why R' + r.refund_no + ' was rejected" title="Why it was rejected"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><circle cx="12" cy="7.8" r=".6" fill="currentColor"/></svg></button>' : '') +
          '<span class="soft-l">' + esc(r.rejected_by || 'Hospital Admin') + '</span>';
      } else {
        apprHtml = stamp(r.approved_at, r.approved_by);
      }

      var issHtml = '';
      if (st === 'issued') {
        issHtml = stamp(r.issued_at || r.date, r.issued_by);
      } else if (st === 'approved') {
        issHtml = '<span class="rf-wait">To issue</span>';
      } else {
        issHtml = '<span class="rf-wait">—</span>';
      }

      html += '<tr data-no="' + r.id + '" data-st="' + st + '">' +
        '<td data-k="Refund no." class="rf-no"><button type="button" class="link" data-rf-act="view" data-no="' + r.id + '">R' + esc(r.refund_no) + '</button></td>' +
        '<td data-k="Status" class="rf-stp"><span class="pill ' + stObj.cls + '">' + stObj.t + '</span></td>' +
        '<td data-k="Patient" class="rf-pt"><span class="strong" title="' + esc(r.patient_name) + '">' + esc(r.patient_name) + '</span><span class="soft-l">' + esc(r.patient_uhid) + '</span></td>' +
        '<td data-k="Against" class="rf-against">' + againstHtml + '</td>' +
        '<td data-k="Amount" class="num"><span class="rf-amt">−' + inr(r.amount) + '</span><span class="soft-l"' + (r.transaction_id ? (' title="' + esc(r.transaction_id) + '"') : '') + '>' + esc(r.mode) + '</span></td>' +
        '<td data-k="Initiated" class="rf-stamp">' + stamp(r.initiated_at || r.date, r.initiated_by) + '</td>' +
        '<td data-k="Approval" class="rf-stamp">' + apprHtml + '</td>' +
        '<td data-k="Issued to patient" class="rf-stamp">' + issHtml + '</td>' +
        '<td data-k="Action" class="act"><span class="rf-acts">' + rfActs(r, 'list') + '</span></td>' +
        '</tr>';
    });

    $tbody.html(html);
  }

  // Floating Popover for Rejection Reason and Multi-Bill Allocations
  var popBtn = null;
  function closeWhy(back) {
    var $pop = $('#rf-pop');
    if ($pop.is(':hidden')) return;
    $pop.attr('hidden', true);
    var b = popBtn;
    popBtn = null;
    if (b && $(b).length) {
      $(b).attr('aria-expanded', 'false');
      if (back) $(b).focus();
    }
  }

  function getRefundById(id) {
    var num = String(id);
    var hit = null;
    $.each(state.refunds.data, function (i, r) {
      if (String(r.id) === num || String(r.refund_no) === num) {
        hit = r;
        return false;
      }
    });
    return hit;
  }

  // Unified Approval Trail Dialog
  var rq = null;
  function askRq(o) {
    rq = o;
    $('#rq-t').text(o.t);
    $('#rq-d1').text(o.d1);
    $('#rq-d2').text(o.d2 || '').toggle(!!o.d2);
    $('#rq-why-f').prop('hidden', o.kind !== 'reject');
    $('#rq-why').val('');
    $('#rqwhy-err').text('').prop('hidden', true);
    $('#rq-yes').text(o.yes).attr('class', 'btn ' + (o.kind === 'reject' ? 'btn-filled cx-danger' : o.kind === 'approve' ? 'btn-ok' : 'btn-filled'));
    $('#rq-ask').removeAttr('hidden');
    setTimeout(function () {
      if (o.kind === 'reject') {
        $('#rq-why').focus();
      } else {
        $('#rq-yes').focus();
      }
    }, 50);
  }

  function closeRq() {
    $('#rq-ask').attr('hidden', true);
    var o = rq;
    rq = null;
    return o;
  }

  function onRfAct(e) {
    var $btn = $(e.target).closest('[data-rf-act]');
    if (!$btn.length) return;
    var no = $btn.data('no');
    var act = $btn.data('rf-act');
    var r = getRefundById(no) || state.refunds.currentRefund;
    if (!r) return;

    var who = r.patient_name || 'the patient';
    var against = (r.bills && r.bills.length ? r.bills : [r.bill_id || r.bill_no]).map(function (n) { return 'bill ' + n; }).join(', ');

    if (act === 'view') {
      openRefundView(r);
      return;
    }
    if (act === 'approve') {
      if (!canApproveRefund()) {
        snack('You do not have permission to approve refunds.');
        return;
      }
      askRq({
        kind: 'approve',
        no: r.id,
        t: 'Approve refund R' + r.refund_no + '?',
        d1: inr(r.amount) + ' to ' + who + ' by ' + r.mode + ', against ' + against + '. Reason: ' + (r.reason || 'Service cancelled') + '.',
        d2: 'Initiated by ' + (r.initiated_by || 'Front desk') + ' on ' + (r.initiated_at || r.date) + '. Once approved, the desk hands the money over.',
        yes: 'Approve'
      });
      return;
    }
    if (act === 'reject') {
      if (!canApproveRefund()) {
        snack('You do not have permission to reject refunds.');
        return;
      }
      askRq({
        kind: 'reject',
        no: r.id,
        t: 'Reject refund R' + r.refund_no + '?',
        d1: inr(r.amount) + ' to ' + who + ', initiated by ' + (r.initiated_by || 'Front desk') + '. Rejecting releases the amount back on ' + against + '.',
        yes: 'Reject refund'
      });
      return;
    }
    if (act === 'issue') {
      if (!canIssueRefund()) {
        snack('You do not have permission to issue refunds.');
        return;
      }
      askRq({
        kind: 'issue',
        no: r.id,
        t: 'Issue ' + inr(r.amount) + ' to ' + who + '?',
        d1: 'Hand ' + inr(r.amount) + ' back by ' + r.mode + (r.transaction_id ? (' (' + r.transaction_id + ')') : '') + ', against ' + against + '.',
        d2: 'Approved by ' + (r.approved_by || 'Hospital Admin') + ' on ' + (r.approved_at || r.date) + '. Issuing records the refund on the bill and prints the slip.',
        yes: 'Issue ' + inr(r.amount)
      });
      return;
    }
  }

  function openRefundView(r) {
    $('#ref-list').attr('hidden', true);
    $('#ref-new').removeAttr('hidden');
    $('#rn-find').hide();
    state.refunds.origin = null;
    $('#rn-back-t').text('All refunds');
    showRefundDone(r, true);
  }

  function openNewRefund(patientId, preselectBillIds, origin) {
    if (!canRefund()) {
      snack('You do not have permission to issue refunds.');
      return;
    }
    $('#ref-list').attr('hidden', true);
    $('#ref-new').removeAttr('hidden');
    $('#rn-done').attr('hidden', true);
    $('#rn-step2').attr('hidden', true);
    $('#rn-bar').attr('hidden', true);

    state.refunds.origin = origin || null;
    state.refunds.selectedBills = [];
    state.refunds.patient = null;
    state.refunds.bills = [];
    state.refunds.mode = 'Cash';
    state.refunds.amount = 0;

    $('#rn-back-t').text(origin ? ('Bill ' + origin) : 'All refunds');
    $('#rn-q').val('');
    $('#rn-results').attr('hidden', true).empty();
    $('#rn-adv').attr('hidden', true);
    $('#rn-adv-btn').attr('aria-expanded', 'false');
    $('#rn-adv-count').text('').attr('hidden', true);
    $('#rn-a-name, #rn-a-phone, #rn-a-gender').val('');
    $('#rn-find').show();

    if (patientId) {
      selectRefundPatient(patientId, preselectBillIds);
    } else if (preselectBillIds && preselectBillIds.length) {
      // Look up bill to get patient ID
      $.ajax({
        url: window.ROUTES.billDetails + preselectBillIds[0],
        type: 'GET',
        success: function (bill) {
          if (bill && bill.patient && bill.patient.id) {
            selectRefundPatient(bill.patient.id, preselectBillIds);
          } else {
            $('#rn-empty').removeAttr('hidden');
            $('#rn-body').attr('hidden', true);
          }
        },
        error: function () {
          $('#rn-empty').removeAttr('hidden');
          $('#rn-body').attr('hidden', true);
        }
      });
    } else {
      $('#rn-empty').removeAttr('hidden');
      $('#rn-body').attr('hidden', true);
      setTimeout(function () { $('#rn-q').focus(); }, 100);
    }
  }

  function backToRefundsList() {
    $('#ref-new').attr('hidden', true);
    $('#ref-list').removeAttr('hidden');
    $('#rn-bar').attr('hidden', true);
    fetchRefunds(1);
    window.scrollTo(0, 0);
  }

  function selectRefundPatient(patientId, preselectBillIds) {
    $('#rn-results').attr('hidden', true).empty();
    $('#rn-q').val('');
    $('#rn-find').hide();

    $.ajax({
      url: window.ROUTES.patientRefundableBills + patientId,
      type: 'GET',
      success: function (res) {
        if (!res.patient) {
          snack('Patient details not found.');
          $('#rn-find').show();
          return;
        }
        state.refunds.patient = res.patient;
        state.refunds.bills = res.bills || [];
        state.refunds.filter = 'all';

        $('#rn-empty').attr('hidden', true);
        $('#rn-body').removeAttr('hidden');

        var p = res.patient;
        $('#rn-name').text(p.name);
        var metaParts = ['UHID ' + p.uhid];
        if (p.age) metaParts.push(p.age + ' yrs');
        if (p.gender) metaParts.push(p.gender);
        if (p.mobile) metaParts.push('+91 ' + p.mobile);
        $('#rn-meta').text(metaParts.join(' · '));

        paintRnFilter();
        renderRefundBillsTable(state.refunds.bills, preselectBillIds);
      },
      error: function () {
        snack('Could not load patient billing history.');
        $('#rn-find').show();
      }
    });
  }

  function paintRnFilter() {
    var all = state.refunds.bills || [];
    var can = all.filter(function (b) { return (parseFloat(b.refundable) || 0) > 0 && !b.is_cancelled; }).length;
    var currentFilter = state.refunds.filter || 'all';

    var html = '<button type="button" class="btn btn-tonal" data-rn-f="can" aria-pressed="' + (currentFilter === 'can') + '">Can be refunded (' + can + ')</button>' +
      '<button type="button" class="btn btn-tonal" data-rn-f="all" aria-pressed="' + (currentFilter === 'all') + '">All bills (' + all.length + ')</button>';

    $('#rn-filter').html(html);
    $('#rn-filter').prop('hidden', all.length < 2);
  }

  function renderRefundBillsTable(bills, preselectBillIds) {
    var $tbody = $('#rn-rows');
    $tbody.empty();

    var currentFilter = state.refunds.filter || 'all';
    var picked = state.refunds.selectedBills || [];
    var preIds = (preselectBillIds || []).map(function (v) { return String(v); });

    var filtered = (bills || []).filter(function (b) {
      var bIdStr = String(b.id);
      var isPicked = picked.indexOf(b.id) > -1 || picked.indexOf(bIdStr) > -1 || preIds.indexOf(bIdStr) > -1;
      var canRefund = (parseFloat(b.refundable) || 0) > 0 && !b.is_cancelled;
      return currentFilter === 'all' || canRefund || isPicked;
    });

    if (!filtered.length) {
      $('#rn-tbl').attr('hidden', true);
      var msg = (!bills || !bills.length)
        ? 'This patient has no bills.'
        : 'No bill of this patient can be refunded — switch to All bills to see the rest.';
      $('#rn-nobills').text(msg).removeAttr('hidden');
      $('#rn-step2').attr('hidden', true);
      $('#rn-bar').attr('hidden', true);
      return;
    }

    $('#rn-tbl').removeAttr('hidden');
    $('#rn-nobills').attr('hidden', true);

    var html = '';
    $.each(filtered, function (i, b) {
      var bIdStr = String(b.id);
      var canRefund = (parseFloat(b.refundable) || 0) > 0 && !b.is_cancelled;
      var isPre = preIds.indexOf(bIdStr) > -1 || preIds.indexOf(String(b.bill_no)) > -1;
      var isPicked = picked.indexOf(b.id) > -1 || picked.indexOf(bIdStr) > -1 || isPre;
      var chk = canRefund && isPicked;

      var statusClass = 'pill-due';
      if (b.is_cancelled) {
        statusClass = 'pill-err';
      } else if (b.status === 'Paid') {
        statusClass = 'pill-ok';
      } else if (b.status === 'Fully refunded') {
        statusClass = 'pill-muted';
      } else if (b.status === 'Refund in process') {
        statusClass = 'pill-info';
      } else if (b.status === 'Part refunded' || b.status === 'Part-paid') {
        statusClass = 'pill-warn';
      }

      var refundedText = (parseFloat(b.refunded) || 0) > 0 ? ('−' + inr(b.refunded)) : '—';
      var refundableText = (parseFloat(b.refundable) || 0) > 0 ? ('<b>' + inr(b.refundable) + '</b>') : '—';

      html += '<tr class="' + (b.is_cancelled ? 'is-cx ' : '') + '" data-on="' + (chk ? 'true' : 'false') + '"' + (!canRefund ? ' aria-disabled="true"' : '') + '>' +
        '<td class="pick">' +
        '<input type="checkbox" data-rn-bill="' + b.id + '" data-max="' + b.refundable + '" ' +
        (chk ? 'checked ' : '') + (!canRefund ? 'disabled ' : '') + 'aria-label="Select bill ' + esc(b.bill_no) + '">' +
        '</td>' +
        '<td><b>Bill ' + esc(b.bill_no) + '</b></td>' +
        '<td>' + esc(b.date) + '</td>' +
        '<td>' + esc(b.doctor) + '</td>' +
        '<td class="num">' + inr(b.billed) + '</td>' +
        '<td class="num">' + inr(b.paid) + '</td>' +
        '<td class="num">' + refundedText + '</td>' +
        '<td class="num rn-left">' + refundableText + '</td>' +
        '<td><span class="pill ' + statusClass + '">' + esc(b.status) + '</span></td>' +
        '</tr>';
    });

    $tbody.html(html);
    onRefundBillChange();
  }

  function onRefundBillChange() {
    var selected = [];
    var maxRefundable = 0;

    $('#rn-rows input[data-rn-bill]:checked').each(function () {
      var billId = $(this).data('rn-bill');
      var max = parseFloat($(this).data('max')) || 0;
      selected.push(billId);
      maxRefundable += max;
      $(this).closest('tr').attr('data-on', 'true');
    });

    $('#rn-rows input[data-rn-bill]:not(:checked)').each(function () {
      $(this).closest('tr').attr('data-on', 'false');
    });

    state.refunds.selectedBills = selected;

    if (selected.length > 0) {
      var wasHidden = $('#rn-step2').is(':hidden');
      $('#rn-step2').removeAttr('hidden');
      $('#rn-bar').removeAttr('hidden');

      var currentAmt = parseFloat($('#rn-amt').val()) || 0;
      if (currentAmt <= 0 || currentAmt > maxRefundable) {
        $('#rn-amt').val(maxRefundable.toFixed(2));
      }
      paintRefundBar();

      if (wasHidden) {
        var el = document.getElementById('rn-step2');
        if (el && el.scrollIntoView) {
          el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
      }
    } else {
      $('#rn-step2').attr('hidden', true);
      $('#rn-bar').attr('hidden', true);
    }
  }

  function paintRefundBar() {
    var amt = parseFloat($('#rn-amt').val()) || 0;
    var count = state.refunds.selectedBills.length;
    var mode = state.refunds.mode || 'Cash';
    var userName = (window.INITIAL_DATA && window.INITIAL_DATA.user_name) ? window.INITIAL_DATA.user_name : 'Staff';

    var lineText = count === 1 ? ('Bill ' + state.refunds.selectedBills[0]) : ('Bills ' + state.refunds.selectedBills.join(', '));
    var subText = count > 0
      ? ('Initiated by ' + userName + ' · needs admin approval')
      : 'Tick a bill above to start.';

    $('#rb-line').text(count > 0 ? lineText : 'No bill selected yet');
    $('#rb-sub').text(subText);
    $('#rb-amt').text(inr(amt));
    $('#rn-go').prop('disabled', count === 0);
  }

  function submitRefundPrompt() {
    var amt = parseFloat($('#rn-amt').val()) || 0;
    var maxRefundable = 0;

    $('#rn-rows input[data-rn-bill]:checked').each(function () {
      maxRefundable += (parseFloat($(this).data('max')) || 0);
    });

    if (state.refunds.selectedBills.length === 0) {
      snack('Please select at least one bill to refund against.');
      return;
    }

    if (amt <= 0) {
      $('#rnamt-err').text('Please enter a valid refund amount.').removeAttr('hidden');
      $('#rn-amt').focus();
      return;
    }

    if (amt > maxRefundable + 0.01) {
      $('#rnamt-err').text('Amount exceeds max refundable balance of ' + inr(maxRefundable)).removeAttr('hidden');
      $('#rn-amt').focus();
      return;
    }
    $('#rnamt-err').attr('hidden', true);

    var ptName = state.refunds.patient ? state.refunds.patient.name : 'patient';
    var billsText = state.refunds.selectedBills.map(function (n) { return 'bill ' + n; }).join(', ');

    askRq({
      kind: 'send',
      amt: amt,
      t: 'Send this refund for approval?',
      d1: inr(amt) + ' to ' + ptName + ' by ' + state.refunds.mode + ', against ' + billsText + '.',
      d2: 'It goes to the admin to approve or reject. The amount is held on the bill until then, and nothing is paid out until it is approved and issued.',
      yes: 'Send for approval'
    });
  }

  function confirmProcessRefund() {
    closeRq();

    var patientId = state.refunds.patient ? (state.refunds.patient.id || state.refunds.patient.uhid) : null;
    if (!patientId && state.refunds.bills && state.refunds.bills.length) {
      patientId = state.refunds.bills[0].patient_id;
    }

    if (!patientId) {
      snack('Patient record is required to initiate refund.');
      return;
    }

    var bills = state.refunds.selectedBills || [];
    if (!bills.length) {
      snack('Please select at least one bill to refund against.');
      return;
    }

    var amt = parseFloat($('#rn-amt').val()) || 0;
    if (amt <= 0) {
      snack('Please enter a valid refund amount.');
      return;
    }

    var reason = $('#rn-reason').val() || 'Refund';
    var userRemark = $('#rn-remark').val().trim();
    var fullRemark = userRemark ? (reason + ' - ' + userRemark) : reason;
    var backendMode = frontendToBackendMode(state.refunds.mode);

    var postData = {
      _token: $('meta[name="csrf-token"]').attr('content'),
      uhid: patientId,
      bills: bills,
      refund_amount: amt,
      input_element: backendMode,
      refund_remark: fullRemark,
      transaction_id: $('#rn-txn').val().trim()
    };

    var $btn = $('#rn-go');
    $btn.prop('disabled', true);
    snack('Submitting refund for admin approval...');

    $.ajax({
      url: window.ROUTES.saveInitiateRefund + patientId,
      type: 'POST',
      data: postData,
      success: function (res) {
        $btn.prop('disabled', false);
        snack('Refund R' + (res.refund_id || '') + ' submitted to admin for approval.');

        var rfNum = res.refund_id || Date.now().toString().slice(-4);
        var targetBillId = res.bill_id || (bills.length ? bills[0] : null);
        var rfObj = {
          id: res.refund_id || rfNum,
          refund_no: rfNum,
          bill_id: targetBillId,
          amount: amt,
          mode: state.refunds.mode,
          txn: $('#rn-txn').val().trim(),
          date: 'Just now',
          status: res.refund_status || 'pending',
          status_text: 'Pending',
          reason: fullRemark,
          patient_name: state.refunds.patient ? state.refunds.patient.name : '',
          patient_uhid: String(patientId),
          bills: bills,
          initiated_at: 'Just now',
          initiated_by: (window.INITIAL_DATA && window.INITIAL_DATA.user_name) ? window.INITIAL_DATA.user_name : 'Front Desk'
        };

        showRefundDone(rfObj);

        if (typeof fetchRefunds === 'function') {
          fetchRefunds(1);
        }
      },
      error: function (xhr) {
        $btn.prop('disabled', false);
        var msg = 'Failed to initiate refund.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        } else if (xhr.responseJSON && xhr.responseJSON.errors) {
          var firstErr = Object.values(xhr.responseJSON.errors)[0];
          if (Array.isArray(firstErr)) msg = firstErr[0];
        }
        snack(msg);
      }
    });
  }

  function showRefundDone(refund, quiet) {
    $('#rn-find').hide();
    $('#rn-body').attr('hidden', true);
    $('#rn-bar').attr('hidden', true);
    $('#rn-step2').attr('hidden', true);
    $('#rn-empty').attr('hidden', true);
    $('#rn-done').removeAttr('hidden');

    var targetBillId = refund.bill_id || (refund.bills && refund.bills.length ? refund.bills[0] : (state.refunds.selectedBills && state.refunds.selectedBills.length ? state.refunds.selectedBills[0] : null));
    state.refunds.currentBillId = targetBillId;
    state.refunds.currentRefund = refund;
    $('#rn-print').attr('data-bill-id', targetBillId || '');

    var st = (refund.status || 'pending').toLowerCase();
    var who = refund.patient_name || 'Patient';
    var billsText = (refund.bills || [refund.bill_id || refund.bill_no]).map(function (n) { return 'bill ' + n; }).join(', ');
    var againstStr = ' · ' + refund.mode + ' · against ' + billsText;

    var headMap = {
      pending:  'Refund R' + refund.refund_no + ' sent to admin for approval',
      approved: 'Refund R' + refund.refund_no + ' approved — ready to issue',
      issued:   inr(refund.amount) + ' refunded to ' + who,
      rejected: 'Refund R' + refund.refund_no + ' rejected'
    };
    var subMap = {
      pending:  inr(refund.amount) + ' to ' + who + againstStr + '. The amount is held on the bill until the admin approves or rejects it.',
      approved: 'Approved by ' + (refund.approved_by || 'Hospital Admin') + '. Hand ' + inr(refund.amount) + ' to ' + who + ' and issue it here.',
      issued:   'Refund R' + refund.refund_no + againstStr + '.',
      rejected: 'Rejected by ' + (refund.rejected_by || 'Hospital Admin') + (refund.rejected_why ? (': “' + refund.rejected_why + '”') : '') + '. ' + inr(refund.amount) + ' is back on the bill as refundable.'
    };

    $('#rn-done-h').text(headMap[st] || (inr(refund.amount) + ' refund'));
    $('#rn-done-t').text(subMap[st] || ('Refund R' + refund.refund_no));

    var $okBox = $('#rn-done .vv-ok');
    $okBox.toggleClass('is-wait', isOpenRefund(st));
    $okBox.toggleClass('is-rej', st === 'rejected');

    $('#rn-acts').html(rfActs(refund, 'done'));

    var canEditRefund = canEditRefundReceipt();
    $('#rn-edit-receipt').toggle(canEditRefund && st === 'issued');
    $('#rn-print').toggle(st === 'issued');

    paintRole();

    var docHtml = renderRefundDoc(refund);
    $('#rn-done-doc').html(docHtml);
    if (!quiet) window.scrollTo(0, 0);
  }

  function renderRefundDoc(r) {
    var p = state.refunds.patient || { name: r.patient_name, uhid: r.patient_uhid, mobile: r.patient_mobile };
    var st = (r.status || 'pending').toLowerCase();
    var stObj = RF_ST_META[st] || { t: 'Completed', cls: 'pill-ok' };

    function stepBox(k, at, by, cls, fallback) {
      var content = at
        ? esc(at) + '<span>' + esc(by || '') + '</span>'
        : (fallback || '—');
      return '<div class="' + cls + '"><dt>' + k + '</dt><dd>' + content + '</dd></div>';
    }

    var trailHtml = '<dl class="rf-trail">' +
      stepBox('Initiated', r.initiated_at || r.date, r.initiated_by, 't-done') +
      (st === 'rejected'
        ? stepBox('Rejected', r.rejected_at || r.approved_at || r.date, r.rejected_by, 't-rej')
        : stepBox('Approval', r.approved_at, r.approved_by, r.approved_at ? 't-done' : 't-wait', 'Waiting for admin<span>Hospital Admin</span>')) +
      stepBox('Issued to patient', r.issued_at, r.issued_by, r.issued_at ? 't-done' : (st === 'approved' ? 't-wait' : ''),
        st === 'rejected' ? 'Not issued<span>Rejected</span>' : (st === 'approved' ? 'To issue<span>Front desk</span>' : '—<span>After approval</span>')) +
      '</dl>';

    var billsList = r.bills && r.bills.length ? r.bills : [r.bill_id || r.bill_no];
    var allocs = r.allocations || [];
    var billsHtml = '';

    if (allocs.length) {
      billsHtml = allocs.map(function (al) {
        return '<tr><td>Bill ' + esc(al.bill_no) + '</td><td>' + esc(al.date) + '</td><td class="num">—</td><td class="num" style="color:var(--md-sys-color-error);font-weight:600">−' + inr(al.amount) + '</td></tr>';
      }).join('');
    } else {
      billsHtml = billsList.map(function (n) {
        var b = (state.refunds.bills || []).find(function (item) { return String(item.id) === String(n); });
        return '<tr>' +
          '<td><b>Bill ' + esc(n) + '</b></td>' +
          '<td>' + (b ? esc(b.date) : '—') + '</td>' +
          '<td class="num">' + (b ? inr(b.paid) : '—') + '</td>' +
          '<td class="num" style="color:var(--md-sys-color-error); font-weight:600">−' + inr(r.amount) + '</td>' +
          '</tr>';
      }).join('');
    }

    return '<section class="panel sec sec-b rf-doc" aria-label="Refund ' + esc(r.refund_no) + '">' +
      '<div class="sec-head">' +
      '<h2 class="sec-title">Refund R' + esc(r.refund_no) + ' <span class="pill ' + stObj.cls + '">' + stObj.t + '</span></h2>' +
      '<span class="sec-actions">' + esc(r.issued_at || r.initiated_at || r.date) + '</span>' +
      '</div>' +
      '<div class="sec-body">' +
      '<div class="vv-who">' +
      '<h2>' + esc(r.patient_name || p.name) + '</h2>' +
      '<p class="vv-meta">' +
      'UHID ' + esc(r.patient_uhid || p.uhid) +
      (p.gender ? ' · ' + esc(p.gender) : '') +
      (p.mobile ? ' · +91 ' + esc(p.mobile) : '') +
      '</p>' +
      '</div>' +
      trailHtml +
      '<table class="tbl cx-tbl">' +
      '<thead>' +
      '<tr>' +
      '<th>Against</th>' +
      '<th>Date</th>' +
      '<th class="num">Paid</th>' +
      '<th class="num">Returned</th>' +
      '</tr>' +
      '</thead>' +
      '<tbody>' + billsHtml + '</tbody>' +
      '</table>' +
      '<div class="totals">' +
      '<div class="tot tot-big"><span>' + (st === 'issued' ? 'Refunded' : 'Requested') + '</span><span class="t-v" style="color:var(--md-sys-color-error)">−' + inr(r.amount) + '</span></div>' +
      '<div class="tot"><span>Mode</span><span class="t-v">' + esc(r.mode) + (r.txn || r.transaction_id ? (' · ' + esc(r.txn || r.transaction_id)) : '') + '</span></div>' +
      '<div class="tot"><span>Reason</span><span class="t-v">' + esc(r.reason) + '</span></div>' +
      (r.remarks ? ('<div class="tot"><span>Remarks</span><span class="t-v">' + esc(r.remarks) + '</span></div>') : '') +
      (r.rejected_why ? ('<div class="tot"><span>Why rejected</span><span class="t-v" style="color:var(--md-sys-color-error)">' + esc(r.rejected_why) + '</span></div>') : '') +
      '</div>' +
      '</div>' +
      '</section>';
  }

  // ══════════════════════════════════════════════════════════════════════
  // ══════════════════ BILL EDIT & CANCEL FLOW ═══════════════════════════
  // ══════════════════════════════════════════════════════════════════════

  function openEditPick(billId, targetMode) {
    $('#billing-patient, #billing-visit-summary').attr('hidden', true);
    if (!canEditBill() && !canCancelBill()) {
      snack('You do not have permission to edit or cancel bills.');
      return;
    }

    $.ajax({
      url: window.ROUTES.billDetails + billId,
      type: 'GET',
      success: function (bill) {
        var isCancelled = parseInt(bill.is_cancelled) === 1;
        var isRefund = parseInt(bill.is_refund_bill) === 1 || parseInt(bill.is_refunded) === 1 || parseFloat(bill.refunded || 0) > 0 || parseInt(bill.has_refunds) === 1;

        if (isCancelled) {
          snack('Cancelled bills cannot be edited or modified.');
          return;
        }

        if (isRefund && targetMode !== 'cancel') {
          snack('Bills with refunds cannot be edited.');
          return;
        }

        var bNo = bill.bill_number || bill.no || bill.id;
        state.editing.active = true;
        state.editing.billId = bill.id;
        state.editing.billNo = bNo;
        state.editing.billData = bill;

        // Switch to 'new' tab immediately so editing panels are visible
        switchTab('new');

        // Hide detail document view if open in All bills
        $('#all-detail').attr('hidden', true);
        $('#all-list').removeAttr('hidden');

        $('#ob-empty').attr('hidden', true);
        $('#ob-form').attr('hidden', true);
        $('#ob-paid').attr('hidden', true);
        $('#edit-done').attr('hidden', true);

        // If targetMode is directly requested as cancel
        if (targetMode === 'cancel') {
          pickEditMode('cancel');
          return;
        }

        $('#edit-pick').removeAttr('hidden');

        var canEditThis = canEditBill() && !isCancelled && !isRefund;
        var canCancelThis = canCancelBill() && !isCancelled && !isRefund;

        $('#ep-edit').toggle(canEditThis);
        $('#ep-cancel').toggle(canCancelThis);

        var p = bill.patient || {};
        $('#ep-name').text(p.name || 'Patient');
        $('#ep-meta').text('UHID ' + (p.uhid || p.id) + (p.gender ? ' · ' + p.gender : '') + (p.mobile ? ' · ' + p.mobile : ''));

        var factsHtml = '<div class="f-item"><dt>Bill no.</dt><dd>' + esc(bNo) + '</dd></div>' +
          '<div class="f-item"><dt>Date & time</dt><dd>' + esc(bill.date || '') + '</dd></div>' +
          '<div class="f-item"><dt>Doctor</dt><dd>' + esc(docName(bill.doctor || bill.doctor_name)) + '</dd></div>' +
          '<div class="f-item"><dt>Billed</dt><dd><b>' + inr(bill.payable || bill.total) + '</b></dd></div>' +
          '<div class="f-item"><dt>Paid</dt><dd><b>' + inr(bill.paid || 0) + '</b></dd></div>' +
          '<div class="f-item"><dt>Due</dt><dd>' + inr(bill.due || 0) + '</dd></div>';

        $('#ep-facts').html(factsHtml);

        if (bill.is_settled === 1) {
          $('#ep-settled-warn').removeAttr('hidden');
          $('#ep-edit').addClass('is-disabled').attr('title', 'This bill is already settled. Click "Unsettle bill" to edit.');
        } else {
          $('#ep-settled-warn').attr('hidden', true);
          $('#ep-edit').removeClass('is-disabled').removeAttr('title');
        }
      },
      error: function () {
        snack('Could not load bill details for editing.');
      }
    });
  }

  function startBillEdit(bill) {
    if (bill && parseInt(bill.is_cancelled) === 1) {
      snack('Cancelled bills cannot be edited.');
      return;
    }
    if (bill && (parseInt(bill.is_refund_bill) === 1 || parseInt(bill.is_refunded) === 1 || parseFloat(bill.refunded || 0) > 0 || parseInt(bill.has_refunds) === 1)) {
      snack('Bills with refunds cannot be edited.');
      return;
    }
    if (bill && bill.is_settled === 1) {
      snack('This bill is already settled and cannot be edited. Please unsettle the bill first.');
      return;
    }

    switchTab('new');
    $('#billing-patient, #billing-visit-summary').attr('hidden', true);
    state.payAuto = false;
    state.editing.active = true;
    state.editing.billId = bill.id;
    state.editing.billNo = bill.bill_number || bill.no || bill.id;
    state.editing.billData = bill;
    state.editing.mode = 'edit';

    // 1. Patient
    state.patient = bill.patient || {};
    $('#pt-name').text(state.patient.name || 'Patient').attr('data-patient-id', state.patient.id || '');
    $('#pt-meta').text('UHID ' + (state.patient.uhid || state.patient.id) + (state.patient.gender ? ' · ' + state.patient.gender : '') + (state.patient.mobile ? ' · ' + state.patient.mobile : ''));
    $('#finder').attr('hidden', true);
    $('#ob-empty').attr('hidden', true);
    $('#edit-pick').attr('hidden', true);
    $('#ob-form').removeAttr('hidden');
    $('#pt-block').show();
    $('#pt-change').attr('hidden', true);

    // 2. Doctor & Spec
    setDoctorAndSpec(bill.doctor_id, bill.doctor_name || bill.doctor, bill.doctor_spec || bill.specialization);
    state.refBy = bill.ref_by ? String(bill.ref_by) : '';
    paintBillingRefby();

    // Show doctor name in label
    var dName = getBillDoctorName(bill);
    var dSpec = bill.doctor_spec || bill.specialization || (state.doctor ? (state.doctor.spec || state.doctor.specialization) : '');
    $('#pt-doc-name').text(dName);
    if (dSpec) {
      $('#pt-doc-spec').text(' · ' + dSpec).show();
    } else {
      $('#pt-doc-spec').empty().hide();
    }
    $('#pt-doc-badge').removeAttr('hidden').show();

    // 3. Line items
    state.services = [];
    state.consultation = null;
    state.consultPrimaryId = null;
    state.addConsultation = false;
    $('#consult').prop('checked', false);

    var consultSvcId = window.INITIAL_DATA ? window.INITIAL_DATA.consult_service_id : null;
    var items = bill.items || bill.lines || [];

    $.each(items, function (i, item) {
      var isConsult = (item.flag === 'consultation_charge' || item.flag === 'doctor_consultation' || (consultSvcId && String(item.service_id) === String(consultSvcId)));
      if (isConsult) {
        state.addConsultation = true;
        state.consultPrimaryId = item.primary_id || item.id;
        state.consultation = {
          id: item.service_id,
          name: item.name || item.service_name,
          amt: parseFloat(item.rate || item.charges) || 0
        };
        $('#consult').prop('checked', true);
        state.services.push({
          primary_id: item.primary_id || item.id,
          id: 'consult',
          service_id: item.service_id,
          name: item.name || item.service_name,
          code: item.sub || '',
          cat: '',
          rate: parseFloat(item.rate || item.charges) || 0,
          qty: parseInt(item.qty, 10) || 1,
          disc: parseFloat(item.disc || item.discount) || 0,
          discPct: false,
          is_editable: !!item.is_editable,
          isConsult: true
        });
      } else {
        state.services.push({
          primary_id: item.primary_id || item.id,
          id: 'svc_' + (item.service_id || item.id),
          service_id: item.service_id || item.id,
          name: item.name || item.service_name,
          code: item.sub || '',
          cat: '',
          rate: parseFloat(item.rate || item.charges) || 0,
          qty: parseInt(item.qty, 10) || 1,
          disc: parseFloat(item.disc || item.discount) || 0,
          discPct: false,
          is_editable: !!item.is_editable,
          isConsult: false
        });
      }
    });

    // 4. Whole bill discount
    state.billDiscount = parseFloat(bill.discount) || 0;
    state.billDiscountPct = false;
    $('#bdisc').val(state.billDiscount > 0 ? state.billDiscount : '');

    if (bill.discount_auth_by) {
      $('#discount_auth_by').val(String(bill.discount_auth_by));
    } else {
      $('#discount_auth_by').val('');
    }

    // 5. Payments
    if (bill.payments && bill.payments.length) {
      state.payments = bill.payments.map(function (p) {
        return {
          mode: backendToFrontendMode(p.payment_mode || p.mode),
          amt: (p.amount !== undefined && p.amount !== null) ? String(p.amount) : (p.amt ? String(p.amt) : ''),
          txn: p.txn || p.transaction_id || ''
        };
      });
    } else {
      state.payments = [{ mode: 'Cash', amt: '', txn: '' }];
    }
    renderPaymentRows();

    // 6. Remark
    $('#remark').val(bill.remarks || '');

    // 7. UI adjustments
    $('#edit-note').removeClass('is-cancel').removeAttr('hidden');
    $('#en-verb').text('Editing');
    $('#en-no').text(state.editing.billNo);
    $('#en-sub').text('Changes to lines or discount will update this bill.');
    $('#edit-back').removeAttr('hidden');
    $('#sec-visit, .sec-v, #sec-doc').hide();
    $('#sec-bill').show();
    $('#sec-cancel').attr('hidden', true);

    $('#save-only').attr('hidden', true);
    $('#ob-bar').removeAttr('hidden');
    $('#collect').html('Save changes<span class="kbd">Alt S</span>');

    renderBillTable();
    calculateTotals();
  }

  function pickEditMode(mode) {
    var bill = state.editing.billData;
    if (!bill) return;

    if (mode === 'edit') {
      if (!canEditBill()) {
        snack('You do not have permission to edit bills.');
        return;
      }
      if (parseInt(bill.is_cancelled) === 1) {
        snack('Cancelled bills cannot be edited.');
        return;
      }
      if (parseInt(bill.is_refund_bill) === 1 || parseInt(bill.is_refunded) === 1 || parseFloat(bill.refunded || 0) > 0 || parseInt(bill.has_refunds) === 1) {
        snack('Bills with refunds cannot be edited.');
        return;
      }
      if (bill.is_settled === 1) {
        snack('This bill is already settled and cannot be edited. Please click "Unsettle bill" first.');
        $('#ep-settled-warn').removeAttr('hidden');
        return;
      }
      startBillEdit(bill);
    } else if (mode === 'cancel') {
      if (!canCancelBill()) {
        snack('You do not have permission to cancel bills.');
        return;
      }
      state.editing.mode = 'cancel';
      $('#edit-pick').attr('hidden', true);
      $('#ob-form').removeAttr('hidden');
      $('#edit-back').removeAttr('hidden');
      $('#edit-note').addClass('is-cancel').removeAttr('hidden');
      $('#en-verb').text('Cancelling');
      $('#en-no').text(state.editing.billNo);
      $('#en-sub').text('Strike out items or cancel the whole bill.');

      // 1. Patient
      state.patient = bill.patient || {};
      $('#pt-name').text(state.patient.name || 'Patient').attr('data-patient-id', state.patient.id || '');
      $('#pt-meta').text('UHID ' + (state.patient.uhid || state.patient.id) + (state.patient.gender ? ' · ' + state.patient.gender : '') + (state.patient.mobile ? ' · ' + state.patient.mobile : ''));
      $('#finder').attr('hidden', true);
      $('#ob-empty').attr('hidden', true);
      $('#pt-block').show();
      $('#pt-change').attr('hidden', true);

      // 2. Doctor & Spec
      setDoctorAndSpec(bill.doctor_id, bill.doctor_name || bill.doctor, bill.doctor_spec || bill.specialization);

      // Show doctor name in label
      var dName = getBillDoctorName(bill);
      var dSpec = bill.doctor_spec || bill.specialization || (state.doctor ? (state.doctor.spec || state.doctor.specialization) : '');
      $('#pt-doc-name').text(dName);
      if (dSpec) {
        $('#pt-doc-spec').text(' · ' + dSpec).show();
      } else {
        $('#pt-doc-spec').empty().hide();
      }
      $('#pt-doc-badge').removeAttr('hidden').show();

      // 3. UI Panels
      $('#sec-visit, .sec-v, #sec-doc').hide();
      $('#sec-bill').hide();
      $('#sec-cancel').removeAttr('hidden');
      $('#cx-refund').toggle(canRefund());
      $('#cx-only').toggle(canCancelBill());
      $('#ob-bar').attr('hidden', true);

      renderCancelLines(bill);
    }
  }

  function renderCancelLines(bill) {
    var items = bill.items || bill.lines || [];
    var $tbody = $('#cx-rows');
    $tbody.empty();

    var html = '';
    $.each(items, function (i, item) {
      var itemId = item.primary_id || item.id;
      var rate = parseFloat(item.rate || item.charges || 0);
      var qty = parseFloat(item.qty || 1);
      var disc = parseFloat(item.disc || item.discount || 0);
      var amt = parseFloat(item.amount || (rate * qty - disc) || 0);

      html += '<tr data-struck="true">' +
        '<td>' + (i + 1) + '</td>' +
        '<td><span class="cx-what"><b>' + esc(item.name || item.service_name) + '</b></span></td>' +
        '<td class="num">' + inr(rate) + '</td>' +
        '<td class="num">' + qty + '</td>' +
        '<td class="num">' + inr(disc) + '</td>' +
        '<td class="num"><b>' + inr(amt) + '</b></td>' +
        '<td class="act"><label class="cx-pick"><input type="checkbox" data-cx-item="' + itemId + '" data-rate="' + rate + '" data-qty="' + qty + '" data-disc="' + disc + '" checked aria-label="Cancel item ' + (i + 1) + '"></label></td>' +
        '</tr>';
    });
    $tbody.html(html);

    var paid = parseFloat(bill.paid || 0);
    if (paid > 0) {
      $('#cx-warn-t').text(inr(paid) + ' has been collected against this bill.');
      $('#cx-warn-d').text('Initiating a refund will cancel the selected items and create a refund record.');
      $('#cx-refund').show();
      $('#cx-calc').show();
    } else {
      $('#cx-warn-t').text('No payment collected against this bill.');
      $('#cx-warn-d').text('This bill can be cancelled directly without issuing a refund.');
      $('#cx-calc').hide();
    }

    $('#cx-bill').prop('checked', true);
    $('#cx-remark').val('');
    $('#cx-file').val('');
    $('#cxremark-err').attr('hidden', true);
    $('input[name="cx_refund_mode"][value="cash"]').prop('checked', true);

    calculateCancelTotals();
  }

  function calculateCancelTotals() {
    var bill = state.editing.billData;
    if (!bill) return { grossRefund: 0, discountPercent: 0, netRefund: 0, selectedCount: 0 };

    var items = bill.items || bill.lines || [];

    // Step 1: Calculate subtotal of all items before bill-level discount
    var subtotal = 0;
    $.each(items, function (_, item) {
      var r = parseFloat(item.charges || item.rate || 0);
      var q = parseFloat(item.qty || 1);
      var d = parseFloat(item.discount || item.disc || 0);
      subtotal += (r * q) - d;
    });

    // Step 2: Calculate bill discount percentage from subtotal
    var billDiscount = parseFloat(bill.bill_discount || bill.discount || 0);
    var discountPercent = subtotal > 0 ? (billDiscount / subtotal) * 100 : 0;

    // Step 3: Calculate gross & net refund amount for checked items
    var selectedGross = 0;
    var selectedNet = 0;
    var selectedCount = 0;

    $('#cx-rows tr').each(function () {
      var $tr = $(this);
      var $chk = $tr.find('input[data-cx-item]');
      if ($chk.is(':checked')) {
        selectedCount++;
        $tr.attr('data-struck', 'true');
        var rate = parseFloat($chk.data('rate') || 0);
        var qty = parseFloat($chk.data('qty') || 1);
        var disc = parseFloat($chk.data('disc') || 0);

        var lineTotal = (rate * qty) - disc;
        var itemDiscount = (lineTotal * discountPercent) / 100;
        var lineNet = lineTotal - itemDiscount;

        selectedGross += lineTotal;
        selectedNet += lineNet;
      } else {
        $tr.removeAttr('data-struck');
      }
    });

    var allChecked = items.length > 0 && selectedCount === items.length;
    $('#cx-bill').prop('checked', allChecked);

    var totalItemDiscount = selectedGross - selectedNet;
    $('#cx-subtotal').text(inr(selectedGross));
    $('#cx-disc-pct').text(discountPercent.toFixed(2));
    $('#cx-disc-amt').text('-' + inr(totalItemDiscount));
    $('#cx-net-amt').text(inr(selectedNet));

    var paid = parseFloat(bill.paid || 0);
    if (selectedNet > paid) {
      $('#cx-note').text('Note: Net refund (' + inr(selectedNet) + ') exceeds amount paid (' + inr(paid) + ').');
    } else {
      $('#cx-note').text('');
    }

    return {
      grossRefund: selectedGross,
      discountPercent: discountPercent,
      netRefund: selectedNet,
      selectedCount: selectedCount
    };
  }

  function applyBillCancel(type) {
    if (!canCancelBill()) {
      snack('You do not have permission to cancel bills.');
      return;
    }
    if (type === 'refund_and_cancel' && !canRefund()) {
      snack('You do not have permission to issue refunds.');
      return;
    }

    var bill = state.editing.billData;
    if (!bill) return;

    var remark = $('#cx-remark').val().trim();
    if (!remark) {
      $('#cxremark-err').text('Please provide a cancellation remark.').removeAttr('hidden');
      $('#cx-remark').focus();
      return;
    }
    $('#cxremark-err').attr('hidden', true);

    var itemIds = [];
    $('#cx-rows input[data-cx-item]:checked').each(function () {
      itemIds.push($(this).data('cx-item'));
    });

    if (itemIds.length === 0) {
      snack('Please select at least one item to cancel.');
      return;
    }

    var paid = parseFloat(bill.paid || 0);

    if (type === 'cancel_bill_only') {
      if (paid > 0) {
        snack('This bill has already been paid and cannot be cancelled without refund. Please choose "Initiate Refund & Cancel Bill".');
        return;
      }
    }

    var calc = calculateCancelTotals();

    var formData = new FormData();
    formData.append('bill_id', bill.id);
    formData.append('remark', remark);
    formData.append('type', type);

    $.each(itemIds, function (_, id) {
      formData.append('cancel_item[]', id);
    });

    var cancelAll = $('#cx-bill').is(':checked') || (itemIds.length === (bill.items || []).length);
    if (cancelAll) {
      formData.append('cancel_all', '1');
    }

    if (type === 'refund_and_cancel') {
      formData.append('refund_amount', calc.grossRefund.toFixed(2));
      formData.append('discount_amount', calc.discountPercent.toFixed(2));
      formData.append('net_amount', calc.netRefund.toFixed(2));

      var mode = $('input[name="cx_refund_mode"]:checked').val() || 'cash';
      formData.append('input_element', mode);
    }

    var fileInput = document.getElementById('cx-file');
    if (fileInput && fileInput.files && fileInput.files[0]) {
      formData.append('attachment', fileInput.files[0]);
    }

    var csrfToken = $('meta[name="csrf-token"]').attr('content') || (window.INITIAL_DATA ? window.INITIAL_DATA.csrfToken : '');
    formData.append('_token', csrfToken);

    var $btn = (type === 'refund_and_cancel') ? $('#cx-refund') : $('#cx-only');
    var origHtml = $btn.html();
    $btn.prop('disabled', true).text('Processing...');

    snack('Processing bill cancellation...');
    $.ajax({
      url: window.ROUTES.saveCancelBillItems || '/save-cancel-bill-items',
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function (res) {
        if (res.status === 'success') {
          snack(res.message || 'Bill item cancelled successfully!');
          leaveEditMode();
          switchTab('all');
          fetchBills();
        } else {
          snack(res.message || 'Failed to cancel bill.');
        }
      },
      error: function (xhr) {
        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to cancel bill.';
        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
          var firstErr = Object.values(xhr.responseJSON.errors)[0];
          if (Array.isArray(firstErr)) firstErr = firstErr[0];
          if (firstErr) msg = firstErr;
        }
        snack(msg);
      },
      complete: function () {
        $btn.prop('disabled', false).html(origHtml);
      }
    });
  }

  function leaveEditMode() {
    state.editing.active = false;
    state.editing.billId = null;
    state.editing.billData = null;
    state.editing.mode = null;

    $('#edit-pick').attr('hidden', true);
    $('#edit-back').attr('hidden', true);
    $('#edit-note').attr('hidden', true).removeClass('is-cancel');
    $('#sec-cancel').attr('hidden', true);
    $('#pt-doc-badge').attr('hidden', true).hide();
    $('#pt-doc-name').empty();
    $('#pt-doc-spec').empty().hide();
    $('#sec-visit, .sec-v, #sec-doc').show();
    $('#sec-bill').show();
    $('#pt-change').removeAttr('hidden');
    $('#save-only').removeAttr('hidden');
    $('#collect').html('Collect &#8377;0.00<span class="kbd">Alt S</span>');

    resetBillForm();
    populateDoctors();
  }

  // ══════════════════════════════════════════════════════════════════════
  // ════════════════════ NEW EVENT BINDINGS ══════════════════════════════
  // ══════════════════════════════════════════════════════════════════════

  $(function () {
    // Refunds Filter Form
    $('#rf-form').on('submit', function (e) {
      e.preventDefault();
      fetchRefunds(1);
    });

    $('#rf-clear').on('click', function () {
      $('#rf-no').val('');
      $('#rf-pt').val('');
      $('#rf-from').val('');
      $('#rf-to').val('');
      fetchRefunds(1);
    });

    // Open New Refund
    $('#rf-new').on('click', function () {
      openNewRefund();
    });

    $('#rn-back').on('click', function () {
      backToRefundsList();
    });

    $('#rn-change').on('click', function () {
      state.refunds.patient = null;
      state.refunds.bills = [];
      state.refunds.selectedBills = [];
      state.refunds.filter = 'all';

      $('#rn-body').attr('hidden', true);
      $('#rn-step2').attr('hidden', true);
      $('#rn-bar').attr('hidden', true);
      $('#rn-empty').removeAttr('hidden');
      $('#rn-adv').attr('hidden', true);
      $('#rn-adv-btn').attr('aria-expanded', 'false');
      $('#rn-adv-count').text('').attr('hidden', true);
      $('#rn-a-name, #rn-a-phone, #rn-a-gender').val('');
      $('#rn-find').show();
      $('#rn-q').val('').focus();
      window.scrollTo(0, 0);
    });

    $(document).on('click', '#rn-filter button[data-rn-f]', function () {
      var f = $(this).data('rn-f');
      state.refunds.filter = f;
      paintRnFilter();
      renderRefundBillsTable(state.refunds.bills);
    });

    // Patient search in refund flow
    initPatientSearch({
      container: '#rn-finder',
      form: '#rn-find',
      input: '#rn-q',
      advBtn: '#rn-adv-btn',
      advPanel: '#rn-adv',
      advName: '#rn-a-name',
      advPhone: '#rn-a-phone',
      advGender: '#rn-a-gender',
      advCount: '#rn-adv-count',
      advClear: '#rn-adv-clear',
      results: '#rn-results',
      onSelect: function (patient) {
        selectRefundPatient(patient.id || patient.patient_id || patient.uhid);
      }
    });

    // Refund bill row selection
    $(document).on('change', '#rn-rows input[data-rn-bill]', function () {
      onRefundBillChange();
    });

    $('#rn-none-btn').on('click', function () {
      $('#rn-rows input[data-rn-bill]').prop('checked', false);
      onRefundBillChange();
    });

    $('#rn-amt').on('input', function () {
      paintRefundBar();
    });

    // Refund Mode selector
    $('#rn-mode button').on('click', function () {
      $('#rn-mode button').attr('aria-pressed', 'false');
      $(this).attr('aria-pressed', 'true');
      var mode = $(this).data('mode');
      state.refunds.mode = mode;
      $('#rn-txn-h').text(TXN_HINT[mode] || '');
      $('#rn-txn').prop('disabled', mode === 'Cash');
      if (mode === 'Cash') $('#rn-txn').val('');
      paintRefundBar();
    });

    // Issue Refund action
    $('#rn-go').on('click', function () {
      submitRefundPrompt();
    });

    // Refunds Group Filter Buttons (All, In process, Completed, Rejected)
    $(document).on('click', '#rf-st button[data-g]', function () {
      var g = $(this).data('g');
      state.refunds.filterGroup = g;
      fetchRefunds(1);
    });

    // Lifecycle Action Buttons in Table & Done screen (Approve, Reject, Issue, View)
    $(document).on('click', '#rf-rows, #rn-acts', onRfAct);

    // Rejection Reason and Multi-Bill Allocations Floating Popover
    $(document).on('click', '#rf-rows [data-why], #rf-rows [data-bills]', function (e) {
      e.stopPropagation();
      var b = this;
      if (popBtn === b) { closeWhy(); return; }
      closeWhy();

      var isWhy = $(b).is('[data-why]');
      var no = isWhy ? $(b).data('why') : $(b).data('bills');
      var r = getRefundById(no);
      if (!r) return;

      var $pop = $('#rf-pop');
      $pop.toggleClass('is-bills', !isWhy);

      if (isWhy) {
        $('#rf-pop-h').text('Rejected by ' + (r.rejected_by || 'Hospital Admin'));
        $('#rf-pop-t').text(r.rejected_why || r.remarks || 'Reason not specified');
        $('#rf-pop-l').empty();
      } else {
        var billsList = r.bills || [r.bill_id || r.bill_no];
        $('#rf-pop-h').text('R' + r.refund_no + ' · against ' + billsList.length + ' bills');
        $('#rf-pop-t').text('');

        var allocHtml = '';
        if (r.allocations && r.allocations.length) {
          allocHtml = r.allocations.map(function (al) {
            return '<li><span>Bill ' + esc(al.bill_no) + (al.date ? (' · ' + esc(al.date)) : '') + '</span><b>−' + inr(al.amount) + '</b></li>';
          }).join('');
        } else {
          allocHtml = billsList.map(function (n) {
            return '<li><span>Bill ' + esc(n) + '</span><b>−' + inr(r.amount) + '</b></li>';
          }).join('');
        }
        allocHtml += '<li class="tot"><span>Total</span><b>−' + inr(r.amount) + '</b></li>';
        $('#rf-pop-l').html(allocHtml);
      }

      $pop.removeAttr('hidden');
      popBtn = b;
      $(b).attr('aria-expanded', 'true');

      var rc = b.getBoundingClientRect();
      var w = $pop.outerWidth(), h = $pop.outerHeight();
      var left = Math.min(Math.max(8, rc.left - 12), window.innerWidth - w - 8);
      var top = rc.bottom + 6;
      if (top + h > window.innerHeight - 8) {
        top = Math.max(8, rc.top - h - 6);
      }
      $pop.css({ left: left + 'px', top: top + 'px' });
    });

    $(document).on('click', function (e) {
      if (!$(e.target).closest('#rf-pop, [data-why], [data-bills]').length) {
        closeWhy();
      }
    });

    $(document).on('keydown', function (e) {
      if (e.key === 'Escape' && !$('#rf-pop').is(':hidden')) {
        closeWhy(true);
      }
    });

    $(window).on('scroll resize', function () {
      closeWhy();
    });

    // Approval Trail Modal (#rq-ask) handlers
    $('#rq-x, #rq-no').on('click', function () {
      closeRq();
    });

    $('#rq-why').on('input', function () {
      $('#rqwhy-err').text('').prop('hidden', true);
    });

    $('#rq-yes').on('click', function () {
      if (!rq) return;
      var o = rq;
      if (o.kind === 'send') {
        confirmProcessRefund();
        return;
      }

      var r = getRefundById(o.no) || state.refunds.currentRefund;
      if (!r) return;

      if (o.kind === 'reject') {
        var why = $('#rq-why').val().trim();
        if (!why) {
          $('#rqwhy-err').text('Say why, so the desk can tell the patient.').removeAttr('hidden');
          $('#rq-why').focus();
          return;
        }
        closeRq();

        $.ajax({
          url: window.ROUTES.rejectRefund + r.id,
          type: 'POST',
          data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            reason: why
          },
          success: function (res) {
            r.status = 'rejected';
            r.status_text = 'Rejected';
            r.rejected_why = why;
            r.rejected_by = (window.INITIAL_DATA && window.INITIAL_DATA.user_name) ? window.INITIAL_DATA.user_name : 'Hospital Admin';
            r.rejected_at = 'Just now';
            snack('Refund R' + r.refund_no + ' rejected. Held amount released back on bill.');
            if (!$('#rn-done').is(':hidden') && state.refunds.currentRefund && state.refunds.currentRefund.id === r.id) {
              showRefundDone(r, true);
            } else {
              fetchRefunds(state.refunds.page || 1);
            }
          },
          error: function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to reject refund.';
            snack(msg);
          }
        });
        return;
      }

      if (o.kind === 'approve') {
        closeRq();
        $.ajax({
          url: window.ROUTES.approveRefund + r.id,
          type: 'POST',
          data: {
            _token: $('meta[name="csrf-token"]').attr('content')
          },
          success: function (res) {
            r.status = 'approved';
            r.status_text = 'Approved';
            r.approved_by = (window.INITIAL_DATA && window.INITIAL_DATA.user_name) ? window.INITIAL_DATA.user_name : 'Hospital Admin';
            r.approved_at = 'Just now';
            snack('Refund R' + r.refund_no + ' approved. The desk can now issue ' + inr(r.amount) + '.');
            if (!$('#rn-done').is(':hidden') && state.refunds.currentRefund && state.refunds.currentRefund.id === r.id) {
              showRefundDone(r, true);
            } else {
              fetchRefunds(state.refunds.page || 1);
            }
          },
          error: function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to approve refund.';
            snack(msg);
          }
        });
        return;
      }

      if (o.kind === 'issue') {
        closeRq();
        $.ajax({
          url: window.ROUTES.issueRefund + r.id,
          type: 'POST',
          data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            transaction_id: r.transaction_id || ''
          },
          success: function (res) {
            r.status = 'issued';
            r.status_text = 'Completed';
            r.issued_by = (window.INITIAL_DATA && window.INITIAL_DATA.user_name) ? window.INITIAL_DATA.user_name : 'Front Desk';
            r.issued_at = 'Just now';
            snack('Refund R' + r.refund_no + ' of ' + inr(r.amount) + ' issued to the patient.');
            if (!$('#rn-done').is(':hidden') && state.refunds.currentRefund && state.refunds.currentRefund.id === r.id) {
              showRefundDone(r, true);
            } else {
              fetchRefunds(state.refunds.page || 1);
            }
          },
          error: function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to issue refund.';
            snack(msg);
          }
        });
        return;
      }
    });

    // Dialog handlers
    $('#ra-x, #ra-no').on('click', function () {
      $('#rf-ask').attr('hidden', true);
    });

    $('#ra-yes').on('click', function () {
      confirmProcessRefund();
    });

    // Success screen actions
    $('#rn-again').on('click', function () {
      openNewRefund();
    });

    $('#rn-tolist').on('click', function () {
      backToRefundsList();
    });

    // Print refund voucher bill
    $('#rn-print').on('click', function (e) {
      var billId = $(this).attr('data-bill-id') || state.refunds.currentBillId;
      if (!billId && state.refunds.selectedBills && state.refunds.selectedBills.length) {
        billId = state.refunds.selectedBills[0];
      }
      if (!billId) {
        snack('No bill ID found to print.');
        return;
      }

      var base = (window.ROUTES && window.ROUTES.showBill) ? window.ROUTES.showBill : '/show_bill';
      var url = base.replace(/\/+$/, '') + '/' + billId;
      if (e.ctrlKey || e.metaKey) {
        window.open(url, '_blank');
      } else {
        printUrlOnSamePage(url);
      }
    });

    // Edit refund receipt from detail voucher view
    $('#rn-edit-receipt').on('click', function () {
      if (!canEditRefundReceipt()) {
        snack('You do not have permission to edit receipts.');
        return;
      }
      var r = state.refunds.currentRefund;
      if (!r) {
        snack('No refund record selected.');
        return;
      }

      var openModal = function (paymentId, amount, mode, txn) {
        openReceiptEditModal({
          billId: r.bill_id,
          billNo: r.bill_no || r.refund_no || r.bill_id,
          paymentId: paymentId,
          amount: amount,
          mode: mode,
          txn: txn,
          patientName: r.patient_name || '',
          isOut: true
        });
      };

      if (r.payment_id) {
        openModal(r.payment_id, r.amount, r.mode, r.transaction_id || r.txn || '');
      } else if (r.bill_id) {
        $.ajax({
          url: window.ROUTES.billDetails + r.bill_id,
          type: 'GET',
          success: function (b) {
            var pays = b.payments || [];
            var p = pays.length ? pays[0] : null;
            if (!p) {
              snack('No payment receipt found to edit.');
              return;
            }
            r.payment_id = p.id;
            openModal(p.id, p.amount || r.amount, p.mode || r.mode, p.txn || '');
          },
          error: function () {
            snack('Failed to load refund receipt details.');
          }
        });
      }
    });

    // View receipt in table
    $(document).on('click', '[data-rf-receipt]', function () {
      var rId = $(this).data('rf-receipt');
      var refund = null;
      $.each(state.refunds.data, function (i, r) {
        if (String(r.id) === String(rId)) { refund = r; return false; }
      });
      if (refund) {
        $('#rf-ask').attr('hidden', true);
        showRefundDone(refund);
        $('#ref-list').attr('hidden', true);
        $('#ref-new').removeAttr('hidden');
      }
    });

    // Print refund bill from table
    $(document).on('click', '[data-rf-print]', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var billId = $(this).data('rf-print');
      if (!billId) {
        snack('No refund bill ID found to print.');
        return;
      }
      var base = (window.ROUTES && window.ROUTES.showBill) ? window.ROUTES.showBill : '/show_bill';
      var url = base.replace(/\/+$/, '') + '/' + billId;
      if (e.ctrlKey || e.metaKey) {
        window.open(url, '_blank');
      } else {
        printUrlOnSamePage(url);
      }
    });

    // Bill Edit / Cancel mode triggers
    $('#ep-back, #edit-stop, #edit-back').on('click', function () {
      leaveEditMode();
      switchTab('all');
    });

    $('#ep-edit').on('click', function () {
      pickEditMode('edit');
    });

    $('#ep-unsettle-btn').on('click', function () {
      var billId = state.editing.billId;
      var billNo = state.editing.billNo || billId;
      if (!billId) return;

      var $btn = $(this);
      $btn.prop('disabled', true).text('Unsettling...');
      $.ajax({
        url: window.ROUTES.unsettleBill,
        type: 'POST',
        data: { bill_id: billId },
        success: function (res) {
          $btn.prop('disabled', false).text('Unsettle bill');
          if (res && (res.status === 'success' || res.success)) {
            if (state.editing.billData) {
              state.editing.billData.is_settled = 0;
            }
            $('#ep-settled-warn').attr('hidden', true);
            $('#ep-edit').removeClass('is-disabled').removeAttr('title');
            snack('Bill #' + billNo + ' unsettled successfully. Opening bill editor...');
            pickEditMode('edit');
          } else {
            snack((res && res.message) ? res.message : 'Could not unsettle this bill.');
          }
        },
        error: function (xhr) {
          $btn.prop('disabled', false).text('Unsettle bill');
          var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to unsettle bill.';
          snack(msg);
        }
      });
    });

    $('#ep-cancel').on('click', function () {
      pickEditMode('cancel');
    });

    $('#cx-bill').on('change', function () {
      var chk = $(this).is(':checked');
      $('#cx-rows input[data-cx-item]').prop('checked', chk);
      calculateCancelTotals();
    });

    $(document).on('change', '#cx-rows input[data-cx-item]', function () {
      calculateCancelTotals();
    });

    $('#cx-refund').on('click', function () {
      applyBillCancel('refund_and_cancel');
    });

    $('#cx-only').on('click', function () {
      applyBillCancel('cancel_bill_only');
    });

    // Post-payment Edit / Refund buttons
    $('#paid-edit').on('click', function () {
      if (!canEditBill()) {
        snack('You do not have permission to edit bills.');
        return;
      }
      if (state.billId) {
        openEditPick(state.billId);
      }
    });

    $('#paid-refund').on('click', function () {
      if (state.billId) {
        if (canRefund()) {
          switchTab('ref');
          openNewRefund(null, [state.billId]);
        } else if (canCancelBill()) {
          openEditPick(state.billId, 'cancel');
        } else {
          snack('You do not have permission to refund or cancel this bill.');
        }
      }
    });
  });

})(jQuery);
