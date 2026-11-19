<?php

declare(strict_types=1);

namespace App\Domain\Lab\Services;

use App\Domain\Lab\Models\LabParameter;
use App\Domain\Lab\Models\LabReferenceRange;

class ReferenceRangeResolver
{
    /**
     * Resolve the matched reference range for a parameter, age (days) and sex.
     *
     * @return array{low: ?float, high: ?float, critical_low: ?float, critical_high: ?float, text_range: ?string}
     */
    public function resolve(LabParameter|int $parameter, int $ageInDays, string $sex = 'both'): array
    {
        $parameterId = $parameter instanceof LabParameter ? $parameter->id : $parameter;
        $normalizedSex = strtolower(trim($sex));
        if (! in_array($normalizedSex, ['male', 'female'], true)) {
            $normalizedSex = 'both';
        }

        // Try exact sex match
        $range = LabReferenceRange::where('parameter_id', $parameterId)
            ->where('sex', $normalizedSex)
            ->where('age_from_days', '<=', $ageInDays)
            ->where('age_to_days', '>=', $ageInDays)
            ->first();

        // Fallback to 'both'
        if (! $range && $normalizedSex !== 'both') {
            $range = LabReferenceRange::where('parameter_id', $parameterId)
                ->where('sex', 'both')
                ->where('age_from_days', '<=', $ageInDays)
                ->where('age_to_days', '>=', $ageInDays)
                ->first();
        }

        // Fallback to default range for parameter if any exists
        if (! $range) {
            $range = LabReferenceRange::where('parameter_id', $parameterId)->first();
        }

        if (! $range) {
            return [
                'low' => null,
                'high' => null,
                'critical_low' => null,
                'critical_high' => null,
                'text_range' => null,
            ];
        }

        return [
            'low' => $range->low !== null ? (float) $range->low : null,
            'high' => $range->high !== null ? (float) $range->high : null,
            'critical_low' => $range->critical_low !== null ? (float) $range->critical_low : null,
            'critical_high' => $range->critical_high !== null ? (float) $range->critical_high : null,
            'text_range' => $range->text_range,
        ];
    }

    /**
     * Convert age in years, months or days to total days.
     */
    public function calculateAgeInDays(int $ageYears, int $months = 0, int $days = 0): int
    {
        return ($ageYears * 365) + ($months * 30) + $days;
    }
}
