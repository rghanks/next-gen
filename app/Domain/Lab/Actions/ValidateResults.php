<?php

declare(strict_types=1);

namespace App\Domain\Lab\Actions;

use App\Domain\Lab\Models\LabOrder;
use App\Domain\Lab\Models\LabOrderEvent;
use App\Domain\Lab\States\LabState;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ValidateResults
{
    /**
     * Technician first-step sign-off on entered results.
     */
    public function execute(int $orderId, int $userId): LabOrder
    {
        return DB::transaction(function () use ($orderId, $userId) {
            $order = LabOrder::with(['items.resultValues'])->lockForUpdate()->findOrFail($orderId);

            if (! in_array($order->status, [LabState::RESULTED, LabState::IN_PROCESS], true)) {
                throw ValidationException::withMessages([
                    'order' => ['Only resulted orders can be validated by technician.'],
                ]);
            }

            $fromState = $order->status->value;

            // Check that at least one result value exists
            $totalResults = 0;
            foreach ($order->items as $item) {
                $totalResults += $item->resultValues->count();
            }

            if ($totalResults === 0) {
                throw ValidationException::withMessages([
                    'order' => ['Cannot validate an order with no entered result values.'],
                ]);
            }

            $order->status = LabState::RESULTED;
            $order->save();

            foreach ($order->items as $item) {
                $item->status = LabState::RESULTED;
                $item->save();
            }

            LabOrderEvent::create([
                'order_id' => $order->id,
                'from_state' => $fromState,
                'to_state' => LabState::RESULTED->value,
                'user_id' => $userId,
                'meta' => [
                    'step' => 'technician_validation',
                    'validated_at' => Carbon::now()->toIso8601String(),
                ],
            ]);

            return $order;
        });
    }
}
