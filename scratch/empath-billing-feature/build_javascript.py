from pathlib import Path

root = Path(__file__).parent
path = root / 'public/assets/js/v2/opd-billing.js'
text = path.read_bytes().decode('utf-8').replace('\r\n', '\n')

def change(old, new, count=1):
    global text
    if text.count(old) != count:
        raise RuntimeError(f'Expected {count}, got {text.count(old)}: {old[:110]}')
    text = text.replace(old, new)

change("    activeTab: 'new',\n    patient: null,", "    activeTab: 'new',\n    patient: null,\n    workflowStep: 1,\n    visitPrepared: false,\n    preparingVisit: false,\n    chargeRequest: 0,\n    patientRequest: 0,\n    billRequestId: null,\n    billRequestPayload: null,")

start = text.index('    var patientId = p.id || p.uhid;\n', text.index('  function selectPatient(p)'))
end = text.index('\n  function loadDraftBillData(d)', start)
text = text[:start] + '''    setWorkflowStep(2);
    $(document).trigger('billing:patient-selected');
    snack(name + ' · ' + p.uhid + ' selected. Create the visit to continue.');
  }
''' + text[end:]

change('  function loadDraftBillData(d) {', '  function loadDraftBillData(d, onDone) {')
change("    state.regPrimaryId = d.reg_primary_id || null;", "    state.regPrimaryId = d.reg_primary_id || null;\n    $('#consult').prop('checked', !!d.consult_primary_id);", 1)
change("    renderBillTable();\n    calculateTotals();\n    snack((state.patient ?", "    setRegistrationItem(parseFloat(d.reg_charge) || 0, d.reg_primary_id);\n    renderBillTable();\n    calculateTotals();\n    snack((state.patient ?")
change("        state.isLoadingDraft = false;\n      });\n    } else {\n      state.isLoadingDraft = false;\n    }\n  }", "        state.isLoadingDraft = false;\n        if (onDone) onDone();\n      });\n    } else {\n      state.isLoadingDraft = false;\n      if (onDone) onDone();\n    }\n  }")
change("    state.patient = null;\n    state.visitId = null;", "    state.patient = null;\n    resetBillForm();\n    setWorkflowStep(1);")

workflow = '''  function workflowEditing() {
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
    $('#billing-patient-hint').removeAttr('hidden');
    if (step === 1) $('#billing-register').attr('hidden', true);
    $('#sec-visit').toggle(step === 2);
    $('#sec-bill').toggle(step === 3);
    $('#billing-visit-summary').attr('hidden', step !== 3);
    $('#ob-bar').attr('hidden', step !== 3);
  }

  function invalidateVisit(clearBill) {
    if (workflowEditing() || state.isLoadingDraft) return;
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

'''
change('  // --- REFERRAL DOCTORS (REF. BY) ---', workflow + '  // --- REFERRAL DOCTORS (REF. BY) ---')

start = text.index('    // If change was made by user (not loading a draft programmatically)')
end = text.index('\n  function fetchDoctorCharges', start)
text = text[:start] + '    fetchDoctorCharges(docId);\n  }\n' + text[end:]
change('  function onDocChange() {\n    var docId', '  function onDocChange() {\n    invalidateVisit(true);\n    state.chargeRequest++;\n    var docId')
change("    $('#charge').empty().append('<option value=\"\">Choose a doctor first</option>').prop('disabled', true);\n    $('#consult').prop('disabled', true);\n    updateConsultationItem();\n  }", '    onDocChange();\n  }')
change('    var docs = window.INITIAL_DATA.doctors || [];\n    for (var i = 0; i < docs.length; i++) {\n      if (String(docs[i].id) === String(docId)) {\n        state.doctor = docs[i];\n        break;\n      }\n    }\n\n    $(\'#charge\').empty()', '    var chargeToken = ++state.chargeRequest;\n    $(\'#charge\').empty()')
change("    $('#consult').prop('disabled', false);\n\n    var catId", "    $('#consult').prop('disabled', true);\n\n    var catId")
change("      success: function (res) {\n        var $charge = $('#charge').empty();", "      success: function (res) {\n        if (chargeToken !== state.chargeRequest) return;\n        var $charge = $('#charge').empty();")
change("          updateConsultationItem();\n        } else {\n          // Fallback charge from doctor model or standard", "          $('#consult').prop('disabled', $('#billing-visit-kind').val() === '0');\n          updateConsultationItem();\n        } else {\n          // Fallback charge from doctor model or standard")
start = text.index('          // Fallback charge from doctor model or standard')
end = text.index('\n        if (typeof onDone', start)
text = text[:start] + '''          state.consultation = null;
          $charge.append('<option value="">No consultation charges configured</option>').prop('disabled', true);
          $('#consult').prop('disabled', true);
          updateConsultationItem();
        }''' + text[end:]
