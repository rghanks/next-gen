<?php

declare(strict_types=1);

namespace App\Http\Requests\Lab;

use App\Domain\Lab\Models\LabOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LabOrder::class) ?? true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('walkin.phone') && is_string($this->input('walkin.phone'))) {
            $phone = preg_replace('/\D/', '', $this->input('walkin.phone'));
            if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
                $phone = substr($phone, 2);
            }
            $walkin = $this->input('walkin');
            $walkin['phone'] = $phone;
            $this->merge(['walkin' => $walkin]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_token' => ['nullable', 'string', 'max:64'],
            'patient_id' => ['nullable', 'integer', 'exists:pi,id'],
            'walkin' => ['nullable', 'array'],
            'walkin.name' => ['required_without:patient_id', 'nullable', 'string', 'max:100'],
            'walkin.age' => ['required_without:patient_id', 'nullable', 'integer', 'min:0', 'max:150'],
            'walkin.sex' => ['required_without:patient_id', 'nullable', 'string', 'in:male,female,other,Male,Female,Other'],
            'walkin.phone' => ['required_without:patient_id', 'nullable', 'digits:10'],
            'visit_id' => ['nullable', 'integer', 'exists:visitlog,id'],
            'doctor_id' => ['nullable', 'integer'],
            'referral_doctor' => ['nullable', 'string', 'max:150'],
            'payer_category' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'in:routine,stat,urgent'],
            'clinical_notes' => ['nullable', 'string', 'max:1000'],
            'branch_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_id' => ['required', 'integer', 'exists:hospital_services,id'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_per' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.outsource_lab_id' => ['nullable', 'integer'],
            'bill_discount' => ['nullable', 'numeric', 'min:0'],
            'bill_discount_per' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_reason' => ['nullable', 'string', 'max:255'],
            'discount_approver_id' => ['nullable', 'integer'],
            'payments' => ['nullable', 'array'],
            'payments.*.payment_mode' => ['required_with:payments', 'string', 'in:cash,card,upi,credit,net_banking,cheque'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0'],
            'payments.*.transaction_id' => ['nullable', 'string', 'max:100'],
            'payments.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'walkin.phone.required_without' => 'Mobile number is required for patient registration.',
            'walkin.phone.digits' => 'Mobile number must be exactly 10 digits.',
        ];
    }
}
