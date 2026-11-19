<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Lab\Models\LabParameter;
use App\Domain\Lab\Models\LabReferenceRange;
use App\Models\Legacy\HospitalService;
use Illuminate\Database\Seeder;

class LabParameterMigrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = HospitalService::whereIn('flag', ['test', 'lab_package'])->get();

        foreach ($services as $service) {
            $inputAttrs = $service->input_attributes;
            if (is_string($inputAttrs)) {
                $inputAttrs = json_decode($inputAttrs, true);
            }

            if (! is_array($inputAttrs)) {
                $inputAttrs = [];
            }

            $shortCode = $service->short_code ?: strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $service->service), 0, 8));
            if (! $shortCode) {
                $shortCode = 'TEST'.$service->id;
            }

            $unit = $inputAttrs['unit'] ?? null;
            if (is_numeric($unit)) {
                // If unit is an index or code, fallback to text representation
                $unit = 'units';
            }

            $inputType = 'numeric';
            if (isset($inputAttrs['input_type']) && in_array($inputAttrs['input_type'], ['text', 'options', 'culture'], true)) {
                $inputType = $inputAttrs['input_type'];
            }

            $formula = null;
            if (! empty($inputAttrs['formula']) && is_string($inputAttrs['formula'])) {
                $formula = $inputAttrs['formula'];
            }

            $parameter = LabParameter::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'short_code' => $shortCode,
                ],
                [
                    'name' => $service->service,
                    'unit' => $unit,
                    'method' => $service->comment ?: 'Automated Analyzer',
                    'loinc_code' => $service->loinc_code,
                    'decimal_places' => 2,
                    'input_type' => $inputType,
                    'formula' => $formula,
                    'sort_order' => 1,
                ]
            );

            // Parse biological reference ranges
            if (! empty($inputAttrs['biological_reference']) && is_array($inputAttrs['biological_reference'])) {
                $bioRef = $inputAttrs['biological_reference'];

                foreach (['Male' => 'male', 'Female' => 'female'] as $genderKey => $genderVal) {
                    if (isset($bioRef[$genderKey]) && is_array($bioRef[$genderKey])) {
                        $genderData = $bioRef[$genderKey];

                        // Adult range: 18 to 120 years (6570 to 43800 days)
                        if (isset($genderData['Adult']) && is_array($genderData['Adult'])) {
                            $adult = $genderData['Adult'];
                            LabReferenceRange::updateOrCreate(
                                [
                                    'parameter_id' => $parameter->id,
                                    'sex' => $genderVal,
                                    'age_from_days' => 6570,
                                    'age_to_days' => 43800,
                                ],
                                [
                                    'low' => is_numeric($adult['min'] ?? null) ? (float) $adult['min'] : null,
                                    'high' => is_numeric($adult['max'] ?? null) ? (float) $adult['max'] : null,
                                    'critical_low' => is_numeric($adult['critical_lower'] ?? null) ? (float) $adult['critical_lower'] : null,
                                    'critical_high' => is_numeric($adult['critical_higher'] ?? null) ? (float) $adult['critical_higher'] : null,
                                    'text_range' => ($adult['min'] ?? '').' - '.($adult['max'] ?? ''),
                                ]
                            );
                        }

                        // Child range: 1 to 18 years (365 to 6569 days)
                        if (isset($genderData['Child']) && is_array($genderData['Child'])) {
                            $child = $genderData['Child'];
                            LabReferenceRange::updateOrCreate(
                                [
                                    'parameter_id' => $parameter->id,
                                    'sex' => $genderVal,
                                    'age_from_days' => 365,
                                    'age_to_days' => 6569,
                                ],
                                [
                                    'low' => is_numeric($child['min'] ?? null) ? (float) $child['min'] : null,
                                    'high' => is_numeric($child['max'] ?? null) ? (float) $child['max'] : null,
                                    'critical_low' => is_numeric($child['critical_lower'] ?? null) ? (float) $child['critical_lower'] : null,
                                    'critical_high' => is_numeric($child['critical_higher'] ?? null) ? (float) $child['critical_higher'] : null,
                                    'text_range' => ($child['min'] ?? '').' - '.($child['max'] ?? ''),
                                ]
                            );
                        }

                        // Infant / Newborn range: 0 to 364 days
                        if (isset($genderData['New Born']) && is_array($genderData['New Born'])) {
                            $newborn = $genderData['New Born'];
                            LabReferenceRange::updateOrCreate(
                                [
                                    'parameter_id' => $parameter->id,
                                    'sex' => $genderVal,
                                    'age_from_days' => 0,
                                    'age_to_days' => 364,
                                ],
                                [
                                    'low' => is_numeric($newborn['min'] ?? null) ? (float) $newborn['min'] : null,
                                    'high' => is_numeric($newborn['max'] ?? null) ? (float) $newborn['max'] : null,
                                    'critical_low' => is_numeric($newborn['critical_lower'] ?? null) ? (float) $newborn['critical_lower'] : null,
                                    'critical_high' => is_numeric($newborn['critical_higher'] ?? null) ? (float) $newborn['critical_higher'] : null,
                                    'text_range' => ($newborn['min'] ?? '').' - '.($newborn['max'] ?? ''),
                                ]
                            );
                        }
                    }
                }
            }
        }
    }
}
