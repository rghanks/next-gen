@props(['discAuthUsers' => []])
<section class="panel sec sec-b billing-workspace" id="sec-bill" aria-labelledby="t-bill" tabindex="-1" data-layout="classic">
  <div class="sec-head billing-workspace-heading">
    <h2 class="sec-title" id="t-bill"><span class="sec-n" aria-hidden="true">3</span>Billing</h2>
  </div>
  <div class="billing-columns">
    <div class="billing-services">
      <div class="billing-table-scroll">
        <table class="bill">
          <caption class="sr">Items on this bill</caption>
          <thead><tr><th scope="col" class="c-sn">Sr.No.</th><th scope="col" class="c-service-id">Service ID</th><th scope="col">Service Name</th><th scope="col" class="num">Charges</th><th scope="col" class="num">Qty.</th><th scope="col" class="num c-disc">Discount (₹)</th><th scope="col" class="num">Amount</th><th scope="col">Action</th></tr></thead>
          <tbody id="bill-rows"></tbody>
        </table>
      </div>
      <p class="bill-empty" id="bill-empty">Search below to add services to this bill.</p>
      <div class="svc-find billing-service-browser" id="svc-find">
        <label class="sr" for="svc-q">Search a service to add</label>
        <input id="svc-q" type="text" autocomplete="off" placeholder="Search services by name or ID..." role="combobox" aria-expanded="false" aria-controls="svc-list" aria-autocomplete="list">
        <ul class="svc-list" id="svc-list" role="listbox" aria-label="Services" hidden></ul>
        <select id="add-svc" hidden tabindex="-1" aria-hidden="true"></select>
      </div>
    </div>
    <aside class="billing-payment" aria-labelledby="pay-h">
      <h3 id="pay-h">Payment Details</h3>
      <div class="totals">
        <div class="tot"><span>SubTotal</span><span class="t-v billing-readout" id="t-sub">₹0.00</span></div>
        <div class="tot" id="t-disc-row" hidden><span>Item discounts</span><span class="t-v billing-readout" id="t-disc">₹0.00</span></div>
        <div class="tot tot-bd"><span>Discount</span><span class="bd-ctl">
          <span class="ldisc"><span class="dunit" role="group" aria-label="Bill discount in"><button type="button" data-bunit="inr" aria-pressed="true" title="Bill discount in rupees">₹</button><button type="button" data-bunit="pct" aria-pressed="false" title="Bill discount as a percent">%</button></span><input id="bdisc" type="text" inputmode="decimal" maxlength="10" placeholder="0" aria-label="Discount on the whole bill"></span>
          <span class="sr" id="t-bdisc">₹0.00</span>
        </span></div>
        <div class="tot tot-auth" id="tot-auth-row" hidden><label for="discount_auth_by">Discount auth by<span class="req">*</span></label><select id="discount_auth_by" aria-label="Discount Authorized By"><option value="">Select Auth By</option>@foreach($discAuthUsers as $user)<option value="{{ $user['id'] }}">{{ $user['name'] }}</option>@endforeach</select></div>
        <div class="tot"><label for="remark">Remarks</label><input id="remark" type="text" placeholder="Enter Remarks" autocomplete="off" maxlength="120"></div>
        <div class="tot tot-big"><span>Total</span><span class="t-v billing-readout" id="t-pay">₹0.00</span></div>
      </div>
      <div hidden><input id="disc" type="text" value="0" tabindex="-1" aria-hidden="true"><div id="mode"><button type="button" data-mode="Cash" aria-pressed="true" tabindex="-1">Cash</button></div></div>
      <div class="pay billing-payment-modes">
        <div id="pay-rows"></div>
        <div class="tot pay-got"><span>Total Collect Amount</span><span class="t-v billing-readout" id="pay-got">₹0.00</span></div>
        <div class="tot pay-bal" id="pay-bal-box"><span id="pay-bal-k">Balance</span><span class="t-v billing-readout" id="pay-bal">₹0.00</span></div>
        <span class="err pay-err" id="pay-err" role="alert"></span>
        <p class="pay-by">Collected by <b id="pay-by">{{ auth()->user()->name }}</b></p>
      </div>
      <div class="actionbar billing-actions" id="ob-bar" hidden>
        <div class="ab-what"><div class="ab-line" id="ab-line"></div><p class="ab-sub" id="ab-sub"></p></div>
        <span class="sr" id="ab-amt">₹0.00</span>
        <div class="ab-btns"><button type="button" class="btn btn-outlined" id="save-only">Bill later</button><button type="button" class="btn btn-outlined" id="ob-clear">Clear</button><button type="button" class="btn btn-filled" id="collect">Collect ₹0.00<span class="kbd">Alt S</span></button></div>
      </div>
    </aside>
  </div>
</section>
