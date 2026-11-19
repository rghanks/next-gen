<?php

declare(strict_types=1);

namespace App\Domain\Lab\Services;

use App\Domain\Lab\Models\LabResultValue;

class CriticalValueChecker
{
    /**
     * Check if a flag represents a critical panic value.
     */
    public function isCritical(string $flag): bool
    {
        return in_array($flag, ['LL', 'HH'], true);
    }

    /**
     * Verify if all critical values in an order or item are acknowledged.
     *
     * @param  iterable<LabResultValue>  $resultValues
     * @return array{has_unacknowledged_critical: bool, unacknowledged_count: int}
     */
    public function checkAcknowledgements(iterable $resultValues): array
    {
        $unackCount = 0;

        foreach ($resultValues as $val) {
            if ($this->isCritical($val->flag)) {
                $ack = $val->criticalAcknowledgement()->exists();
                if (! $ack) {
                    $unackCount++;
                }
            }
        }

        return [
            'has_unacknowledged_critical' => $unackCount > 0,
            'unacknowledged_count' => $unackCount,
        ];
    }
}
