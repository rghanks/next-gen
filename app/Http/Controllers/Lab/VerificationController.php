<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lab;

use App\Domain\Lab\Actions\ValidateResults;
use App\Domain\Lab\Actions\VerifyResults;
use App\Domain\Lab\Models\LabOrder;
use App\Domain\Lab\Models\LabOrderEvent;
use App\Domain\Lab\States\LabState;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function __construct(
        protected ValidateResults $validateResults,
        protected VerifyResults $verifyResults
    ) {}

    /**
     * Show Pathologist / Senior Technician Verification Queue.
     */
    public function index(Request $request): View
    {
        $priority = $request->input('priority');

        $query = LabOrder::with([
            'patient',
            'items.service',
            'items.legacyGenBill',
            'items.resultValues.parameter',
            'items.resultValues.criticalAcknowledgement',
        ])
            ->whereIn('status', [LabState::RESULTED->value, LabState::IN_PROCESS->value])
            ->latest('id');

        if ($priority) {
            $query->where('priority', $priority);
        }

        $orders = $query->paginate(25);

        return view('lab.verification.index', [
            'orders' => $orders,
            'priority' => $priority,
        ]);
    }

    /**
     * Technician validation batch.
     */
    public function validateBatch(Request $request): JsonResponse
    {
        $request->validate([
            'order_ids' => ['required', 'array'],
            'order_ids.*' => ['integer', 'exists:lab_orders,id'],
        ]);

        $user = $request->user();
        $userId = $user ? (int) $user->id : 1;
        $count = 0;

        foreach ($request->input('order_ids') as $oid) {
            $this->validateResults->execute((int) $oid, $userId);
            $count++;
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully validated {$count} order(s).",
        ]);
    }

    /**
     * Pathologist verification & release batch.
     */
    public function verifyBatch(Request $request): JsonResponse
    {
        $request->validate([
            'order_ids' => ['required', 'array'],
            'order_ids.*' => ['integer', 'exists:lab_orders,id'],
        ]);

        $user = $request->user();
        $pathologistId = $user ? (int) $user->id : 1;
        $count = 0;

        foreach ($request->input('order_ids') as $oid) {
            $this->verifyResults->execute((int) $oid, $pathologistId, true);
            $count++;
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully verified & released {$count} order(s).",
        ]);
    }

    /**
     * Pathologist reject back to technician with remarks.
     */
    public function rejectBack(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => ['required', 'integer', 'exists:lab_orders,id'],
            'remarks' => ['required', 'string', 'min:3'],
        ]);

        $order = LabOrder::with('items')->findOrFail((int) $request->input('order_id'));
        $user = $request->user();
        $userId = $user ? (int) $user->id : 1;
        $remarks = trim((string) $request->input('remarks'));

        $order->status = LabState::IN_PROCESS;
        $order->save();

        foreach ($order->items()->get() as $item) {
            $item->status = LabState::IN_PROCESS;
            $item->save();
        }

        LabOrderEvent::create([
            'order_id' => $order->id,
            'from_state' => LabState::RESULTED->value,
            'to_state' => LabState::IN_PROCESS->value,
            'user_id' => $userId,
            'meta' => [
                'action' => 'pathologist_rejected_back_to_tech',
                'remarks' => $remarks,
                'rejected_at' => Carbon::now()->toIso8601String(),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order returned to Lab Technician for re-testing / review.',
        ]);
    }
}
