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
     * Cache active price list by payer category for the request lifecycle.
     */
    protected static array $activePriceLists = [];

    /**
     * Get active price list for category with request-level caching.
     */
    public function getActivePriceList(string $payerCategory = 'general'): ?LabPriceList
    {
        $today = Carbon::today()->toDateString();
        $cacheKey = $payerCategory . '_' . $today;

        if (!array_key_exists($cacheKey, self::$activePriceLists)) {
            self::$activePriceLists[$cacheKey] = LabPriceList::where('payer_category', $payerCategory)
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
        }

        return self::$activePriceLists[$cacheKey];
    }

    /**
     * Bulk resolve charges for multiple services efficiently in 1-2 queries.
     *
     * @param \Illuminate\Support\Collection<int, HospitalService>|array<int, HospitalService> $services
     * @return array<int, float> [service_id => price]
     */
    public function resolveBatch(
        $services,
        string $payerCategory = 'general',
        ?int $priceListId = null,
        int $branchId = 1
    ): array {
        $serviceList = is_array($services) ? collect($services) : $services;
        if ($serviceList->isEmpty()) {
            return [];
        }

        $serviceIds = $serviceList->pluck('id')->all();
        $prices = [];
        $unresolvedIds = [];

        // 1. Explicit price list if provided
        if ($priceListId) {
            $items = LabPriceListItem::where('price_list_id', $priceListId)
                ->whereIn('service_id', $serviceIds)
                ->get()
                ->keyBy('service_id');

            foreach ($serviceIds as $sid) {
                if (isset($items[$sid]) && (float) $items[$sid]->charge > 0) {
                    $prices[$sid] = (float) $items[$sid]->charge;
                } else {
                    $unresolvedIds[] = $sid;
                }
            }
        } else {
            $unresolvedIds = $serviceIds;
        }

        // 2. Active price list by payer category
        if (!empty($unresolvedIds)) {
            $activePriceList = $this->getActivePriceList($payerCategory);
            if ($activePriceList) {
                $items = LabPriceListItem::where('price_list_id', $activePriceList->id)
                    ->whereIn('service_id', $unresolvedIds)
                    ->get()
                    ->keyBy('service_id');

                $nextUnresolved = [];
                foreach ($unresolvedIds as $sid) {
                    if (isset($items[$sid]) && (float) $items[$sid]->charge > 0) {
                        $prices[$sid] = (float) $items[$sid]->charge;
                    } else {
                        $nextUnresolved[] = $sid;
                    }
                }
                $unresolvedIds = $nextUnresolved;
            }
        }

        // 3. Fallback to legacy hospital_service_charges
        if (!empty($unresolvedIds)) {
            $legacyCharges = HospitalServiceCharge::whereIn('service', $unresolvedIds)
                ->where('is_active', true)
                ->get()
                ->groupBy('service');

            $nextUnresolved = [];
            foreach ($unresolvedIds as $sid) {
                $resolved = false;
                if (isset($legacyCharges[$sid])) {
                    $firstCharge = $legacyCharges[$sid]->first();
                    if ($firstCharge->opd !== null && (float) $firstCharge->opd > 0) {
                        $prices[$sid] = (float) $firstCharge->opd;
                        $resolved = true;
                    } elseif ($firstCharge->charges !== null) {
                        if (is_numeric($firstCharge->charges) && (float) $firstCharge->charges > 0) {
                            $prices[$sid] = (float) $firstCharge->charges;
                            $resolved = true;
                        } else {
                            $decoded = json_decode((string) $firstCharge->charges, true);
                            if (is_array($decoded) && isset($decoded['opd']) && (float) $decoded['opd'] > 0) {
                                $prices[$sid] = (float) $decoded['opd'];
                                $resolved = true;
                            }
                        }
                    }
                }

                if (!$resolved) {
                    $nextUnresolved[] = $sid;
                }
            }
            $unresolvedIds = $nextUnresolved;
        }

        // 4. Fallback: input_attributes on already loaded service models
        $serviceMap = $serviceList->keyBy('id');
        if (!empty($unresolvedIds)) {
            foreach ($unresolvedIds as $sid) {
                $srv = $serviceMap->get($sid);
                $resolved = false;
                if ($srv && !empty($srv->input_attributes)) {
                    $attrs = is_array($srv->input_attributes)
                        ? $srv->input_attributes
                        : json_decode((string) $srv->input_attributes, true);

                    if (is_array($attrs) && isset($attrs['charges']) && is_numeric($attrs['charges']) && (float) $attrs['charges'] > 0) {
                        $prices[$sid] = (float) $attrs['charges'];
                        $resolved = true;
                    }
                }

                if (!$resolved) {
                    $prices[$sid] = $this->resolve($sid, $payerCategory, $priceListId, $branchId);
                }
            }
        }

        return $prices;
    }

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
        $activePriceList = $this->getActivePriceList($payerCategory);

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
                if (is_numeric($legacyCharge->charges) && (float) $legacyCharge->charges > 0) {
                    return (float) $legacyCharge->charges;
                }
                $decoded = json_decode((string) $legacyCharge->charges, true);
                if (is_array($decoded) && isset($decoded['opd']) && (float) $decoded['opd'] > 0) {
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

            if (is_array($attrs) && isset($attrs['charges']) && is_numeric($attrs['charges']) && (float) $attrs['charges'] > 0) {
                return (float) $attrs['charges'];
            }
        }

        // 5. Fallback for group/profile/package: resolve sum from components if no direct charge
        if ($service && ($service->is_group || $service->is_subgroup || $service->flag === 'lab_package')) {
            $components = $this->expandComponents($serviceId);
            $compSum = 0.00;
            foreach ($components as $comp) {
                if ($comp->id !== $serviceId) {
                    $compSum += $this->resolve($comp->id, $payerCategory, $priceListId, $branchId);
                }
            }
            if ($compSum > 0) {
                return (float) $compSum;
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
