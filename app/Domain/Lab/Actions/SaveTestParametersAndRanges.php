<?php

declare(strict_types=1);

namespace App\Domain\Lab\Actions;

use App\Domain\Lab\Models\LabParameter;
use App\Domain\Lab\Models\LabReferenceRange;
use App\Models\Legacy\HospitalService;
use Illuminate\Support\Facades\DB;

class SaveTestParametersAndRanges
{
    /**
     * Age bracket mapping to days for legacy compatibility.
     *
     * @var array<string, array{from: int, to: int, range_str: string}>
     */
    protected array $ageGroups = [
        'New Born' => ['from' => 0, 'to' => 30, 'range_str' => '<= 1m'],
        'Infant' => ['from' => 31, 'to' => 365, 'range_str' => '> 1m to 1y'],
        'Child' => ['from' => 366, 'to' => 4015, 'range_str' => '> 1y to 11y'],
        'Adult' => ['from' => 4016, 'to' => 43800, 'range_str' => '> 11y'],
    ];

    /**
     * Save parameters and reference ranges for a test with strict dual-write.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(HospitalService $service, array $data): HospitalService
    {
        return DB::transaction(function () use ($service, $data) {
            $paramId = $data['parameter_id'] ?? null;

            $shortCode = trim((string) ($data['short_code'] ?? $service->short_code));
            if ($shortCode === '') {
                $shortCode = 'PARAM'.$service->id;
            }

            $inputType = (string) ($data['input_type'] ?? 'numeric');
            $unit = (string) ($data['unit'] ?? '');
            $formula = ! empty($data['formula']) ? (string) $data['formula'] : null;
            $options = ! empty($data['options']) && is_array($data['options']) ? array_values(array_filter($data['options'])) : null;

            /** @var LabParameter $parameter */
            $parameter = LabParameter::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'short_code' => $shortCode,
                ],
                [
                    'name' => (string) ($data['name'] ?? $service->service),
                    'unit' => $unit,
                    'method' => (string) ($data['method'] ?? 'Automated Analyzer'),
                    'loinc_code' => (string) ($data['loinc_code'] ?? $service->loinc_code),
                    'decimal_places' => (int) ($data['decimal_places'] ?? 2),
                    'input_type' => $inputType,
                    'formula' => $formula,
                    'options' => $options,
                    'sort_order' => (int) ($data['sort_order'] ?? 1),
                ]
            );

            // Save reference ranges if provided
            $rangesData = $data['ranges'] ?? [];
            $legacyBioRef = [
                'Male' => [],
                'Female' => [],
            ];

            if (! empty($rangesData) && is_array($rangesData)) {
                LabReferenceRange::where('parameter_id', $parameter->id)->delete();

                foreach ($rangesData as $r) {
                    $sex = strtolower((string) ($r['sex'] ?? 'both'));
                    $ageFromDays = (int) ($r['age_from_days'] ?? 0);
                    $ageToDays = (int) ($r['age_to_days'] ?? 43800);
                    $low = isset($r['low']) && is_numeric($r['low']) ? (float) $r['low'] : null;
                    $high = isset($r['high']) && is_numeric($r['high']) ? (float) $r['high'] : null;
                    $critLow = isset($r['critical_low']) && is_numeric($r['critical_low']) ? (float) $r['critical_low'] : null;
                    $critHigh = isset($r['critical_high']) && is_numeric($r['critical_high']) ? (float) $r['critical_high'] : null;
                    $textRange = (string) ($r['text_range'] ?? '');

                    LabReferenceRange::create([
                        'parameter_id' => $parameter->id,
                        'sex' => $sex,
                        'age_from_days' => $ageFromDays,
                        'age_to_days' => $ageToDays,
                        'low' => $low,
                        'high' => $high,
                        'critical_low' => $critLow,
                        'critical_high' => $critHigh,
                        'text_range' => $textRange,
                    ]);

                    // Format into legacy biological_reference matrix
                    $genderKey = match ($sex) {
                        'male' => ['Male'],
                        'female' => ['Female'],
                        default => ['Male', 'Female'],
                    };

                    $bracketName = (string) ($r['group_name'] ?? 'Adult');

                    foreach ($genderKey as $gk) {
                        $legacyBioRef[$gk][$bracketName] = [
                            'min' => $low !== null ? (string) $low : '',
                            'max' => $high !== null ? (string) $high : '',
                            'critical_lower' => $critLow !== null ? (string) $critLow : '',
                            'critical_higher' => $critHigh !== null ? (string) $critHigh : '',
                        ];
                    }
                }
            }

            // Dual write to hospital_services.input_attributes
            $legacyInputAttrs = [
                'input_element' => match ($inputType) {
                    'options' => 'select',
                    'formula' => 'formula',
                    'text' => 'textarea',
                    default => 'input',
                },
                'input_type' => $inputType === 'numeric' ? 'number' : 'text',
                'unit' => $unit,
                'formula' => $formula,
                'options' => $options ?? [],
                'biological_reference' => $legacyBioRef,
                'biological_reference_range_text' => (string) ($data['text_range'] ?? ''),
            ];

            $service->input_attributes = $legacyInputAttrs;
            $service->save();

            return $service;
        });
    }
}
