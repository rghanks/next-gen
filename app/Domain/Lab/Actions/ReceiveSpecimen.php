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

class ReceiveSpecimen
{
    /**
     * Mark specimen as received at lab bench with condition.
     */
    public function execute(string|int $identifier, int $userId, string $condition = 'normal'): Specimen
    {
        return DB::transaction(function () use ($identifier, $userId, $condition) {
            $specimen = Specimen::with('order.items')->where(function ($q) use ($identifier) {
                if (is_numeric($identifier)) {
                    $q->where('id', (int) $identifier)->orWhere('barcode', (string) $identifier);
                } else {
                    $q->where('barcode', (string) $identifier);
                }
            })->first();

            if (! $specimen) {
                throw ValidationException::withMessages([
                    'barcode' => ["Specimen with barcode/ID '{$identifier}' was not found."],
                ]);
            }

            $order = $specimen->order;
            $fromState = $order->status->value;

            // If not collected yet, auto-collect as part of receiving
            if ($order->status === LabState::ORDERED) {
                $specimen->collected_at = Carbon::now();
                $specimen->collected_by = $userId;
            }

            $order->status->transitionTo(LabState::RECEIVED, 'Specimen received at lab bench.');

            $specimen->received_at = Carbon::now();
            $specimen->received_by = $userId;
            $specimen->condition = $condition;
            $specimen->save();

            $order->status = LabState::RECEIVED;
            $order->save();

            foreach ($order->items as $item) {
                $item->status = LabState::RECEIVED;
                $item->save();

                // Dual-write: update legacy lab_investigations
                if ($item->gen_bill_id) {
                    LabInvestigation::where('lab_order_id', $item->gen_bill_id)->update([
                        'status' => 'collected',
                        'sample_condition' => $condition,
                        'in_process' => 1,
                        'in_process_time' => Carbon::now(),
                    ]);
                }
            }

            // Audit log
            LabOrderEvent::create([
                'order_id' => $order->id,
                'from_state' => $fromState,
                'to_state' => LabState::RECEIVED->value,
                'user_id' => $userId,
                'meta' => [
                    'barcode' => $specimen->barcode,
                    'condition' => $condition,
                    'received_at' => Carbon::now()->toIso8601String(),
                ],
            ]);

            return $specimen;
        });
    }
}
