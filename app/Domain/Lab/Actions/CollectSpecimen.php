<?php

declare(strict_types=1);

namespace App\Domain\Lab\Actions;

use App\Domain\Lab\Models\LabOrderEvent;
use App\Domain\Lab\Models\Specimen;
use App\Domain\Lab\States\LabState;
use App\Models\Legacy\LabInvestigation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CollectSpecimen
{
    /**
     * Collect one or multiple specimens by barcode or ID.
     *
     * @param  string|int|list<string|int>  $identifiers
     */
    public function execute(string|int|array $identifiers, int $userId): int
    {
        $ids = is_array($identifiers) ? $identifiers : [$identifiers];
        $count = 0;

        DB::transaction(function () use ($ids, $userId, &$count) {
            foreach ($ids as $id) {
                $specimen = Specimen::with('order.items')->where(function ($q) use ($id) {
                    if (is_numeric($id)) {
                        $q->where('id', (int) $id)->orWhere('barcode', (string) $id);
                    } else {
                        $q->where('barcode', (string) $id);
                    }
                })->first();

                if (! $specimen) {
                    throw ValidationException::withMessages([
                        'barcode' => ["Specimen with barcode/ID '{$id}' was not found."],
                    ]);
                }

                $order = $specimen->order;
                $fromState = $order->status->value;

                // State transition validation
                $order->status->transitionTo(LabState::COLLECTED, 'Specimen collected by staff.');

                $specimen->collected_at = Carbon::now();
                $specimen->collected_by = $userId;
                $specimen->condition = 'normal';
                $specimen->save();

                $order->status = LabState::COLLECTED;
                $order->save();

                foreach ($order->items as $item) {
                    $item->status = LabState::COLLECTED;
                    $item->save();

                    // Dual-write: update legacy lab_investigations
                    if ($item->gen_bill_id) {
                        LabInvestigation::where('lab_order_id', $item->gen_bill_id)->update([
                            'status' => 'collected',
                            'sample_collect_time' => Carbon::now(),
                            'sample_condition' => 'normal',
                        ]);
                    }
                }

                // Audit log
                LabOrderEvent::create([
                    'order_id' => $order->id,
                    'from_state' => $fromState,
                    'to_state' => LabState::COLLECTED->value,
                    'user_id' => $userId,
                    'meta' => [
                        'barcode' => $specimen->barcode,
                        'collected_at' => Carbon::now()->toIso8601String(),
                    ],
                ]);

                $count++;
            }
        });

        return $count;
    }
}
