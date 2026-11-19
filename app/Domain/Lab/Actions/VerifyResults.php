<?php

declare(strict_types=1);

namespace App\Domain\Lab\Actions;

use App\Domain\Lab\Models\LabOrder;
use App\Domain\Lab\Models\LabOrderEvent;
use App\Domain\Lab\Services\CriticalValueChecker;
use App\Domain\Lab\States\LabState;
use App\Models\Legacy\LabInvestigation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifyResults
{
    public function __construct(
        protected CriticalValueChecker $criticalChecker,
        protected ReleaseReport $releaseReport
    ) {}

    /**
     * Pathologist final verification and report release.
     */
    public function execute(int $orderId, int $pathologistId, bool $autoRelease = true): LabOrder
    {
        return DB::transaction(function () use ($orderId, $pathologistId, $autoRelease) {
            $order = LabOrder::with(['items.resultValues.criticalAcknowledgement'])->lockForUpdate()->findOrFail($orderId);

            if ($order->status !== LabState::VERIFIED) {
                if ($order->status->canTransitionTo(LabState::VERIFIED)) {
                    $order->status->transitionTo(LabState::VERIFIED, 'Pathologist verification review.');
                } else {
                    $order->status = LabState::VERIFIED;
                }
            }
            $fromState = $order->status->value;

            // Auto-acknowledge any critical panic values upon verification without blocking
            foreach ($order->items as $item) {
                foreach ($item->resultValues as $val) {
                    if ($this->criticalChecker->isCritical((string) $val->flag)) {
                        if (! $val->criticalAcknowledgement()->exists()) {
                            \App\Domain\Lab\Models\LabCriticalAcknowledgement::create([
                                'result_value_id' => $val->id,
                                'informed_person' => 'Verified by Pathologist',
                                'informed_by' => $pathologistId,
                                'informed_at' => Carbon::now(),
                                'remarks' => 'Auto-acknowledged during pathologist report verification/approval.',
                            ]);
                        }
                    }
                }
            }

            $order->status = LabState::VERIFIED;
            $order->save();

            foreach ($order->items as $item) {
                $item->status = LabState::VERIFIED;
                $item->save();

                // Dual-write: update legacy lab_investigations
                if ($item->gen_bill_id) {
                    LabInvestigation::where('lab_order_id', $item->gen_bill_id)->update([
                        'status' => 'approved',
                        'approval_status' => 1,
                        'approval_time' => Carbon::now(),
                        'approved_by' => $pathologistId,
                    ]);
                }
            }

            LabOrderEvent::create([
                'order_id' => $order->id,
                'from_state' => $fromState,
                'to_state' => LabState::VERIFIED->value,
                'user_id' => $pathologistId,
                'meta' => [
                    'step' => 'pathologist_verification',
                    'verified_at' => Carbon::now()->toIso8601String(),
                ],
            ]);

            if ($autoRelease) {
                $this->releaseReport->execute($order->id, $pathologistId);
            }

            return $order->fresh(['reports', 'items.service']);
        });
    }
}
