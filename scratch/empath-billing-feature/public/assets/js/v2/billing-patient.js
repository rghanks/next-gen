(function ($) {
  'use strict';

  $(function () {
    var $form = $('#billing-register');
    if (!$form.length) return;
    var pending = false;
    var requestId = null;
    var previousPayload = null;
    var duplicateRequest = null;
    var duplicateTimer = null;

    function newRequestId() {
      return '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, function (c) {
        return (c ^ window.crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16);
      });
    }

    function reset() {
      $form[0].reset();
      $form.find('[data-error]').empty();
      $form.find('[aria-invalid]').removeAttr('aria-invalid');
      $('#br-error, #br-duplicates').empty();
      $('#br-duplicates').attr('hidden', true);
      requestId = null;
      previousPayload = null;
    }

    $('#billing-new-patient').off('.billingPatient').on('click.billingPatient', function () {
      $('#ob-empty').attr('hidden', true);
      $('#billing-patient-hint').attr('hidden', true);
      $form.removeAttr('hidden');
      $('#br-first').trigger('focus');
    });
    $('#billing-register-cancel').off('.billingPatient').on('click.billingPatient', function () {
      if (pending) return;
      $form.attr('hidden', true);
      $('#billing-patient-hint, #ob-empty').removeAttr('hidden');
      $('#q').trigger('focus');
    });
    $(document).off('billing:patient-selected.billingPatient').on('billing:patient-selected.billingPatient', function () {
      $form.attr('hidden', true);
      if (!pending) reset();
    });

    $form.off('.billingPatient')
      .on('change.billingPatient', '#br-dob', function () {
        if (!this.value) return;
        var dob = new Date(this.value + 'T00:00:00');
        var today = new Date();
        var age = today.getFullYear() - dob.getFullYear();
        if (today.getMonth() < dob.getMonth() || (today.getMonth() === dob.getMonth() && today.getDate() < dob.getDate())) age--;
        if (age >= 0) $('#br-age').val(age);
      })
      .on('input.billingPatient', '#br-mobile', function () {
        var mobile = this.value.replace(/\D/g, '').slice(0, 10);
        this.value = mobile;
        clearTimeout(duplicateTimer);
        if (duplicateRequest) duplicateRequest.abort();
        $('#br-duplicates').attr('hidden', true).empty();
        if (mobile.length !== 10) return;
        duplicateTimer = setTimeout(function () {
          duplicateRequest = $.ajax({
            url: window.ROUTES.checkDuplicateMobile,
            data: { mobile_no: mobile },
            success: function (resp) {
              if ($('#br-mobile').val() !== mobile) return;
              var patients = resp.patients || [];
              if (!patients.length) return;
              var $box = $('#br-duplicates').empty().removeAttr('hidden');
              $('<span>').text('Patients already use this mobile. Select an existing patient, or continue registering a different person.').appendTo($box);
              patients.forEach(function (patient) {
                $('<button type="button" class="btn btn-text">').text((patient.name || [patient.first, patient.last].filter(Boolean).join(' ')) + ' · UHID ' + patient.uhid)
                  .on('click', function () { $(document).trigger('billing:select-patient', [patient]); }).appendTo($box);
              });
            },
            error: function (xhr, status) {
              if (status !== 'abort') $('#br-duplicates').removeAttr('hidden').text('Duplicate lookup is unavailable. Use patient search to check existing records.');
            }
          });
        }, 300);
      })
      .on('submit.billingPatient', function (event) {
        event.preventDefault();
        if (pending || !$form[0].reportValidity()) return;
        var payload = {};
        $form.serializeArray().forEach(function (field) { payload[field.name] = field.value.trim(); });
        var serialized = JSON.stringify(payload);
        if (serialized !== previousPayload) requestId = newRequestId();
        previousPayload = serialized;
        payload.request_id = requestId;
        payload.whatsapp_checkbox = 1;
        pending = true;
        var registered = false;
        $('#finder').attr('hidden', true);
        if (window.BillingGuard) window.BillingGuard.start('Patient registration is being saved. Please wait before leaving this page.');
        $form.find('input, select, button').prop('disabled', true);
        $('#billing-new-patient').prop('disabled', true);
        $('#br-error').empty();
        $form.find('[data-error]').empty();
        $form.find('[aria-invalid]').removeAttr('aria-invalid');
        $.ajax({
          url: window.ROUTES.registerBillingPatient,
          type: 'POST',
          data: payload,
          success: function (resp) {
            var p = resp.patient || resp.data;
            if (!resp.success || !p || !p.id) {
              $('#br-error').text(resp.message || 'Registration could not be saved.');
              return;
            }
            registered = true;
            $(document).trigger('billing:select-patient', [{
              id: p.id, uhid: p.id, name: [p.prefix, p.Fname, p.Lname].filter(Boolean).join(' '),
              first: p.Fname, last: p.Lname, gender: p.sex, age: p.age,
              mobile: p.mobile_no, catId: String(p.i_cover)
            }]);
            reset();
          },
          error: function (xhr) {
            var resp = xhr.responseJSON || {};
            $('#br-error').text(resp.message || 'Registration could not be saved. Your details are kept; retry to continue.');
            $.each(resp.errors || {}, function (name, messages) {
              $form.find('[data-error]').filter(function () { return $(this).data('error') === name; }).text(messages[0] || messages);
              $form.find('[name]').filter(function () { return this.name === name; }).attr('aria-invalid', 'true');
            });
          },
          complete: function () {
            pending = false;
            if (!registered) $('#finder').removeAttr('hidden');
            if (window.BillingGuard) window.BillingGuard.stop();
            $form.find('input, select, button').prop('disabled', false);
            $('#billing-new-patient').prop('disabled', false);
          }
        });
      });
  });
})(jQuery);
