<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Lab\Models\LabOrder;
use App\Domain\Lab\Models\LabOrderItem;
use App\Domain\Lab\Models\LabParameter;
use App\Domain\Lab\Models\LabReport;
use App\Domain\Lab\Models\LabResultValue;
use App\Domain\Lab\Services\AccessionGenerator;
use App\Domain\Lab\Services\FlagCalculator;
use App\Domain\Lab\States\LabState;
use App\Models\Legacy\LabInvestigation;
use App\Models\Legacy\PatientGenBill;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillLabResultsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lab:backfill-results {--limit=100 : Maximum number of investigations to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill legacy test_results JSON from lab_investigations into lab_orders and lab_result_values';

    /**
     * Execute the console command.
     */
    public function handle(AccessionGenerator $accessionGenerator, FlagCalculator $flagCalculator): int
    {
        $limit = (int) $this->option('limit');
        $this->info("Starting backfill for legacy lab results (limit: {$limit})...");

        $investigations = LabInvestigation::whereNotNull('test_results')
            ->where('test_results', '!=', '')
            ->where('test_results', '!=', '[]')
            ->where('test_results', '!=', '{}')
            ->limit($limit)
            ->get();

        $processedCount = 0;
        $valuesCount = 0;

        foreach ($investigations as $inv) {
            $genBill = PatientGenBill::with(['billing.visit.patient'])->find($inv->lab_order_id);
            if (! $genBill || ! $genBill->billing) {
                continue;
            }

            $billing = $genBill->billing;
            $visit = $billing->visit;
            $patient = $visit ? $visit->patient : null;

            if (! $patient) {
                continue;
            }

            DB::transaction(function () use (
                $inv,
                $genBill,
                $billing,
                $visit,
                $patient,
                $accessionGenerator,
                $flagCalculator,
                &$processedCount,
                &$valuesCount
            ) {
                // Find or create LabOrder for this bill
                $order = LabOrder::firstOrCreate(
                    [
                        'bill_id' => $billing->id,
                        'patient_id' => $patient->id,
                    ],
                    [
                        'visit_id' => $visit ? $visit->id : null,
                        'accession_no' => $accessionGenerator->generate(),
                        'priority' => 'routine',
                        'status' => match ($inv->status) {
                            'approved' => LabState::RELEASED,
                            'submitted' => LabState::VERIFIED,
                            'collected' => LabState::COLLECTED,
                            default => LabState::RESULTED,
                        },
                        'ordered_by' => $billing->created_by,
                        'branch_id' => 1,
                    ]
                );

                // Find or create LabOrderItem
                $orderItem = LabOrderItem::firstOrCreate(
                    [
                        'lab_order_id' => $order->id,
                        'gen_bill_id' => $genBill->id,
                    ],
                    [
                        'service_id' => $genBill->service_id,
                        'status' => $order->status,
                        'is_outsource' => (bool) $inv->is_outsource,
                    ]
                );

                $results = $inv->test_results;
                if (is_string($results)) {
                    $results = json_decode($results, true);
                }

                if (! is_array($results)) {
                    return;
                }

                // Process test_results structure
                // Can be {"GROUP_NAME": {"param_id": {"value": "...", ...}}} or flat {"param_id": {...}}
                foreach ($results as $key => $subGroup) {
                    if (is_array($subGroup)) {
                        foreach ($subGroup as $paramKey => $valData) {
                            if (is_array($valData) && isset($valData['value'])) {
                                $this->saveResultValue($orderItem, (string) $paramKey, $valData, $flagCalculator, $valuesCount);
                            }
                        }
                    }
                }

                if ($order->status === LabState::RELEASED) {
                    LabReport::firstOrCreate(
                        [
                            'lab_order_id' => $order->id,
                            'version' => 1,
                        ],
                        [
                            'template' => 'standard',
                            'verified_by' => $billing->created_by ?: 1,
                            'released_at' => $order->created_at ?? Carbon::now(),
                        ]
                    );
                }

                $processedCount++;
            });
        }

        $this->info("Completed: Backfilled {$processedCount} investigations with {$valuesCount} result values.");

        return Command::SUCCESS;
    }

    /**
     * Parse and persist single result value.
     */
    protected function saveResultValue(
        LabOrderItem $orderItem,
        string $paramKey,
        array $valData,
        FlagCalculator $flagCalculator,
        int &$valuesCount
    ): void {
        $rawVal = trim((string) ($valData['value'] ?? ''));
        $numericVal = is_numeric($rawVal) ? (float) $rawVal : null;

        // Try to find matching LabParameter by service_id or id
        $parameter = LabParameter::where('service_id', (int) $paramKey)->first()
            ?: LabParameter::where('id', (int) $paramKey)->first();

        if (! $parameter) {
            // Create fallback parameter under this order item's service
            $parameter = LabParameter::firstOrCreate(
                [
                    'service_id' => $orderItem->service_id,
                    'short_code' => 'P'.$paramKey,
                ],
                [
                    'name' => 'Parameter '.$paramKey,
                    'input_type' => $numericVal !== null ? 'numeric' : 'text',
                ]
            );
        }

        $refText = $valData['biological_reference_range'] ?? null;
        $refLow = null;
        $refHigh = null;

        if ($refText && str_contains($refText, '-')) {
            $parts = explode('-', $refText);
            if (isset($parts[0], $parts[1])) {
                $refLow = is_numeric(trim($parts[0])) ? (float) trim($parts[0]) : null;
                $refHigh = is_numeric(trim($parts[1])) ? (float) trim($parts[1]) : null;
            }
        }

        $flag = $flagCalculator->calculate($numericVal, $refLow, $refHigh, null, null, $rawVal);

        LabResultValue::updateOrCreate(
            [
                'order_item_id' => $orderItem->id,
                'parameter_id' => $parameter->id,
            ],
            [
                'value_text' => $rawVal,
                'value_numeric' => $numericVal,
                'unit' => $parameter->unit,
                'ref_low' => $refLow,
                'ref_high' => $refHigh,
                'ref_text' => $refText,
                'flag' => $flag,
                'entered_by' => 1,
                'entered_at' => Carbon::now(),
                'version' => 1,
            ]
        );

        $valuesCount++;
    }
}
