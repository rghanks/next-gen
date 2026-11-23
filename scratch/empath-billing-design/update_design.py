from pathlib import Path

root = Path(__file__).parent

def replace_once(text, old, new):
    assert text.count(old) == 1, (old[:100], text.count(old))
    return text.replace(old, new, 1)

view = root / 'resources/views/v2/billing/index.blade.php'
s = view.read_text(encoding='utf-8')
s = replace_once(s, '  <script src="{{ asset(\'/assets/js/jquery.min.js\') }}"></script>', '''  <link rel="stylesheet" href="{{ asset('assets/css/icons.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/v2/billing-classic.css') }}?v={{ file_exists(public_path('assets/css/v2/billing-classic.css')) ? filemtime(public_path('assets/css/v2/billing-classic.css')) : '1' }}">
  <script src="{{ asset('/assets/js/jquery.min.js') }}"></script>''')
s = replace_once(s, '<body>', '<body class="billing-classic">\n  @include(\'admin.patient.includes.nav_actions\')')
s = replace_once(s, '    <x-v2.side-menu active="billing" mode="rail" />\n', '')
s = replace_once(s, '      <x-v2.topbar :show-hospital-name="false">', '''      <x-v2.topbar :show-menu-btn="false">
        <x-slot:left><nav class="billing-main-nav" aria-label="Patient navigation">@yield('nav-actions')</nav></x-slot:left>
      </x-v2.topbar>
      <div class="billing-pagebar">
        <h1><i class="uil uil-file-alt" aria-hidden="true"></i>Billing</h1>''')
s = replace_once(s, '        </div>\n      </x-v2.topbar>', '        </div>\n      </div>')
s = replace_once(s, '''              <!-- Reused Billing Component -->
              <x-v2.registration.billing-step stepNumber="3" title="Billing" emptyText="Add the consultation above, or search a service to add it to the bill." :discAuthUsers="$discAuthUsers" />''', '''              <x-v2.billing.workspace :discAuthUsers="$discAuthUsers" />''')
start = s.index('          <!-- Sticky Bottom Action Bar -->')
end = s.index('        </section>', start)
s = s[:start] + s[end:]
view.write_text(s, encoding='utf-8', newline='\r\n')

js = root / 'public/assets/js/v2/opd-billing.js'
s = js.read_text(encoding='utf-8')
s = replace_once(s, "    $('#ob-bar').attr('hidden', step !== 3);\n  }", "    $('#ob-bar').attr('hidden', step !== 3);\n    if (step === 3) handleServiceSearch();\n  }")
s = replace_once(s, '''    if (!rawQ) {
      svcHits = [];
      svcIdx = -1;
      closeSvc();
      return;
    }

''', '')
s = replace_once(s, '    }).slice(0, 20);\n\n    svcIdx', '    }).slice(0, 100);\n\n    svcIdx')
s = replace_once(s, '''      $ul.html('<li class="sv-none">No service matches "' + esc($q.val().trim()) + '".</li>');''', '''      $ul.html('<li class="sv-none">' + ($q.val().trim() ? 'No service matches "' + esc($q.val().trim()) + '".' : 'No services available.') + '</li>');
      $q.removeAttr('aria-activedescendant');''')
s = replace_once(s, "data-svc=\"' + sId + '\"", "data-svc=\"' + esc(sId) + '\"")
s = replace_once(s, '''    $ul.attr('hidden', true);
    $q.attr('aria-expanded', 'false').removeAttr('aria-activedescendant');''', '''    $q.removeAttr('aria-activedescendant');
    // The service catalogue stays visible below the bill, like legacy Billing.
    if ($('#sec-bill').is(':visible')) return;
    $ul.attr('hidden', true);
    $q.attr('aria-expanded', 'false');''')
