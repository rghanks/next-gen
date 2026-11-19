<?php

declare(strict_types=1);

namespace App\Http\Requests\Lab;

use App\Domain\Lab\Models\LabOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CancelBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cancel', LabOrder::class) ?? true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:lab_orders,id'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
            'refund_mode' => ['nullable', 'string', 'in:cash,card,upi,bank_transfer,credit_note'],
        ];
    }
}
