<?php

declare(strict_types=1);

namespace App\Domain\Lab\Services;

class FlagCalculator
{
    /**
     * Compute flag for a numeric result based on reference and critical ranges.
     * Flags:
     * - LL: Critical panic low
     * - HH: Critical panic high
     * - L: Low
     * - H: High
     * - N: Normal
     * - A: Abnormal
     */
    public function calculate(
        ?float $numericValue,
        ?float $low,
        ?float $high,
        ?float $criticalLow = null,
        ?float $criticalHigh = null,
        ?string $textValue = null
    ): string {
        if ($numericValue === null) {
            if ($textValue !== null && in_array(strtoupper(trim($textValue)), ['POSITIVE', 'REACTIVE', 'PRESENT', 'ABNORMAL'], true)) {
                return 'A';
            }

            return 'N';
        }

        // Critical panic ranges take priority
        if ($criticalLow !== null && $numericValue < $criticalLow) {
            return 'LL';
        }

        if ($criticalHigh !== null && $numericValue > $criticalHigh) {
            return 'HH';
        }

        // Standard reference ranges
        if ($low !== null && $numericValue < $low) {
            return 'L';
        }

        if ($high !== null && $numericValue > $high) {
            return 'H';
        }

        return 'N';
    }
}
