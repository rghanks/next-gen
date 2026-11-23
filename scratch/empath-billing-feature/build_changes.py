from pathlib import Path
import json
import hashlib

ROOT = Path(__file__).parent
SOURCE = Path('C:/xampp/htdocs/empath')
manifest = json.loads((ROOT / 'baseline.json').read_text(encoding='utf-8-sig'))

def stage(path):
    dest = ROOT / path
    if not dest.exists():
        dest.parent.mkdir(parents=True, exist_ok=True)
        raw = (SOURCE / path).read_bytes()
        dest.write_bytes(raw)
        manifest.append({'path': path, 'hash': hashlib.sha256(raw).hexdigest().upper()})
    return dest

def change(path, old, new, count=1):
    dest = stage(path)
    raw = dest.read_bytes()
    text = raw.decode('utf-8').replace('\r\n', '\n')
    if (count == -1 and old not in text) or (count != -1 and text.count(old) != count):
        raise RuntimeError(f'{path}: expected {count} occurrences, got {text.count(old)}: {old[:100]}')
    text = text.replace(old, new, 1 if count == -1 else count)
    dest.write_bytes(text.replace('\n', '\r\n').encode('utf-8') if b'\r\n' in raw else text.encode('utf-8'))

pc = 'app/Http/Controllers/admin/PatientController.php'
change(pc,
    '    private function create_visit($patient_id, $doctor_id,$charges, $org_id, $add_visit, $visit_date = null, $ref_by = null){',
    '''    public function prepareOpdBillingVisit($patientId, $doctorId, $charges, $orgId, $addVisit, $refBy = null)
    {
        // V2 has already selected/locked the encounter context; legacy callers retain same-day reuse.
        return $this->create_visit($patientId, $doctorId, $charges, $orgId, $addVisit, null, $refBy, false);
    }

    private function create_visit($patient_id, $doctor_id,$charges, $org_id, $add_visit, $visit_date = null, $ref_by = null, $reuseExisting = true){''')
change(pc,
    '$visit_id = $this->check_if_patient_already_visit_for_today($patient_id, $doctor_id, $org_id, $date);',
    '$visit_id = $reuseExisting ? $this->check_if_patient_already_visit_for_today($patient_id, $doctor_id, $org_id, $date) : null;')
change(pc,
    '        $add_visit = $request->add_visit;\n\n        // --- Billing Validations ---',
    '''        $add_visit = $request->add_visit;
        $preparedVisit = null;
        if ($request->filled('prepared_visit_id')) {
            $preparedVisit = Visitlog::where('hospital_id', $org_id)
                ->where('patient_id', $patient_id)->where('doctor_id', $doctor_id)
                ->where('visit_status', 0)->where('emergency', 0)
                ->find($request->prepared_visit_id);
            if (!$preparedVisit || (int) $pi->hospital_id !== (int) $org_id) {
                return response()->json(['success' => false, 'message' => 'The prepared visit does not match this patient and doctor.'], 422);
            }
            if ($request->filled('bill_no') && !PatientBilling::whereKey($request->bill_no)->where('visit_id', $preparedVisit->id)->exists()) {
                return response()->json(['success' => false, 'message' => 'The bill does not belong to the prepared visit.'], 422);
            }
        }

        // --- Billing Validations ---''')
change(pc,
    "                    ->where('patient_billing.id', $bill_no)\n                    ->where('visitlog.doctor_id', $doctor_id)",
    "                    ->where('patient_billing.id', $bill_no)\n                    ->when($preparedVisit, function ($q) use ($preparedVisit) { $q->where('visitlog.id', $preparedVisit->id); })\n                    ->where('visitlog.doctor_id', $doctor_id)")
change(pc,
    "                        ->where('visitlog.hospital_id', $org_id)\n                        ->where(function ($q) use ($patient_id) {",
    "                        ->where('visitlog.hospital_id', $org_id)\n                        ->when($preparedVisit, function ($q) use ($preparedVisit) { $q->where('visitlog.id', $preparedVisit->id); })\n                        ->where(function ($q) use ($patient_id) {")
change(pc,
    "                    ->where('visitlog.hospital_id', $org_id)\n                    ->where(function ($q) use ($patient_id) {",
    "                    ->where('visitlog.hospital_id', $org_id)\n                    ->when($preparedVisit, function ($q) use ($preparedVisit) { $q->where('visitlog.id', $preparedVisit->id); })\n                    ->where(function ($q) use ($patient_id) {")
change(pc,
    '$visit_id = $this->create_visit($patient_id, $doctor_id, $charges, $org_id, $add_visit, null, $request->ref_by ?? null);',
    '$visit_id = $preparedVisit ? $preparedVisit->id : $this->create_visit($patient_id, $doctor_id, $charges, $org_id, $add_visit, null, $request->ref_by ?? null);', count=-1)

bc = 'app/Http/Controllers/V2/BillingController.php'
change(bc,
    "        $categories = InsuranceCache::all($org_id)\n            ->pluck('insurance_agen', 'id')\n            ->all();",
    "        $insuranceList = InsuranceCache::all($org_id);\n        $categories = $insuranceList->pluck('insurance_agen', 'id')->all();")
