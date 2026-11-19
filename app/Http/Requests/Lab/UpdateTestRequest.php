<?php

declare(strict_types=1);

namespace App\Http\Requests\Lab;

use App\Models\Legacy\HospitalService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var HospitalService|null $test */
        $test = $this->route('test');
        $testId = $test ? $test->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('hospital_services', 'service')->ignore($testId),
            ],
            'short_code' => ['nullable', 'string', 'max:50'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:100'],
            'is_group' => ['boolean'],
            'tat' => ['nullable', 'integer', 'min:0'],
            'report_time' => ['nullable', 'integer', 'min:0'],
            'loinc_code' => ['nullable', 'string', 'max:50'],
            'specimen_type' => ['nullable', 'string', 'max:100'],
            'container' => ['nullable', 'string', 'max:100'],
            'sample_quantity' => ['nullable', 'numeric', 'min:0'],
            'sample_unit' => ['nullable', 'string', 'max:20'],
            'storage_instruction' => ['nullable', 'string', 'max:255'],
            'base_charge' => ['nullable', 'numeric', 'min:0'],
            'item_cost' => ['nullable', 'numeric', 'min:0'],
            'is_outsource' => ['boolean'],
            'component_ids' => ['nullable', 'array'],
            'component_ids.*' => ['integer', 'exists:hospital_services,id'],
        ];
    }
}
