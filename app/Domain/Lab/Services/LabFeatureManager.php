<?php

declare(strict_types=1);

namespace App\Domain\Lab\Services;

use Illuminate\Support\Facades\Cache;

class LabFeatureManager
{
    /**
     * Check if the new LIS module is enabled for a given branch / hospital.
     */
    public function isEnabledForBranch(int $branchId = 1): bool
    {
        $cacheKey = "lis_feature_flag_branch_{$branchId}";

        return (bool) Cache::remember($cacheKey, 3600, function () use ($branchId) {
            // Default enabled, can be customized per branch via env or cache override
            $configured = config("lab.branches.{$branchId}.enabled");
            if ($configured !== null) {
                return (bool) $configured;
            }

            return config('lab.enabled_globally', true);
        });
    }

    /**
     * Set feature flag status for a branch.
     */
    public function setBranchStatus(int $branchId, bool $enabled): void
    {
        Cache::put("lis_feature_flag_branch_{$branchId}", $enabled, 86400 * 30);
    }
}
