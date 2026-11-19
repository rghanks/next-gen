@props(['initialData' => [], 'userPermissions' => []])
<section class="panel sec" id="billing-patient" aria-labelledby="billing-patient-title">
  <div class="sec-head">
    <h2 class="sec-title" id="billing-patient-title"><span class="sec-n" aria-hidden="true">1</span>Patient</h2>
    <div class="sec-actions">
      @if(!empty($userPermissions['patient']['can_create']))
      <button type="button" class="btn btn-outlined" id="billing-new-patient">Register new patient</button>
      @endif
    </div>
  </div>
  <div class="sec-body">
    <p id="billing-patient-hint">Search above to select an existing patient, or register a new patient here.</p>
    <form id="billing-register" hidden novalidate>
      <div class="grid4">
        <div class="field" data-field="prefix">
          <label for="br-prefix">Title<span class="req">*</span></label>
          <select id="br-prefix" name="prefix" required>
            @foreach($initialData['titles'] as $title)
            <option value="{{ $title }}">{{ $title }}</option>
            @endforeach
          </select>
          <span class="err" data-error="prefix" role="alert"></span>
        </div>
        <div class="field" data-field="Fname">
          <label for="br-first">First name<span class="req">*</span></label>
          <input id="br-first" name="Fname" type="text" maxlength="100" autocomplete="off" required>
          <span class="err" data-error="Fname" role="alert"></span>
        </div>
        <div class="field" data-field="Lname">
          <label for="br-last">Last name<span class="opt">Optional</span></label>
          <input id="br-last" name="Lname" type="text" maxlength="100" autocomplete="off">
          <span class="err" data-error="Lname" role="alert"></span>
        </div>
        <div class="field" data-field="mobile_no">
          <label for="br-mobile">Mobile number<span class="req">*</span></label>
          <input id="br-mobile" name="mobile_no" type="tel" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" required>
          <span class="err" data-error="mobile_no" role="alert"></span>
        </div>
        <div class="field" data-field="age">
          <label for="br-age">Age (years)<span class="req">*</span></label>
          <input id="br-age" name="age" type="number" min="0" max="150" step="1" required>
          <span class="err" data-error="age" role="alert"></span>
        </div>
        <div class="field" data-field="dob">
          <label for="br-dob">Date of birth<span class="opt">Optional</span></label>
          <input id="br-dob" name="dob" type="date" max="{{ now()->toDateString() }}">
          <span class="err" data-error="dob" role="alert"></span>
        </div>
        <div class="field" data-field="gender">
          <label for="br-gender">Gender<span class="req">*</span></label>
          <select id="br-gender" name="gender" required>
            <option value="">Select gender</option>
            @foreach($initialData['genders'] as $gender)
            <option value="{{ $gender }}">{{ $gender }}</option>
            @endforeach
          </select>
          <span class="err" data-error="gender" role="alert"></span>
        </div>
        <div class="field" data-field="i_cover">
          <label for="br-category">Payment category<span class="req">*</span></label>
          <select id="br-category" name="i_cover" required>
            <option value="">Select category</option>
            @foreach($initialData['categories'] as $id => $category)
            <option value="{{ $id }}" @selected((string)$id === (string)$initialData['cash_category_id'])>{{ $category }}</option>
            @endforeach
          </select>
          <span class="err" data-error="i_cover" role="alert"></span>
        </div>
        <div class="field" data-field="email_id">
          <label for="br-email">Email<span class="opt">Optional</span></label>
          <input id="br-email" name="email_id" type="email" maxlength="150">
          <span class="err" data-error="email_id" role="alert"></span>
        </div>
        <div class="field f-wide" data-field="Address">
          <label for="br-address">Address<span class="opt">Optional</span></label>
          <input id="br-address" name="Address" type="text" maxlength="1000">
          <span class="err" data-error="Address" role="alert"></span>
        </div>
      </div>
      <div class="banner banner-warn" id="br-duplicates" hidden role="status"></div>
      <p id="br-error" class="err" role="alert"></p>
      <div class="sec-actions billing-step-actions">
        <button type="button" class="btn btn-text" id="billing-register-cancel">Search existing patient</button>
        <button type="submit" class="btn btn-filled" id="billing-register-save">Register &amp; continue to visit</button>
      </div>
    </form>
  </div>
</section>
