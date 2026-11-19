<?php

declare(strict_types=1);

namespace App\Domain\Lab\Actions;

use App\Domain\Lab\Models\LabPriceList;
use App\Domain\Lab\Models\LabPriceListItem;
use App\Models\Legacy\HospitalService;
use App\Models\Legacy\HospitalServiceCharge;
use Illuminate\Support\Facades\DB;

class SaveTestPricing
{
    /**
     * Save pricing across payer categories and dual-write to legacy tables.
     *
     * @param  array<string, float|int|numeric-string>  $pricingMap  [ 'general' => 350.0, 'tpa' => 300.0, ... ]
     */
    public function execute(HospitalService $service, array $pricingMap, float $itemCost = 0.0): HospitalService
    {
        return DB::transaction(function () use ($service, $pricingMap) {
            $generalPrice = (float) ($pricingMap['general'] ?? 0.0);

            // 1. Dual-write to legacy hospital_service_charges
            HospitalServiceCharge::updateOrCreate(
                [
                    'hospital_id' => 1,
                    'service' => $service->id,
                ],
                [
                    'charges' => $generalPrice,
                    'opd' => $generalPrice,
                    'er_opd' => $generalPrice,
                    'location_1' => $generalPrice,
                    'is_active' => 1,
                    'is_editable' => 1,
                ]
            );

            // 2. Domain lab_price_lists and items
            foreach ($pricingMap as $category => $charge) {
                $categorySlug = strtolower(trim((string) $category));
                $amount = (float) $charge;

                $priceList = LabPriceList::firstOrCreate(
                    ['payer_category' => $categorySlug],
                    ['name' => ucfirst($categorySlug).' Price List', 'is_active' => true]
                );

                LabPriceListItem::updateOrCreate(
                    [
                        'price_list_id' => $priceList->id,
                        'service_id' => $service->id,
                    ],
                    [
                        'charge' => $amount,
                    ]
                );
            }

            return $service;
        });
    }
}
