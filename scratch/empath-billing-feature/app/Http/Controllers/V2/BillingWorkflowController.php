<?php

namespace App\Http\Controllers\V2;

use App\Cache\DoctorCache;
use App\Cache\InsuranceCache;
use App\Helpers\HospitalHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\admin\PatientController;
use App\Models\admin\Patient;
use App\Models\admin\PatientBilling;
use App\Models\admin\Visitlog;
use App\Models\RefUsers;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingWorkflowController extends Controller
{
    public function registerPatient(Request $request)
    {
        $this->authorizeModule('patient', 'can_create');
        $this->authorizeModule('opd_billing', 'can_create');
        $request->validate([
            'patient_id' => 'prohibited',
            'id' => 'prohibited',
            'uhid' => 'prohibited',
            'prefix' => 'required|string|max:20',
            'Fname' => 'required|string|max:100',
            'Lname' => 'nullable|string|max:100',
            'mobile_no' => 'required|digits:10',
            'age' => 'required|integer|min:0|max:150',
            'dob' => 'nullable|date|before_or_equal:today',
            'gender' => 'required|in:Male,Female,Transgender,Other',
            'i_cover' => 'required|integer',
            'email_id' => 'nullable|email|max:150',
            'Address' => 'nullable|string|max:1000',
        ]);
        if (!InsuranceCache::all(auth()->user()->org_id)->contains('id', $request->integer('i_cover'))) {
            throw ValidationException::withMessages(['i_cover' => 'Select an active payment category for this hospital.']);
        }

        return $this->once($request, 'register', function () use ($request) {
            $request->replace($request->only(['prefix', 'Fname', 'Lname', 'mobile_no', 'age', 'dob', 'gender', 'i_cover', 'email_id', 'Address']));
            $request->merge(['whatsapp_checkbox' => 1]);
            $response = app(PatientController::class)->save($request);
            $result = $response->getData(true);
            if ($response->getStatusCode() < 300 && !empty($result['success'])) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'] ?? 'Patient registered.',
                    'patient_id' => $result['patient_id'],
                    'patient' => \Illuminate\Support\Arr::only($result['patient'] ?? $result['data'], ['id', 'prefix', 'Fname', 'Lname', 'sex', 'age', 'mobile_no', 'i_cover']),
                ]);
            }
            return $response;
        });
    }

    public function prepareVisit(Request $request)
    {
        $this->authorizeModule('opd_visits', 'can_create');
        $this->authorizeModule('opd_billing', 'can_create');
        $data = $request->validate([
            'patient_id' => 'required|integer|min:1',
            'doctor_id' => 'required|integer|min:1',
            'doctor_charges' => 'required|numeric|min:0',
            'add_visit' => 'required|boolean',
            'ref_by' => 'nullable|integer|min:1',
            'visit_id' => 'nullable|integer|min:1',
        ]);
        $orgId = auth()->user()->org_id;
        if (!DoctorCache::exists($orgId, (int) $data['doctor_id'])) {
            throw ValidationException::withMessages(['doctor_id' => 'Select an active doctor for this hospital.']);
        }
        $refBy = $data['ref_by'] ?? null;
        if (!HospitalHelper::isSettingEnabled($orgId, 'refered_by_in_visit')) {
            $refBy = null;
        } elseif ($refBy && !RefUsers::where('hospital_id', $orgId)->where('status', 1)->whereKey($refBy)->exists()) {
            throw ValidationException::withMessages(['ref_by' => 'Select a referring doctor for this hospital.']);
        }

        return DB::transaction(function () use ($data, $orgId, $refBy) {
            $patient = Patient::where('hospital_id', $orgId)->lockForUpdate()->findOrFail($data['patient_id']);
            $query = Visitlog::where('hospital_id', $orgId)
                ->where('patient_id', $patient->id)
                ->where('doctor_id', $data['doctor_id'])
                ->where('visit_status', 0)->where('emergency', 0)
                ->whereDate('created_at', today());
            if ($data['add_visit']) {
                $query->whereNull('visit_type');
            } else {
                $query->where('visit_type', 'non-clinical');
            }
            if (!empty($data['visit_id'])) {
                $visit = $query->whereKey($data['visit_id'])->first();
                if (!$visit) {
                    throw ValidationException::withMessages(['visit_id' => 'This visit no longer matches the patient, doctor or visit reason. Select the visit again.']);
                }
            } else {
                $visit = $query->orderByDesc('id')->first();
            }
            if (!$visit) {
                $visitId = app(PatientController::class)->prepareOpdBillingVisit(
                    $patient->id, $data['doctor_id'], $data['doctor_charges'], $orgId, (bool) $data['add_visit'], $refBy
                );
                if ($visitId instanceof \Illuminate\Http\JsonResponse) {
                    // The legacy helper rolls back its transaction before returning an error.
                    throw ValidationException::withMessages(['visit_id' => 'The visit could not be saved. Please try again.']);
                }
                $visit = Visitlog::findOrFail($visitId);
            } else {
                $visit->ref_by = $refBy;
                if (!PatientBilling::where('visit_id', $visit->id)->where('status', 1)
                    ->where(function ($q) { $q->whereNull('is_cancelled')->orWhere('is_cancelled', 0); })->exists()) {
                    $visit->ch = $data['doctor_charges'];
                }
                $visit->save();
            }
            $draftId = PatientBilling::where('visit_id', $visit->id)
                ->where(function ($q) { $q->whereNull('status')->orWhere('status', 0); })
                ->where(function ($q) { $q->whereNull('is_cancelled')->orWhere('is_cancelled', 0); })
                ->orderByDesc('id')->value('id');

            return response()->json([
                'success' => true,
                'visit_id' => $visit->id,
                'visit_no' => $visit->visit_id,
                'visit_type' => $visit->visit_type,
                'draft_bill_id' => $draftId,
                'registration_charge' => (int) $patient->is_registered === 1 ? 0 : (float) $patient->first_reg_charge,
            ]);
        });
    }

    public function saveBill(Request $request)
    {
        $this->authorizeModule('opd_billing', 'can_create');
        $data = $request->validate([
            'patient_id' => 'required|integer|min:1',
            'doctor_id' => 'required|integer|min:1',
            'prepared_visit_id' => 'required|integer|min:1',
            'bill_no' => 'nullable|integer|min:1',
            'action' => 'nullable|in:save_as_draft',
            'action2' => 'nullable|in:gen_bill_from_draft',
            'service_id' => 'required|array|min:1',
            'service_id.*' => 'required|integer|min:1',
            'charges' => 'required|array|size:' . count((array) $request->service_id),
            'charges.*' => 'required|numeric|min:0',
            'qty' => 'required|array|size:' . count((array) $request->service_id),
            'qty.*' => 'required|numeric|gt:0',
            'payment' => 'required|array',
            'payment.*' => 'required|numeric|min:0',
        ]);
        $visit = Visitlog::where('hospital_id', auth()->user()->org_id)
            ->where('patient_id', $data['patient_id'])->where('doctor_id', $data['doctor_id'])
            ->where('visit_status', 0)->where('emergency', 0)->findOrFail($data['prepared_visit_id']);
        Patient::where('hospital_id', auth()->user()->org_id)->findOrFail($data['patient_id']);

        return $this->once($request, 'bill', function () use ($request, $data, $visit) {
            if (!empty($data['bill_no'])) {
                $bill = PatientBilling::where('visit_id', $visit->id)->findOrFail($data['bill_no']);
                abort_if((int) $bill->is_cancelled === 1, 422, 'Cancelled bills cannot be billed.');
                if ((int) $bill->status === 1) {
                    abort_unless($request->action2 === 'gen_bill_from_draft', 409, 'This draft has already been billed.');
                    return response()->json(['success' => true, 'bill_id' => $bill->id, 'message' => 'Bill already generated.']);
                }
            } else {
                $draft = PatientBilling::where('visit_id', $visit->id)
                    ->where(function ($q) { $q->whereNull('status')->orWhere('status', 0); })
                    ->where(function ($q) { $q->whereNull('is_cancelled')->orWhere('is_cancelled', 0); })
                    ->orderByDesc('id')->first();
                abort_if($draft, 409, 'A draft already exists for this visit. Return to Visit and resume it before billing.');
            }
            $request->merge([
                'add_visit' => $visit->visit_type === 'non-clinical' ? 0 : 1,
                'ref_by' => $visit->ref_by,
            ]);
            return app(PatientController::class)->gen_opd_bill($request);
        });
    }

    private function authorizeModule(string $module, string $ability): void
    {
        abort_unless(auth()->user()->can('module-permission', [$module, $ability]), 403, 'You do not have permission to perform this step.');
    }

    private function once(Request $request, string $operation, callable $callback)
    {
        $request->validate(['request_id' => 'required|uuid']);
        $key = 'opd-workflow:' . auth()->user()->org_id . ':' . auth()->id() . ':' . $operation . ':' . $request->request_id;
        $fingerprint = hash('sha256', json_encode($request->except(['_token', 'request_id'])));
        // File locks work across PHP workers even when the optional Redis cache is unavailable.
        $cache = Cache::store('file');
        try {
            return $cache->lock($key . ':lock', 120)->block(3, function () use ($cache, $key, $fingerprint, $callback) {
                $saved = $cache->get($key);
                if ($saved) {
                    abort_unless(hash_equals($saved['fingerprint'], $fingerprint), 409, 'The request changed. Please submit it again.');
                    return response()->json($saved['data'], $saved['status']);
                }
                $response = $callback();
                if ($response->getStatusCode() < 300 && ($response->getData(true)['success'] ?? false)) {
                    $cache->put($key, ['fingerprint' => $fingerprint, 'data' => $response->getData(true), 'status' => $response->getStatusCode()], now()->addDay());
                }
                return $response;
            });
        } catch (LockTimeoutException $e) {
            return response()->json(['success' => false, 'message' => 'This request is still being saved. Please retry shortly.'], 409);
        }
    }
}