s = replace_once(s, "        $q.prop('disabled', false).val('').focus();", "        $q.prop('disabled', false).val('').focus();\n        handleServiceSearch();")
start = s.index('  function renderBillTable() {')
end = s.index('  function calculateTotals() {', start)
s = s[:start] + '''  function renderBillTable() {
    var $tbody = $('#bill-rows');
    if (!state.services.length) {
      $tbody.empty();
      $('#bill-empty').removeAttr('hidden');
      return;
    }

    $('#bill-empty').attr('hidden', true);
    var hasDiscountPerm = canDiscount();
    var rows = state.services.map(function (item, index) {
      var gross = item.rate * item.qty;
      var discountAmt = discAmt(item.disc, item.discPct, gross);
      var netAmt = Math.max(0, gross - discountAmt);
      var isPct = !!item.discPct;
      var qtyOptions = '';
      for (var q = 1; q <= Math.max(10, item.qty); q++) {
        qtyOptions += '<option value="' + q + '"' + (Number(item.qty) === q ? ' selected' : '') + '>' + q + '</option>';
      }
      var displayVal = (item.disc !== undefined && item.disc !== null && item.disc !== '') ? item.disc : '0';
      var itemName = esc(item.name);
      return '<tr data-index="' + index + '">'
        + '<td class="c-sn"><span class="billing-cell">' + (index + 1) + '</span></td>'
        + '<td><span class="billing-cell readonly">' + esc(item.service_id || '—') + '</span></td>'
        + '<td><span class="billing-cell" title="' + itemName + '">' + itemName + '</span></td>'
        + '<td class="num">'
        + (item.is_editable
          ? '<input type="text" inputmode="decimal" maxlength="7" class="line-rate" data-item-rate="' + index + '" value="' + esc(item.rate) + '" aria-label="Charges for ' + itemName + '">'
          : '<span class="billing-cell readonly">' + inr(item.rate, 0) + '</span>')
        + '</td><td class="num">'
        + ((item.isConsult || item.isRegistration) ? '<span class="billing-cell readonly">1</span>' : '<select class="qty" data-item-qty="' + index + '" aria-label="Quantity for ' + itemName + '">' + qtyOptions + '</select>')
        + '</td><td class="num c-disc"><div class="ldisc">'
        + '<span class="dunit" role="group" aria-label="Discount unit for ' + itemName + '">'
        + '<button type="button" data-dunit="inr" data-item-idx="' + index + '" aria-pressed="' + (!isPct) + '" title="Switch discount to rupees"' + (!hasDiscountPerm ? ' disabled' : '') + '>₹</button>'
        + '<button type="button" data-dunit="pct" data-item-idx="' + index + '" aria-pressed="' + isPct + '" title="Switch discount to percent"' + (!hasDiscountPerm ? ' disabled' : '') + '>%</button></span>'
        + '<input type="text" inputmode="numeric" maxlength="' + (isPct ? 3 : 6) + '" placeholder="0" class="line-disc" data-item-disc="' + index + '" value="' + esc(displayVal) + '" aria-label="Discount for ' + itemName + '"' + (!hasDiscountPerm ? ' readonly disabled' : '') + '></div>'
        + '<span class="d-eq">' + (isPct && discountAmt > 0 ? '= ' + inr(discountAmt, 0) : '') + '</span></td>'
        + '<td class="num"><span class="billing-cell readonly l-amt">' + inr(netAmt) + '</span></td><td>'
        + ((item.isConsult || item.isRegistration) ? '' : '<button type="button" class="billing-remove" data-item-del="' + index + '" aria-label="Remove ' + itemName + '" title="Remove item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/></svg></button>')
        + '</td></tr>';
    }).join('');
    $tbody.html(rows);
  }

''' + s[end:]
s = replace_once(s, '''    // Update First payment row amount if user hasn't explicitly split payments (only in new bill mode, never in edit mode)
    if (state.payAuto && (!state.editing || !state.editing.active) && state.payments.length === 1) {
      state.payments[0].amt = payable > 0 ? payable : '';
      $('#pay-rows .pay-row').first().find('[data-pk="amt"]').val(payable > 0 ? payable : '');
    }''', '''    // Keep the default cash amount in sync until the user enters a payment split.
    if (state.payAuto && (!state.editing || !state.editing.active)) {
      var cashIdx = state.payments.findIndex(function (p) { return p.mode === 'Cash'; });
      if (cashIdx > -1) {
        state.payments[cashIdx].amt = payable > 0 ? payable : '';
        $('#pay-rows [data-pk="amt"][data-pay-idx="' + cashIdx + '"]').val(payable > 0 ? payable : '');
      }
    }''')
start = s.index('  function renderPaymentRows() {')
end = s.index('  function addPaymentRow() {', start)
s = s[:start] + '''  function renderPaymentRows() {
    var $rows = $('#pay-rows').empty();
    var modes = [
      { mode: 'Cash', label: 'CASH' },
      { mode: 'UPI', label: 'UPI' },
      { mode: 'Card', label: 'DEBIT/CREDIT CARD' },
      { mode: 'Bank transfer', label: 'NEFT/RTGS' },
      { mode: 'Cheque', label: 'CHEQUE' }
    ];
    state.payments.forEach(function (p) { p.mode = backendToFrontendMode(p.mode); });
    modes.forEach(function (m) {
      if (!state.payments.some(function (p) { return p.mode === m.mode; })) {
        state.payments.push({ mode: m.mode, amt: '', txn: '' });
      }
    });
    // Preserve individual receipt rows when editing, including repeated modes.
    modes.forEach(function (m) {
      state.payments.forEach(function (p, idx) {
        if (p.mode !== m.mode) return;
        appendPayment(p, idx, m.label);
      });
    });
    state.payments.forEach(function (p, idx) {
      if (!modes.some(function (m) { return m.mode === p.mode; })) appendPayment(p, idx, p.mode);
    });

    function appendPayment(p, idx, label) {
      var cash = p.mode === 'Cash';
      $rows.append('<div class="billing-payment-mode pay-row" data-pay="' + idx + '">'
        + '<label for="billing-pay-' + idx + '">' + esc(label) + '</label>'
        + '<div class="billing-payment-inputs' + (cash ? ' is-cash' : '') + '">'
        + '<input id="billing-pay-' + idx + '" type="text" inputmode="decimal" maxlength="7" data-pk="amt" data-pay-idx="' + idx + '" value="' + esc(p.amt || '') + '" placeholder="0" aria-label="Amount by ' + esc(p.mode) + '">'
        + (cash ? '' : '<input type="text" data-pk="txn" data-pay-idx="' + idx + '" value="' + esc(p.txn || '') + '" placeholder="TRX ID" aria-label="Transaction ID for ' + esc(p.mode) + '" title="' + esc(TXN_HINT[p.mode] || 'Transaction ID') + '" autocomplete="off" maxlength="64">')
        + '</div></div>');
    }
  }

''' + s[end:]
js.write_text(s, encoding='utf-8', newline='\r\n')
