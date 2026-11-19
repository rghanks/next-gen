<?php

declare(strict_types=1);

namespace App\Domain\Lab\Actions;

use App\Domain\Lab\Exceptions\OptimisticLockException;
use App\Domain\Lab\Models\LabOrder;
use App\Domain\Lab\Models\LabOrderEvent;
use App\Domain\Lab\Models\LabOrderItem;
use App\Domain\Lab\Models\LabParameter;
use App\Domain\Lab\Models\LabResultValue;
use App\Domain\Lab\Services\DeltaChecker;
use App\Domain\Lab\Services\FlagCalculator;
use App\Domain\Lab\Services\FormulaEvaluator;
use App\Domain\Lab\Services\PriceResolver;
use App\Domain\Lab\Services\ReferenceRangeResolver;
use App\Domain\Lab\States\LabState;
use App\Models\Legacy\LabInvestigation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SaveResults
{
    public function __construct(
        protected ReferenceRangeResolver $rangeResolver,
        protected FormulaEvaluator $formulaEvaluator,
        protected FlagCalculator $flagCalculator,
        protected DeltaChecker $deltaChecker,
        protected PriceResolver $priceResolver
    ) {}

    /**
     * Save parameter results for an order item atomically with formulas, snapshotting, and dual-write.
     *
     * @param array{
     *     order_item_id: int,
     *     expected_row_version: int,
     *     results: list<array{parameter_id: int, value: string|float|null}>,
     *     entered_by: int
     * } $data
     * @return array{order_item: LabOrderItem, delta_alerts: list<string>, critical_count: int}
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $orderItem = LabOrderItem::with(['order.patient', 'service'])
                ->lockForUpdate()
                ->findOrFail($data['order_item_id']);

            /** @var LabOrder $order */
            $order = $orderItem->order;

            // 1. Optimistic Locking check
            if ($orderItem->row_version !== (int) $data['expected_row_version']) {
                throw new OptimisticLockException(
                    "Order item #{$orderItem->id} was modified by another user (current version: {$orderItem->row_version}, expected: {$data['expected_row_version']})."
                );
            }

            $patient = $order->patient;
            $ageInDays = $patient ? $this->rangeResolver->calculateAgeInDays((int) $patient->age) : 365 * 30;
            $sex = $patient ? (string) $patient->sex : 'both';
            $enteredBy = $data['entered_by'];

            // 2. Build map of direct input values
            $rawValues = [];
            $numericValues = [];
            foreach ($data['results'] as $r) {
                $pid = (int) $r['parameter_id'];
                $valStr = trim((string) ($r['value'] ?? ''));
                if (is_numeric($valStr) && str_contains($valStr, '.')) {
                    $cleaned = rtrim(rtrim($valStr, '0'), '.');
                    if ($cleaned !== '') {
                        $valStr = $cleaned;
                    }
                }
                $rawValues[$pid] = $valStr;
                if (is_numeric($valStr)) {
                    $numericValues[$pid] = (float) $valStr;
                }
            }

            // 3. Load all parameters for this service and any expanded group component services
            $componentServices = $this->priceResolver->expandComponents($orderItem->service_id);
            $serviceIds = array_map(fn ($s) => $s->id, $componentServices);
            if (! in_array($orderItem->service_id, $serviceIds, true)) {
                $serviceIds[] = $orderItem->service_id;
            }

            $parameters = LabParameter::whereIn('service_id', $serviceIds)
                ->orderBy('sort_order')
                ->get();

            if ($parameters->isEmpty()) {
                foreach ($componentServices as $cs) {
                    $p = LabParameter::firstOrCreate(
                        ['service_id' => $cs->id, 'short_code' => $cs->short_code ?: 'VAL_'.$cs->id],
                        ['name' => $cs->service, 'input_type' => 'numeric']
                    );
                    $parameters->push($p);
                }
            }

            // Prepare variable context for ExpressionLanguage (e.g. param_152, UREA, SCREATENINE)
            $formulaVariables = [];
            foreach ($parameters as $p) {
                $v = $numericValues[$p->id] ?? null;
                $formulaVariables['param_'.$p->id] = $v;
                $formulaVariables['p'.$p->id] = $v;
                if ($p->short_code) {
                    $code = preg_replace('/[^a-zA-Z0-9_]/', '_', $p->short_code);
                    $formulaVariables[$code] = $v;
                    $formulaVariables[strtoupper($code)] = $v;
                    $formulaVariables[strtolower($code)] = $v;
                }
                if ($p->name) {
                    $cleanName = preg_replace('/[^a-zA-Z0-9_]/', '_', $p->name);
                    $formulaVariables[$cleanName] = $v;
                    $formulaVariables[strtoupper($cleanName)] = $v;
                    $formulaVariables[strtolower($cleanName)] = $v;
                }
            }

            // 4. Compute formulas if formula exists and direct value wasn't entered
            // Run multi-pass (up to 3 passes) for cascading formulas (e.g. Urea -> BUN -> BUN/Creatinine)
            for ($pass = 0; $pass < 3; $pass++) {
                $newComputed = false;
                foreach ($parameters as $p) {
                    if ($p->formula && ! isset($numericValues[$p->id])) {
                        $computed = $this->formulaEvaluator->evaluate($p->formula, $formulaVariables, (int) ($p->decimal_places ?? 2));
                        if ($computed !== null) {
                            $numericValues[$p->id] = $computed;
                            $computedStr = (string) $computed;
                            if (str_contains($computedStr, '.')) {
                                $cleaned = rtrim(rtrim($computedStr, '0'), '.');
                                $computedStr = $cleaned !== '' ? $cleaned : '0';
                            }
                            $rawValues[$p->id] = $computedStr;

                            // update variable pool for downstream formulas
                            $formulaVariables['param_'.$p->id] = $computed;
                            $formulaVariables['p'.$p->id] = $computed;
                            if ($p->short_code) {
                                $code = preg_replace('/[^a-zA-Z0-9_]/', '_', $p->short_code);
                                $formulaVariables[$code] = $computed;
                                $formulaVariables[strtoupper($code)] = $computed;
                                $formulaVariables[strtolower($code)] = $computed;
                            }
                            if ($p->name) {
                                $cleanName = preg_replace('/[^a-zA-Z0-9_]/', '_', $p->name);
                                $formulaVariables[$cleanName] = $computed;
                                $formulaVariables[strtoupper($cleanName)] = $computed;
                                $formulaVariables[strtolower($cleanName)] = $computed;
                            }
                            $newComputed = true;
                        }
                    }
                }
                if (! $newComputed) {
                    break;
                }
            }

            // 5. Persist LabResultValues with range snapshotting & flag calculations
            $deltaAlerts = [];
            $criticalCount = 0;
            $legacyResultsJson = [];
            $serviceName = $orderItem->service?->service ?: 'TEST';

            foreach ($parameters as $p) {
                if (! array_key_exists($p->id, $rawValues) && ! array_key_exists($p->id, $numericValues)) {
                    continue;
                }

                $textVal = $rawValues[$p->id] ?? null;
                $numVal = $numericValues[$p->id] ?? null;

                // Resolve age & sex reference range
                $range = $this->rangeResolver->resolve($p, $ageInDays, $sex);

                // Compute Flag (N, L, H, LL, HH, A)
                $flag = $this->flagCalculator->calculate(
                    $numVal,
                    $range['low'],
                    $range['high'],
                    $range['critical_low'],
                    $range['critical_high'],
                    $textVal
                );

                if (in_array($flag, ['LL', 'HH'], true)) {
                    $criticalCount++;
                }

                // Check historical delta
                if ($patient && $numVal !== null) {
                    $delta = $this->deltaChecker->check($patient->id, $p->id, $numVal, $orderItem->id);
                    if ($delta['is_significant']) {
                        $deltaAlerts[] = "{$p->name}: Shift of {$delta['percent_delta']}% from previous value ({$delta['previous_value']}) on {$delta['previous_date']}.";
                    }
                }

                // Save snapshotted result value
                LabResultValue::updateOrCreate(
                    [
                        'order_item_id' => $orderItem->id,
                        'parameter_id' => $p->id,
                    ],
                    [
                        'value_text' => $textVal,
                        'value_numeric' => $numVal,
                        'unit' => $p->unit,
                        'ref_low' => $range['low'],
                        'ref_high' => $range['high'],
                        'ref_text' => $range['text_range'],
                        'flag' => $flag,
                        'entered_by' => $enteredBy,
                        'entered_at' => Carbon::now(),
                        'version' => $orderItem->row_version + 1,
                    ]
                );

                // Format for legacy lab_investigations.test_results JSON
                $legacyResultsJson[(string) $p->id] = [
                    'value' => $textVal ?? ($numVal !== null ? (string) (0 + $numVal) : ''),
                    'biological_reference_range' => $range['text_range'] ?? '',
                    'biological_reference_critical_range' => ($range['critical_low'] && $range['critical_high'])
                        ? "{$range['critical_low']} - {$range['critical_high']}"
                        : null,
                ];
            }

            // 6. Update row version and transition status
            $fromState = $orderItem->status->value;
            $orderItem->row_version += 1;

            $hasAnyValue = false;
            foreach ($data['results'] as $r) {
                if (trim((string) ($r['value'] ?? '')) !== '') {
                    $hasAnyValue = true;
                    break;
                }
            }

            if ($hasAnyValue) {
                $orderItem->status = LabState::RESULTED;
            } elseif ($orderItem->status !== LabState::RESULTED) {
                $orderItem->status = LabState::IN_PROCESS;
            }
            $orderItem->save();

            // Check if all items in order are resulted
            $allResulted = LabOrderItem::where('lab_order_id', $order->id)
                ->where('status', '!=', LabState::RESULTED->value)
                ->count() === 0;
            if ($allResulted && $hasAnyValue) {
                $order->status = LabState::RESULTED;
            } else {
                $order->status = LabState::IN_PROCESS;
            }
            $order->save();

            // 7. Dual-write: update legacy lab_investigations
            if ($orderItem->gen_bill_id) {
                $wrappedJson = [
                    $serviceName => $legacyResultsJson,
                ];

                LabInvestigation::where('lab_order_id', $orderItem->gen_bill_id)->update([
                    'status' => 'submitted',
                    'reporting_time' => Carbon::now(),
                    'test_results' => json_encode($wrappedJson),
                ]);
            }

            // 8. Audit event log
            LabOrderEvent::create([
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'from_state' => $fromState,
                'to_state' => LabState::RESULTED->value,
                'user_id' => $enteredBy,
                'meta' => [
                    'results_count' => count($data['results']),
                    'critical_count' => $criticalCount,
                    'new_version' => $orderItem->row_version,
                ],
            ]);

            return [
                'order_item' => $orderItem->fresh(['resultValues.parameter']),
                'delta_alerts' => $deltaAlerts,
                'critical_count' => $criticalCount,
            ];
        });
    }
}
