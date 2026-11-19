<?php

declare(strict_types=1);

namespace App\Domain\Lab\Services;

use App\Domain\Lab\Models\LabResultValue;
use App\Domain\Lab\States\LabState;

class DeltaChecker
{
    /**
     * Compare a result value with the patient's previous historical value for the same parameter.
     *
     * @return array{previous_value: ?float, previous_date: ?string, absolute_delta: ?float, percent_delta: ?float, is_significant: bool}
     */
    public function check(int $patientId, int $parameterId, ?float $currentNumericValue, ?int $currentOrderItemId = null): array
    {
        if ($currentNumericValue === null) {
            return [
                'previous_value' => null,
                'previous_date' => null,
                'absolute_delta' => null,
                'percent_delta' => null,
                'is_significant' => false,
            ];
        }

        // Find the most recent verified/released result value for the same patient and parameter
        $previous = LabResultValue::query()
            ->where('parameter_id', $parameterId)
            ->whereNotNull('value_numeric')
            ->when($currentOrderItemId, function ($query, $item) {
                $query->where('order_item_id', '!=', $item);
            })
            ->whereHas('orderItem.order', function ($query) use ($patientId) {
                $query->where('patient_id', $patientId)
                    ->whereIn('status', [LabState::VERIFIED->value, LabState::RELEASED->value, LabState::RESULTED->value]);
            })
            ->latest('entered_at')
            ->first();

        if (! $previous || $previous->value_numeric === null) {
            return [
                'previous_value' => null,
                'previous_date' => null,
                'absolute_delta' => null,
                'percent_delta' => null,
                'is_significant' => false,
            ];
        }

        $prevVal = (float) $previous->value_numeric;
        $diff = $currentNumericValue - $prevVal;
        $pct = $prevVal != 0.0 ? round(($diff / abs($prevVal)) * 100, 2) : 0.0;
        $isSignificant = abs($pct) >= 50.0; // Alert if delta is 50% or more

        return [
            'previous_value' => $prevVal,
            'previous_date' => $previous->entered_at ? $previous->entered_at->format('Y-m-d H:i') : null,
            'absolute_delta' => round($diff, 4),
            'percent_delta' => $pct,
            'is_significant' => $isSignificant,
        ];
    }
}
