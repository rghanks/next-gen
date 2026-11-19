<?php

declare(strict_types=1);

namespace App\Domain\Lab\Services;

use App\Domain\Lab\Models\LabBillSequence;
use Carbon\Carbon;

class BillNumberGenerator
{
    /**
     * Get the current financial year string (e.g., "2026-27" if April-March, or current year).
     */
    public function currentFinancialYear(?Carbon $date = null): string
    {
        $date = $date ?? Carbon::now();
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');

        // Indian Financial Year: April 1 to March 31
        if ($month >= 4) {
            $nextYear = ($year + 1) % 100;

            return sprintf('%d-%02d', $year, $nextYear);
        }

        $prevYear = $year - 1;
        $currentYearShort = $year % 100;

        return sprintf('%d-%02d', $prevYear, $currentYearShort);
    }

    /**
     * Generate the next concurrency-safe bill sequence number within an active transaction.
     */
    public function nextNumber(string $prefix = 'LB', ?string $financialYear = null): int
    {
        $fy = $financialYear ?? $this->currentFinancialYear();

        $sequence = LabBillSequence::where('prefix', $prefix)
            ->where('financial_year', $fy)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $sequence = LabBillSequence::create([
                'prefix' => $prefix,
                'financial_year' => $fy,
                'last_number' => 0,
            ]);
            // Re-lock
            $sequence = LabBillSequence::where('id', $sequence->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $sequence->last_number += 1;
        $sequence->save();

        return (int) $sequence->last_number;
    }

    /**
     * Format a display bill number (e.g., "LB/26-27/000123").
     */
    public function formatBillNumber(string $prefix, string $fy, int $number): string
    {
        return sprintf('%s/%s/%06d', $prefix, $fy, $number);
    }
}
