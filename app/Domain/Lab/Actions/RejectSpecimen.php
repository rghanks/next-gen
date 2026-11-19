<?php

declare(strict_types=1);

namespace App\Domain\Lab\Actions;

use App\Domain\Lab\Models\LabOrderEvent;
use App\Domain\Lab\Models\Specimen;
use App\Domain\Lab\States\LabState;
use App\Models\Legacy\LabInvestigation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectSpecimen
{
    /**
     * Reject specimen with reason, returning order and items to ORDERED for recollection.
     */
    public function execute(string|int $identifier, int $userId, string $reason): Specimen
    {
        return DB::transaction(function () use ($identifier, $userId, $reason) {
            $reason = trim($reason);
            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => ['A rejection reason is required (e.g. hemolyzed, insufficient, clotted).'],
                ]);
            }

            $specimen = Specimen::with('order.items')->where(function ($q) use ($identifier) {
                if (is_numeric($identifier)) {
                    $q->where('id', (int) $identifier)->orWhere('barcode', (string) $identifier);
                } else {
                    $q->where('barcode', (string) $identifier);
                }
            })->first();

            if (! $specimen) {
                throw ValidationException::withMessages([
                    'barcode' => ["Specimen '{$identifier}' not found."],
                ]);
            }

            $order = $specimen->order;
            $fromState = $order->status->value;

            // State machine: reject returns to ORDERED for recollection
            $order->status->transitionTo(LabState::ORDERED, 'Specimen rejected: '.$reason);

            $specimen->condition = 'rejected';
            $specimen->reject_reason = $reason;
            $specimen->collected_at = null;
            $specimen->received_at = null;
            $specimen->save();

            $order->status = LabState::ORDERED;
            $order->save();

            foreach ($order->items as $item) {
                $item->status = LabState::ORDERED;
                $item->save();

                // Dual-write: update legacy lab_investigations
                if ($item->gen_bill_id) {
                    LabInvestigation::where('lab_order_id', $item->gen_bill_id)->update([
                        'status' => 'rejected',
                        'sample_remark' => 'Rejected: '.$reason,
                    ]);
                }
            }

            // Audit log
            LabOrderEvent::create([
                'order_id' => $order->id,
                'from_state' => $fromState,
                'to_state' => LabState::ORDERED->value,
                'user_id' => $userId,
                'meta' => [
                    'barcode' => $specimen->barcode,
                    'reject_reason' => $reason,
                ],
            ]);

            return $specimen;
        });
    }
}
