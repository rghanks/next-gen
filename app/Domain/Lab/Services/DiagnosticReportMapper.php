<?php

declare(strict_types=1);

namespace App\Domain\Lab\Services;

use App\Domain\Lab\Models\LabOrder;
use App\Domain\Lab\Models\LabReport;

class DiagnosticReportMapper
{
    /**
     * Map a LabOrder and LabReport to HL7 FHIR R4 DiagnosticReport JSON structure.
     *
     * @return array<string, mixed>
     */
    public function toFhir(LabOrder $order, LabReport $report): array
    {
        $patient = $order->patient;
        $status = match (true) {
            $report->isAmended() => 'amended',
            $order->status->value === 'released' => 'final',
            $order->status->value === 'verified' => 'preliminary',
            default => 'registered',
        };

        // Observations (individual parameters)
        $containedObservations = [];
        $resultReferences = [];

        foreach ($order->items as $item) {
            foreach ($item->resultValues as $val) {
                $obsId = 'obs-'.$val->id;
                $p = $val->parameter;
                $loincCode = $p?->loinc_code ?: 'unknown';

                $obs = [
                    'resourceType' => 'Observation',
                    'id' => $obsId,
                    'status' => 'final',
                    'code' => [
                        'coding' => [
                            [
                                'system' => 'http://loinc.org',
                                'code' => $loincCode,
                                'display' => $p?->name,
                            ],
                        ],
                        'text' => $p?->name,
                    ],
                    'subject' => [
                        'reference' => 'Patient/'.($patient?->uhid ?: $patient?->id),
                        'display' => $patient?->full_name,
                    ],
                ];

                if ($val->value_numeric !== null) {
                    $obs['valueQuantity'] = [
                        'value' => (float) $val->value_numeric,
                        'unit' => $val->unit ?: 'units',
                        'system' => 'http://unitsofmeasure.org',
                    ];
                } else {
                    $obs['valueString'] = (string) $val->value_text;
                }

                // Reference Range
                if ($val->ref_low !== null || $val->ref_high !== null || $val->ref_text) {
                    $obs['referenceRange'] = [
                        [
                            'low' => $val->ref_low !== null ? ['value' => (float) $val->ref_low, 'unit' => $val->unit] : null,
                            'high' => $val->ref_high !== null ? ['value' => (float) $val->ref_high, 'unit' => $val->unit] : null,
                            'text' => $val->ref_text,
                        ],
                    ];
                }

                // Interpretation flag
                if ($val->flag && $val->flag !== 'N') {
                    $obs['interpretation'] = [
                        [
                            'coding' => [
                                [
                                    'system' => 'http://terminology.hl7.org/CodeSystem/v3-ObservationInterpretation',
                                    'code' => $val->flag,
                                ],
                            ],
                        ],
                    ];
                }

                $containedObservations[] = $obs;
                $resultReferences[] = [
                    'reference' => '#'.$obsId,
                    'display' => $p?->name,
                ];
            }
        }

        return [
            'resourceType' => 'DiagnosticReport',
            'id' => 'dr-'.$order->accession_no.'-v'.$report->version,
            'identifier' => [
                [
                    'system' => 'urn:hospital:lis:accession',
                    'value' => $order->accession_no,
                ],
                [
                    'system' => 'urn:hospital:lis:report-version',
                    'value' => (string) $report->version,
                ],
            ],
            'status' => $status,
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/v2-0074',
                            'code' => 'LAB',
                            'display' => 'Laboratory',
                        ],
                    ],
                ],
            ],
            'code' => [
                'coding' => [
                    [
                        'system' => 'http://loinc.org',
                        'code' => '11502-2',
                        'display' => 'Laboratory report',
                    ],
                ],
                'text' => 'Laboratory Diagnostic Report',
            ],
            'subject' => [
                'reference' => 'Patient/'.($patient?->uhid ?: $patient?->id),
                'display' => $patient?->full_name,
            ],
            'effectiveDateTime' => $order->specimens->first()?->collected_at?->toIso8601String() ?: $order->created_at?->toIso8601String(),
            'issued' => $report->released_at?->toIso8601String() ?: date('c'),
            'performer' => [
                [
                    'display' => $report->verifiedByUser ? $report->verifiedByUser->name : 'Pathologist',
                ],
            ],
            'result' => $resultReferences,
            'contained' => $containedObservations,
            'presentedForm' => [
                [
                    'contentType' => 'application/pdf',
                    'hash' => base64_encode((string) $report->pdf_sha256),
                    'title' => "Report_{$order->accession_no}_v{$report->version}.pdf",
                ],
            ],
        ];
    }
}