start = text.index("      error: function () {\n        var defRate = state.doctor")
end = text.index('\n        if (typeof onDone', start)
text = text[:start] + '''      error: function () {
        if (chargeToken !== state.chargeRequest) return;
        state.consultation = null;
        $('#charge').empty().append('<option value="">Charges unavailable — select the doctor to retry</option>').prop('disabled', true);
        $('#consult').prop('disabled', true);
        updateConsultationItem();
        $('#billing-visit-error').text('Consultation charges could not be loaded. Select the doctor again to retry.');''' + text[end:]
change('  function onChargeChange() {', '  function onChargeChange() {\n    invalidateVisit(false);')

change("(item.isConsult ? '1' : '<select", "((item.isConsult || item.isRegistration) ? '1' : '<select")
change("(item.isConsult ? '' : '<button", "((item.isConsult || item.isRegistration) ? '' : '<button")

change('  function resetBillForm() {\n    state.visitId = null;', '''  function resetBillForm() {
    state.visitPrepared = false;
    state.patientRequest++;
    state.chargeRequest++;
    state.billRequestId = null;
    state.billRequestPayload = null;
    state.visitId = null;''')
change("    $('#charge').empty().append('<option value=\"\">Select consultation...</option>');\n    $('#consult').prop('checked', false);", "    $('#charge').empty().append('<option value=\"\">Select consultation...</option>');\n    $('#billing-visit-kind').val('1');\n    $('#billing-visit-error').empty();\n    $('#sec-visit [data-field=\"charge\"], #sec-visit .ob-consult').show();\n    $('#consult').prop('checked', true);")
change('    calculateTotals();\n  }\n\n  // --- SUBMIT BILL ---', '    calculateTotals();\n    if (!workflowEditing()) setWorkflowStep(state.patient ? 2 : 1);\n  }\n\n  // --- SUBMIT BILL ---')
change("    isDraft = (isDraft === true);\n\n    if (!state.patient)", "    isDraft = (isDraft === true);\n    if (!workflowEditing() && (!state.visitPrepared || state.workflowStep !== 3 || !state.visitId || state.preparingVisit)) {\n      snack('Create or confirm the visit before billing.');\n      return;\n    }\n\n    if (!state.patient)")
change("    if (window.BillingGuard) {\n      window.BillingGuard.start('Billing is in progress, do not refresh or move from page.');\n    }\n\n    $.ajax({\n      url: window.ROUTES.genOpdBill,", '''    if (!workflowEditing()) {
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
      url: workflowEditing() ? window.ROUTES.genOpdBill : window.ROUTES.saveBillingWorkflow,''')
change("    populateDiscountAuthorizers();\n    var initialTab", "    setWorkflowStep(1);\n    populateDiscountAuthorizers();\n    var initialTab")
change("    $('#pt-change').on('click', changePatient);", '''    $('#pt-change').on('click', changePatient);
    $(document).off('billing:select-patient.billingWorkflow').on('billing:select-patient.billingWorkflow', function (e, patient) { selectPatient(patient); });
    $('#billing-start').off('.billingWorkflow').on('click.billingWorkflow', prepareBillingVisit);
    $('#billing-edit-visit').off('.billingWorkflow').on('click.billingWorkflow', function () { invalidateVisit(false); });
    $('#billing-visit-kind').off('.billingWorkflow').on('change.billingWorkflow', function () { invalidateVisit(true); updateVisitKind(); });''')
change("      state.refBy = $(this).val();", "      state.refBy = $(this).val();\n      invalidateVisit(false);")
change("    switchTab('new');\n    state.payAuto = false;", "    switchTab('new');\n    $('#billing-patient, #billing-visit-summary').attr('hidden', true);\n    state.payAuto = false;")

path.write_bytes(text.replace('\n', '\r\n').encode('utf-8'))
css = root / 'public/assets/css/v2/opd-billing.css'
with css.open('ab') as f:
    f.write(b'\r\n/* Three-step New bill workflow */\r\n.billing-step-actions{justify-content:flex-end;margin-top:var(--md-sys-spacing-4);gap:var(--md-sys-spacing-3);flex-wrap:wrap}\r\n.billing-visit-kind{max-width:360px;margin-bottom:var(--md-sys-spacing-4)}\r\n#billing-patient-hint{margin:0;color:var(--md-sys-color-on-surface-variant)}\r\n#br-duplicates{margin-top:var(--md-sys-spacing-4);flex-wrap:wrap}\r\n#billing-visit-description{display:block;margin-top:4px}\r\n')
print('Prepared billing workflow JavaScript and styles.')
