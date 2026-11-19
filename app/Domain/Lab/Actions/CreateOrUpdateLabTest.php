<?php

declare(strict_types=1);

namespace App\Domain\Lab\Actions;

use App\Domain\Lab\Models\LabParameter;
use App\Domain\Lab\Models\LabPriceList;
use App\Domain\Lab\Models\LabPriceListItem;
use App\Models\Legacy\HospitalService;
use App\Models\Legacy\HospitalServiceCharge;
use App\Models\Legacy\InvestigationGroupTestIndex;
use Illuminate\Support\Facades\DB;

class CreateOrUpdateLabTest
{
    /**
     * Execute test or test-group creation / update with strict dual-write.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?HospitalService $test = null): HospitalService
    {
        return DB::transaction(function () use ($data, $test) {
            $isCreate = $test === null;
            $service = $test ?? new HospitalService;

            $isGroup = isset($data['is_group']) ? (bool) $data['is_group'] : (bool) ($service->is_group ?? false);
            $groupAttributes = is_array($service->group_attributes) ? $service->group_attributes : [];

            $department = (string) ($data['department'] ?? ($groupAttributes['group'] ?? 'Clinical Pathology'));
            $tat = (int) ($data['tat'] ?? ($groupAttributes['tat'] ?? 60));
            $reportTime = (int) ($data['report_time'] ?? ($groupAttributes['report_time'] ?? 24));

            // Specimen and container payload
            $sampleData = $groupAttributes['sample'] ?? [];
            $specimenType = strtoupper((string) ($data['specimen_type'] ?? ($sampleData['sample_type'][0] ?? 'BLOOD')));
            $container = strtoupper((string) ($data['container'] ?? ($sampleData['vial'][0] ?? 'EDTA')));
            $quantity = (float) ($data['sample_quantity'] ?? ($sampleData['quantity'][0] ?? 2.0));
            $unit = (string) ($data['sample_unit'] ?? ($sampleData['unit'][0] ?? 'mL'));
            $storageInstruction = (string) ($data['storage_instruction'] ?? ($groupAttributes['sample_storage_instruction'] ?? '2 - 8°C'));

            $groupAttributes['group'] = $department;
            $groupAttributes['tat'] = $tat;
            $groupAttributes['report_time'] = $reportTime;
            $groupAttributes['sample'] = [
                'sample_type' => [$specimenType],
                'vial' => [$container],
                'quantity' => [$quantity],
                'unit' => [$unit],
            ];
            $groupAttributes['sample_storage_instruction'] = $storageInstruction;

            $shortCode = trim((string) ($data['short_code'] ?? ''));
            if ($shortCode === '') {
                $shortCode = $service->short_code ?: strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', (string) $data['name']) ?? 'TEST', 0, 8));
            }

            $service->service = (string) $data['name'];
            $service->short_code = $shortCode;
            $service->display_full_name = (string) ($data['display_name'] ?? ($service->display_full_name ?: $data['name']));
            $service->comment = (string) ($data['comment'] ?? ($service->comment ?? ''));
            $service->brief_des = (string) ($data['brief_des'] ?? ($service->brief_des ?? ''));
            $service->category_id = 9;
            $service->flag = $isGroup ? 'lab_package' : 'test';
            $service->sub_flag = 'lab';
            $service->is_group = $isGroup;
            $service->is_subgroup = false;
            $service->active = isset($data['active']) ? (int) $data['active'] : ($service->active ?? 1);
            $service->spec_id = 1; // Default department ID
            $service->loinc_code = (string) ($data['loinc_code'] ?? ($service->loinc_code ?? ''));
            $service->group_attributes = $groupAttributes;
            $service->save();

            // 1. Group components sync
            if ($isGroup && isset($data['component_ids']) && is_array($data['component_ids'])) {
                InvestigationGroupTestIndex::where('group_id', $service->id)->delete();
                $seq = 1;
                foreach ($data['component_ids'] as $childId) {
                    InvestigationGroupTestIndex::create([
                        'group_id' => $service->id,
                        'test_id' => (int) $childId,
                        'sequence_no' => $seq++,
                        'is_active' => 1,
                    ]);
                }
            }

            // 2. Dual-write to lab_parameters for single tests
            if (! $isGroup) {
                if (! empty($data['parameters']) && is_array($data['parameters'])) {
                    $pSeq = 1;
                    foreach ($data['parameters'] as $pItem) {
                        $pCode = ! empty($pItem['short_code']) ? (string) $pItem['short_code'] : $service->short_code.'_'.$pSeq;
                        LabParameter::updateOrCreate(
                            [
                                'service_id' => $service->id,
                                'short_code' => $pCode,
                            ],
                            [
                                'name' => (string) ($pItem['name'] ?? $service->service),
                                'unit' => (string) ($pItem['unit'] ?? $unit),
                                'method' => (string) ($pItem['method'] ?? 'Automated Analyzer'),
                                'loinc_code' => (string) ($pItem['loinc_code'] ?? $service->loinc_code),
                                'input_type' => (string) ($pItem['input_type'] ?? 'numeric'),
                                'decimal_places' => (int) ($pItem['decimal_places'] ?? 2),
                                'formula' => ! empty($pItem['formula']) ? (string) $pItem['formula'] : null,
                                'sort_order' => (int) ($pItem['sort_order'] ?? $pSeq),
                            ]
                        );
                        $pSeq++;
                    }
                } else {
                    LabParameter::firstOrCreate(
                        [
                            'service_id' => $service->id,
                            'short_code' => $service->short_code,
                        ],
                        [
                            'name' => $service->service,
                            'unit' => $unit,
                            'method' => 'Automated Analyzer',
                            'loinc_code' => $service->loinc_code,
                            'input_type' => 'numeric',
                            'decimal_places' => 2,
                            'sort_order' => 1,
                        ]
                    );
                }
            }

            // 3. Dual-write pricing
            $baseCharge = (float) ($data['base_charge'] ?? 100.0);
            if ($baseCharge >= 0) {
                // Legacy hospital_service_charges
                HospitalServiceCharge::updateOrCreate(
                    [
                        'hospital_id' => 1,
                        'service' => $service->id,
                    ],
                    [
                        'charges' => $baseCharge,
                        'is_active' => 1,
                        'is_editable' => 1,
                        'opd' => $baseCharge,
                        'er_opd' => $baseCharge,
                        'location_1' => $baseCharge,
                    ]
                );

                // Domain lab_price_lists
                $generalList = LabPriceList::firstOrCreate(
                    ['payer_category' => 'general'],
                    ['name' => 'General Payer Price List', 'is_active' => true]
                );

                LabPriceListItem::updateOrCreate(
                    [
                        'price_list_id' => $generalList->id,
                        'service_id' => $service->id,
                    ],
                    [
                        'charge' => $baseCharge,
                    ]
                );
            }

            return $service;
        });
    }
}
