@props([
    'mode' => 'registration',
    'title' => 'Visit',
    'stepNumber' => '2',
    'billingWorkflow' => false,
])

@php
  $isRefByEnabled = true;
  if (auth()->check() && class_exists('\App\Helpers\HospitalHelper')) {
      $isRefByEnabled = (bool) \App\Helpers\HospitalHelper::isSettingEnabled(auth()->user()->org_id, 'refered_by_in_visit');
  }
@endphp

<section class="panel sec sec-v" id="sec-visit" aria-labelledby="t-doc" tabindex="-1">
  <div class="sec-head">
    <div>
      <h2 class="sec-title" id="t-doc">
        @if(!empty($stepNumber))
          <span class="sec-n" aria-hidden="true">{{ $stepNumber }}</span>
        @endif
        {{ $title }}
      </h2>
      <span class="sec-sum" id="v-sum"></span>
    </div>
    @if($mode === 'registration')
      <div class="sec-actions">
        <span class="pill" id="visit-pill" hidden></span>
        <button type="button" class="btn btn-text" id="v-cancel" hidden>Cancel</button>
        <button type="button" class="btn btn-text" id="v-edit" hidden>Edit</button>
      </div>
    @else
      <div class="sec-actions">
        <button type="button" class="btn btn-outlined" id="sec-vitals-btn" onclick="openVitals(this)" aria-haspopup="dialog" style="display:inline-flex; align-items:center; gap:6px;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:15px;height:15px"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
          Add vitals
        </button>
      </div>
    @endif
  </div>
  <div class="sec-body">
    @if($billingWorkflow)
    <div class="field billing-visit-kind">
      <label for="billing-visit-kind">Visit reason<span class="req">*</span></label>
      <select id="billing-visit-kind" name="add_visit">
        <option value="1">Doctor consultation</option>
        <option value="0">Services only</option>
      </select>
    </div>
    @endif
    <div class="grid4 {{ !$isRefByEnabled ? 'has-3cols' : '' }}">
      <div class="field" data-field="spec">
        <label for="spec">Specialisation @if($mode !== 'billing') <span class="opt">Optional</span> @endif</label>
        <select id="spec"></select>
      </div>
      <div class="field" data-field="doc">
        <label for="doc">Doctor<span class="req" aria-hidden="true">*</span></label>
        <select id="doc" aria-required="true" aria-describedby="doc-err"></select>
        <span class="err" id="doc-err" role="alert"></span>
      </div>
      <div class="field" data-field="charge">
        <label for="charge">Consultation @if($mode !== 'billing') <span class="req" aria-hidden="true">*</span> @endif</label>
        <select id="charge" aria-describedby="charge-hint"></select>
        <span class="err" id="charge-err" role="alert"></span>
      </div>
      <div class="field" data-field="ref_by" id="f-ref-by" @if(!$isRefByEnabled) hidden style="display:none;" @endif>
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <label for="ref_by">Ref. By <span class="opt">Optional</span></label>
          <button type="button" class="btn btn-text" id="add-ref-btn" style="padding:0;min-height:auto;font-size:12px;font-weight:600;color:var(--md-sys-color-primary,#0b57d0);text-decoration:none;" title="Add referring doctor">+ Add</button>
        </div>
        <select id="ref_by" name="ref_by">
          <option value="">— Select Ref. By —</option>
        </select>
      </div>
      @if($mode === 'billing')
        <div class="field ob-consult" data-field="consult" style="grid-column: 1 / -1; margin-top: 4px;">
          <span class="lbl" aria-hidden="true">&nbsp;</span>
          <label class="check"><input type="checkbox" id="consult"><span class="c-label">Add consultation to this bill</span></label>
        </div>
      @endif
    </div>
    @if($billingWorkflow)
    <p class="err" id="billing-visit-error" role="alert"></p>
    <div class="sec-actions billing-step-actions">
      <button type="button" class="btn btn-filled" id="billing-start">Create visit &amp; start billing</button>
    </div>
    @endif
  </div>
</section>