change(bc,
    "            'categories' => $categories,",
    "            'categories' => $categories,\n            'cash_category_id' => $insuranceList->firstWhere('is_cash', 1)->id ?? null,\n            'titles' => ['Mr.', 'Mrs.', 'Ms.', 'Dr.', 'Master', 'Baby', 'Baby of', 'Smt.', 'Shri'],\n            'genders' => ['Male', 'Female', 'Transgender', 'Other'],")
change(bc,
    "            'is_admin' => $isAdmin,\n            'add_referred_by' => [",
    "            'is_admin' => $isAdmin,\n            'patient' => [\n                'can_create' => (bool) $user->can('module-permission', ['patient', 'can_create']),\n            ],\n            'add_referred_by' => [")

change('routes/web.php',
    "        Route::get('/list', [App\\Http\\Controllers\\V2\\BillingController::class, 'listBills'])->name('v2.billing.list');",
    "        Route::post('/register-patient', [App\\Http\\Controllers\\V2\\BillingWorkflowController::class, 'registerPatient'])->name('v2.billing.register_patient');\n        Route::post('/prepare-visit', [App\\Http\\Controllers\\V2\\BillingWorkflowController::class, 'prepareVisit'])->name('v2.billing.prepare_visit');\n        Route::post('/save', [App\\Http\\Controllers\\V2\\BillingWorkflowController::class, 'saveBill'])->name('v2.billing.save');\n        Route::get('/list', [App\\Http\\Controllers\\V2\\BillingController::class, 'listBills'])->name('v2.billing.list');")

visit = 'resources/views/components/v2/registration/visit-step.blade.php'
change(visit, "    'stepNumber' => '2',", "    'stepNumber' => '2',\n    'billingWorkflow' => false,")
change(visit, '  <div class="sec-body">\n    <div class="grid4', '''  <div class="sec-body">
    @if($billingWorkflow)
    <div class="field billing-visit-kind">
      <label for="billing-visit-kind">Visit reason<span class="req">*</span></label>
      <select id="billing-visit-kind" name="add_visit">
        <option value="1">Doctor consultation</option>
        <option value="0">Services only</option>
      </select>
    </div>
    @endif
    <div class="grid4''')
change(visit, '    </div>\n  </div>\n</section>', '''    </div>
    @if($billingWorkflow)
    <p class="err" id="billing-visit-error" role="alert"></p>
    <div class="sec-actions billing-step-actions">
      <button type="button" class="btn btn-filled" id="billing-start">Create visit &amp; start billing</button>
    </div>
    @endif
  </div>
</section>''')

view = 'resources/views/v2/billing/index.blade.php'
change(view, '          <div class="ob-work">', '          <div class="ob-work">\n            <x-v2.billing.patient-step :initialData="$initialData" :userPermissions="$userPermissions" />', count=-1)
change(view, 'Search by UHID, phone number, ABHA ID or name. A patient not yet registered is registered first, on <a id="reg-link" href="{{ route(\'registration.index\') }}">Registration</a>.', 'Search by UHID, phone number, ABHA ID or name, or use Register new patient above to complete all three steps here.')
change(view, '<h2 id="pt-name"></h2>', '<h2><span class="sec-n" aria-hidden="true">1</span><span id="pt-name"></span></h2>')
change(view, '<x-v2.registration.visit-step mode="billing" stepNumber="" title="Doctor and consultation" />', '''<section class="panel sec" id="billing-visit-summary" hidden>
                <div class="sec-head">
                  <div><h2 class="sec-title"><span class="sec-n" aria-hidden="true">2</span>Visit</h2><span class="sec-sum" id="billing-visit-description"></span></div>
                  <button type="button" class="btn btn-text" id="billing-edit-visit">Edit visit</button>
                </div>
              </section>
              <x-v2.registration.visit-step mode="billing" stepNumber="2" title="Visit" :billingWorkflow="true" />''')
change(view, '<x-v2.registration.billing-step stepNumber="" title="Billing"', '<x-v2.registration.billing-step stepNumber="3" title="Billing"')
change(view, "      saveVisit: \"{{ route('visit.save') }}\",", "      saveVisit: \"{{ route('visit.save') }}\",\n      registerBillingPatient: \"{{ route('v2.billing.register_patient') }}\",\n      prepareBillingVisit: \"{{ route('v2.billing.prepare_visit') }}\",\n      saveBillingWorkflow: \"{{ route('v2.billing.save') }}\",\n      checkDuplicateMobile: \"{{ route('registration.check_duplicate_mobile') }}\",")
change(view, '  <script src="{{ asset(\'assets/js/v2/opd-billing.js\') }}', '  <script src="{{ asset(\'assets/js/v2/billing-patient.js\') }}?v={{ time() }}" charset="utf-8"></script>\n  <script src="{{ asset(\'assets/js/v2/opd-billing.js\') }}')

(ROOT / 'baseline.json').write_text(json.dumps(manifest, indent=2), encoding='utf-8')
print('Prepared backend, routes and Blade changes.')
