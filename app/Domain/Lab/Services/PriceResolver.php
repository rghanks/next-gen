<?php

declare(strict_types=1);

namespace App\Domain\Lab\Services;

use App\Domain\Lab\Models\LabPriceList;
use App\Domain\Lab\Models\LabPriceListItem;
use App\Models\Legacy\HospitalService;
use App\Models\Legacy\HospitalServiceCharge;
use App\Models\Legacy\InvestigationGroupTestIndex;
use Carbon\Carbon;

class PriceResolver
{
    /**
     * Resolve the unit charge for a test/service based on payer category or price lists.
     */
    public function resolve(
        int $serviceId,
        string $payerCategory = 'general',
        ?int $priceListId = null,
        int $branchId = 1
    ): float {
        $today = Carbon::today()->toDateString();

        // 1. Check explicit price list if passed
        if ($priceListId) {
            $item = LabPriceListItem::where('price_list_id', $priceListId)
                ->where('service_id', $serviceId)
                ->first();
            if ($item && $item->charge > 0) {
                return (float) $item->charge;
            }
        }

        // 2. Check active price list by payer category
        $activePriceList = LabPriceList::where('payer_category', $payerCategory)
            ->where('is_active', true)
            ->where(function ($query) use ($today) {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $today);
            })
            ->latest('id')
            ->first();

        if ($activePriceList) {
            $item = LabPriceListItem::where('price_list_id', $activePriceList->id)
                ->where('service_id', $serviceId)
                ->first();
            if ($item && $item->charge > 0) {
                return (float) $item->charge;
            }
        }

        // 3. Fallback to legacy hospital_service_charges
        $legacyCharge = HospitalServiceCharge::where('service', $serviceId)
            ->where('is_active', true)
            ->first();

        if ($legacyCharge) {
            if ($legacyCharge->opd !== null && (float) $legacyCharge->opd > 0) {
                return (float) $legacyCharge->opd;
            }
            if ($legacyCharge->charges !== null) {
                $decoded = json_decode((string) $legacyCharge->charges, true);
                if (is_array($decoded) && isset($decoded['opd'])) {
                    return (float) $decoded['opd'];
                }
            }
        }

        // 4. Fallback: check hospital_services.input_attributes or default
        $service = HospitalService::find($serviceId);
        if ($service && ! empty($service->input_attributes)) {
            $attrs = is_array($service->input_attributes)
                ? $service->input_attributes
                : json_decode((string) $service->input_attributes, true);

            if (is_array($attrs) && isset($attrs['charges']) && is_numeric($attrs['charges'])) {
                return (float) $attrs['charges'];
            }
        }

        return 0.00;
    }

    /**
     * Expand components recursively if service is a group/profile/package.
     * Returns array of component HospitalService models.
     *
     * @param  list<int>  $visited
     * @return list<HospitalService>
     */
    public function expandComponents(int $serviceId, array &$visited = []): array
    {
        if (in_array($serviceId, $visited, true)) {
            return [];
        }
        $visited[] = $serviceId;

        $service = HospitalService::find($serviceId);
        if (! $service) {
            return [];
        }

        if ($service->is_group || $service->is_subgroup || $service->flag === 'lab_package') {
            $groupTests = InvestigationGroupTestIndex::with('test')
                ->where('group_id', $serviceId)
                ->where('is_active', 1)
                ->orderBy('sequence_no')
                ->get();

            if ($groupTests->isEmpty()) {
                return [$service];
            }

            $components = [];
            /** @var InvestigationGroupTestIndex $gt */
            foreach ($groupTests as $gt) {
                if ($gt->test) {
                    if ($gt->test->is_group || $gt->test->is_subgroup || $gt->test->flag === 'lab_package') {
                        $nested = $this->expandComponents($gt->test->id, $visited);
                        foreach ($nested as $sub) {
                            $components[] = $sub;
                        }
                    } else {
                        $components[] = $gt->test;
                    }
                }
            }

            return $components;
        }

        return [$service];
    }
}
