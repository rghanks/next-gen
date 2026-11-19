<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lab;

use App\Domain\Lab\Actions\CollectSpecimen;
use App\Domain\Lab\Actions\ReceiveSpecimen;
use App\Domain\Lab\Actions\RejectSpecimen;
use App\Domain\Lab\Models\Specimen;
use App\Domain\Lab\States\LabState;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpecimenController extends Controller
{
    public function __construct(
        protected CollectSpecimen $collectSpecimen,
        protected ReceiveSpecimen $receiveSpecimen,
        protected RejectSpecimen $rejectSpecimen
    ) {}

    /**
     * Show Specimen Worklist.
     */
    public function index(Request $request): View
    {
        $tab = $request->input('tab', 'pending');

        $query = Specimen::with(['order.patient', 'order.items.service', 'collectedByUser', 'receivedByUser'])
            ->latest('id');

        if ($tab === 'pending') {
            $query->whereNull('collected_at')->whereHas('order', function ($q) {
                $q->where('status', LabState::ORDERED->value);
            });
        } elseif ($tab === 'collected') {
            $query->whereNotNull('collected_at')->whereNull('received_at');
        } elseif ($tab === 'received') {
            $query->whereNotNull('received_at')->where('condition', '!=', 'rejected');
        } elseif ($tab === 'rejected') {
            $query->where('condition', 'rejected');
        }

        $specimens = $query->paginate(25);

        return view('lab.specimens.index', [
            'tab' => $tab,
            'specimens' => $specimens,
        ]);
    }

    /**
     * Bulk collect or scanner collect.
     */
    public function collect(Request $request): JsonResponse
    {
        $request->validate([
            'barcodes' => ['required'],
        ]);

        $barcodes = $request->input('barcodes');
        if (is_string($barcodes)) {
            $barcodes = array_filter(array_map('trim', explode(',', $barcodes)));
        }

        $user = $request->user();
        $userId = $user ? (int) $user->id : 1;
        $count = $this->collectSpecimen->execute($barcodes, $userId);

        return response()->json([
            'success' => true,
            'message' => "Successfully collected {$count} specimen(s).",
        ]);
    }

    /**
     * Receive specimen with condition.
     */
    public function receive(Request $request): JsonResponse
    {
        $request->validate([
            'barcode' => ['required', 'string'],
            'condition' => ['nullable', 'string', 'in:normal,hemolyzed,lipemic,icteric,insufficient,clotted'],
        ]);

        $user = $request->user();
        $userId = $user ? (int) $user->id : 1;
        $condition = $request->input('condition', 'normal');

        $specimen = $this->receiveSpecimen->execute($request->input('barcode'), $userId, $condition);

        return response()->json([
            'success' => true,
            'message' => "Specimen {$specimen->barcode} marked as Received ({$condition}).",
        ]);
    }

    /**
     * Reject specimen with reason.
     */
    public function reject(Request $request): JsonResponse
    {
        $request->validate([
            'barcode' => ['required'],
            'reason' => ['required', 'string', 'min:3'],
        ]);

        $user = $request->user();
        $userId = $user ? (int) $user->id : 1;
        $specimen = $this->rejectSpecimen->execute($request->input('barcode'), $userId, $request->input('reason'));

        return response()->json([
            'success' => true,
            'message' => "Specimen {$specimen->barcode} rejected and returned to Pending Recollection.",
        ]);
    }

    /**
     * High-speed barcode scan lookup.
     */
    public function scanBarcode(Request $request): JsonResponse
    {
        $barcode = trim((string) $request->input('barcode', ''));
        $specimen = Specimen::with(['order.patient', 'order.items.service'])
            ->where('barcode', $barcode)
            ->first();

        if (! $specimen) {
            return response()->json(['success' => false, 'message' => 'Barcode not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'specimen' => [
                'id' => $specimen->id,
                'barcode' => $specimen->barcode,
                'type' => $specimen->specimen_type,
                'container' => $specimen->container,
                'condition' => $specimen->condition,
                'status' => $specimen->order->status->value,
                'patient_name' => $specimen->order->patient?->full_name,
                'uhid' => $specimen->order->patient?->uhid,
                'tests' => $specimen->order->items->map(fn ($i) => $i->service?->service),
            ],
        ]);
    }
}
