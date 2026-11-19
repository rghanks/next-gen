<?php

declare(strict_types=1);

namespace App\Domain\Lab\Actions;

use App\Domain\Lab\Events\LabBillCancelled;
use App\Domain\Lab\Exceptions\InvalidStateTransitionException;
use App\Domain\Lab\Models\LabBillCancellation;
use App\Domain\Lab\Models\LabOrder;
use App\Domain\Lab\Models\LabOrderEvent;
use App\Domain\Lab\Services\AccessionGenerator;
use App\Domain\Lab\States\LabState;
use App\Models\Legacy\BillingPaymentType;
use App\Models\Legacy\LabInvestigation;
use App\Models\Legacy\PatientBilling;
use App\Models\Legacy\PatientGenBill;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelLabBill
{
    public function __construct(
        protected AccessionGenerator $accessionGenerator
    ) {}

    /**
     * Cancel an active lab bill and order with a credit note.
     *
     * @param array{
     *     order_id: int,
     *     reason: string,
     *     approved_by: int,
     *     refund_mode?: string
     * } $data
     */
    public function execute(array $data): LabBillCancellation
    {
        return DB::transaction(function () use ($data) {
            $order = LabOrder::with(['bill', 'items'])->lockForUpdate()->findOrFail($data['order_id']);

            // Enforce state machine rule: cancelled only before resulted
            if (! in_array($order->status, [LabState::ORDERED, LabState::COLLECTED, LabState::RECEIVED, LabState::IN_PROCESS], true)) {
                throw new InvalidStateTransitionException(
                    $order->status->value,
                    LabState::CANCELLED->value,
                    'A lab bill or order cannot be cancelled once results are entered or verified.'
                );
            }

            $fromState = $order->status->value;
            $approverId = $data['approved_by'];
            $reason = trim($data['reason']);

            if (empty($reason)) {
                throw ValidationException::withMessages([
                    'reason' => ['A valid cancellation reason is mandatory.'],
                ]);
            }

            // Calculate total refundable amount from settled payments
            $totalPaid = (float) BillingPaymentType::where('bill_id', $order->bill_id)
                ->where('is_deleted', 0)
                ->sum('amount');

            // Generate unique Credit Note number
            $todayKey = Carbon::now()->format('Ymd');
            $creditNoteNo = 'CN-'.$todayKey.'-'.sprintf('%05d', rand(1, 99999));

            // Create cancellation & credit note record
            $cancellation = LabBillCancellation::create([
                'bill_id' => $order->bill_id,
                'order_id' => $order->id,
                'credit_note_no' => $creditNoteNo,
                'reason' => $reason,
                'approved_by' => $approverId,
                'refund_mode' => $data['refund_mode'] ?? 'cash',
                'refund_amount' => $totalPaid,
                'cancelled_at' => Carbon::now(),
            ]);

            // 1. Update Domain Models
            $order->status = LabState::CANCELLED;
            $order->save();

            foreach ($order->items as $item) {
                $item->status = LabState::CANCELLED;
                $item->save();

                // 2. Dual-write: update legacy patient_gen_bill & lab_investigations
                if ($item->gen_bill_id) {
                    PatientGenBill::where('id', $item->gen_bill_id)->update([
                        'service_status' => 'cancelled',
                        'is_cancelled' => 1,
                        'cancelled_at' => Carbon::now(),
                        'cancelled_by' => $approverId,
                    ]);

                    LabInvestigation::where('lab_order_id', $item->gen_bill_id)->update([
                        'status' => 'cancelled',
                        'cancelled_at' => Carbon::now(),
                        'cancelled_by' => $approverId,
                        'cancelled_reason' => $reason,
                    ]);
                }
            }

            // 3. Dual-write: update legacy patient_billing
            if ($order->bill_id) {
                PatientBilling::where('id', $order->bill_id)->update([
                    'is_cancelled' => 1,
                    'status' => 0,
                    'remarks' => 'Cancelled: '.$reason,
                    'updated_by' => $approverId,
                ]);
            }

            // 4. Audit log
            LabOrderEvent::create([
                'order_id' => $order->id,
                'from_state' => $fromState,
                'to_state' => LabState::CANCELLED->value,
                'user_id' => $approverId,
                'meta' => [
                    'credit_note_no' => $creditNoteNo,
                    'reason' => $reason,
                    'refund_amount' => $totalPaid,
                ],
            ]);

            // 5. Dispatch event after commit
            DB::afterCommit(function () use ($order, $cancellation) {
                if ($order->bill) {
                    event(new LabBillCancelled($order, $order->bill, $cancellation));
                }
            });

            return $cancellation;
        });
    }
}
