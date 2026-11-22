<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lab;

use App\Domain\Lab\Actions\CollectSpecimen;
use App\Domain\Lab\Actions\ReceiveSpecimen;
use App\Domain\Lab\Actions\VerifyResults;
use App\Domain\Lab\Models\LabBillSequence;
use App\Domain\Lab\Models\LabOrder;
use App\Domain\Lab\Models\LabOrderEvent;
use App\Domain\Lab\Models\Specimen;
use App\Domain\Lab\Services\AccessionGenerator;
use App\Domain\Lab\Services\ReportRenderer;
use App\Domain\Lab\States\LabState;
use App\Http\Controllers\Controller;
use App\Models\LabSetting;
use App\Models\Legacy\HospitalPrintSetting;
use App\Models\Legacy\HospitalService;
use App\Models\Legacy\LabInvestigation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LabOrderDeskController extends Controller
{
    public function __construct(
        protected CollectSpecimen $collectSpecimen,
        protected ReceiveSpecimen $receiveSpecimen,
        protected ReportRenderer $reportRenderer,
        protected VerifyResults $verifyResults
    ) {}

    /**
     * Unified Lab Test Orders & Worklist Desk.
     * Combines Filters, Sample Collection, Real-Time Status, and Result Entry.
     */
    public function index(Request $request): View
    {
        $status = $request->input('status', 'all');
        $source = $request->input('source', 'all'); // all, inhouse, outsource
        $serviceId = $request->input('service_id');
        $search = trim((string) $request->input('q', ''));
        $uhid = trim((string) $request->input('uhid', ''));
        $billId = trim((string) $request->input('bill_id', ''));
        $patientName = trim((string) $request->input('name', ''));
        $mobileNo = trim((string) $request->input('mobile', ''));
        $dateFrom = $request->input('date_from', Carbon::today()->toDateString());
        $dateTo = $request->input('date_to', Carbon::today()->toDateString());

        // Base query for orders with all required relations
        $query = LabOrder::with([
            'patient',
            'specimens.collectedByUser',
            'specimens.receivedByUser',
            'items.service',
            'items.legacyGenBill',
            'items.resultValues.parameter',
            'bill.payments',
        ])->latest('id');

        // Date range filter
        if (! empty($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if (! empty($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Specific Field Filters (Matching Exact Desktop Layout)
        if ($uhid !== '') {
            $query->whereHas('patient', function ($p) use ($uhid) {
                $p->where('uhid', 'like', "%{$uhid}%")->orWhere('id', (int) $uhid);
            });
        }
        if ($billId !== '') {
            $query->where(function ($q) use ($billId) {
                $q->where('bill_id', (int) $billId)
                    ->orWhereHas('bill', function ($b) use ($billId) {
                        $b->where('bill_number', 'like', "%{$billId}%");
                    });
            });
        }
        if ($patientName !== '') {
            $query->whereHas('patient', function ($p) use ($patientName) {
                $p->where('Fname', 'like', "%{$patientName}%")
                    ->orWhere('Lname', 'like', "%{$patientName}%");
            });
        }
        if ($mobileNo !== '') {
            $query->whereHas('patient', function ($p) use ($mobileNo) {
                $p->where('mobile_no', 'like', "%{$mobileNo}%");
            });
        }

        // Status Lifecycle Filter
        if ($status === 'sample_pending') {
            $query->where('status', LabState::ORDERED->value);
        } elseif ($status === 'collected' || $status === 'report_pending') {
            $query->whereIn('status', [LabState::COLLECTED->value, LabState::RECEIVED->value]);
        } elseif ($status === 'in_process') {
            $query->where('status', LabState::IN_PROCESS->value);
        } elseif ($status === 'resulted' || $status === 'approval_pending') {
            $query->where('status', LabState::RESULTED->value);
        } elseif ($status === 'completed') {
            $query->whereIn('status', [LabState::VERIFIED->value, LabState::RELEASED->value]);
        }

        // Source Filter (In-house vs Outsourced)
        if ($source === 'inhouse') {
            $query->whereDoesntHave('items.service', function ($q) {
                $q->where('service', 'like', '%outsource%')
                    ->orWhere('service', 'like', '%pus culture%')
                    ->orWhere('service', 'like', '%sputum for afb%');
            });
        } elseif ($source === 'outsource') {
            $query->whereHas('items.service', function ($q) {
                $q->where('service', 'like', '%outsource%')
                    ->orWhere('service', 'like', '%pus culture%')
                    ->orWhere('service', 'like', '%sputum for afb%');
            });
        }

        // Service Filter
        if (! empty($serviceId)) {
            $query->whereHas('items', function ($q) use ($serviceId) {
                $q->where('service_id', (int) $serviceId);
            });
        }

        // Global Search Term (UHID, Name, Mobile, Accession, Bill No)
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('accession_no', 'like', "%{$search}%")
                    ->orWhereHas('patient', function ($p) use ($search) {
                        $p->where('Fname', 'like', "%{$search}%")
                            ->orWhere('Lname', 'like', "%{$search}%")
                            ->orWhere('uhid', 'like', "%{$search}%")
                            ->orWhere('mobile_no', 'like', "%{$search}%");
                    })
                    ->orWhereHas('bill', function ($b) use ($search) {
                        $b->where('bill_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items.service', function ($s) use ($search) {
                        $s->where('service', 'like', "%{$search}%");
                    });
            });
        }

        // Real-time Status Counters for the selected date range
        $countsQuery = LabOrder::query();
        if (! empty($dateFrom)) {
            $countsQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if (! empty($dateTo)) {
            $countsQuery->whereDate('created_at', '<=', $dateTo);
        }

        $allOrdersForCounts = (clone $countsQuery)->select('id', 'status')->get();
        $counts = [
            'all' => $allOrdersForCounts->count(),
            'sample_pending' => $allOrdersForCounts->where('status.value', LabState::ORDERED->value)->count(),
            'collected' => $allOrdersForCounts->whereIn('status.value', [LabState::COLLECTED->value, LabState::RECEIVED->value])->count(),
            'in_process' => $allOrdersForCounts->where('status.value', LabState::IN_PROCESS->value)->count(),
            'resulted' => $allOrdersForCounts->where('status.value', LabState::RESULTED->value)->count(),
            'completed' => $allOrdersForCounts->whereIn('status.value', [LabState::VERIFIED->value, LabState::RELEASED->value])->count(),
        ];

        // Active Lab Services for filter dropdown
        $services = HospitalService::whereIn('flag', ['test', 'lab_package'])
            ->where('active', 1)
            ->orderBy('service')
            ->get(['id', 'service', 'short_code']);

        $orders = $query->paginate(25)->withQueryString();

        return view('lab.orders.index', [
            'orders' => $orders,
            'counts' => $counts,
            'services' => $services,
            'status' => $status,
            'source' => $source,
            'serviceId' => $serviceId,
            'search' => $search,
            'uhid' => $uhid,
            'billId' => $billId,
            'patientName' => $patientName,
            'mobileNo' => $mobileNo,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'nextSampleBarcode' => app(AccessionGenerator::class)->previewNextBarcode(),
        ]);
    }

    /**
     * 1-Click Asynchronous Sample Collection.
     * Transitions order state, sets collection time & dual-writes to legacy tables.
     */
    public function collectSample(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => ['nullable', 'integer', 'exists:lab_orders,id'],
            'specimen_id' => ['nullable', 'integer'],
            'barcode' => ['nullable', 'string'],
            'custom_sample_no' => ['nullable', 'string', 'max:50'],
        ]);

        $orderId = $request->input('order_id');
        $barcode = $request->input('barcode');
        $specimenId = $request->input('specimen_id');
        $customSampleNo = $request->input('custom_sample_no') ? trim($request->input('custom_sample_no')) : null;

        $order = null;
        if ($orderId) {
            $order = LabOrder::with('specimens')->findOrFail($orderId);
        }

        // If custom sample number provided, assign it and restart the series
        if ($customSampleNo) {
            $accessionGen = app(AccessionGenerator::class);
            $targetSpecimen = null;
            if ($order && $order->specimens->isNotEmpty()) {
                $targetSpecimen = $order->specimens->first();
            } elseif ($specimenId) {
                $targetSpecimen = Specimen::find($specimenId);
            }

            $newBarcode = $accessionGen->generateBarcode('blood', null, $customSampleNo);
            if ($targetSpecimen) {
                $targetSpecimen->barcode = $newBarcode;
                $targetSpecimen->save();
            }
            $targetBarcode = $newBarcode;
        } else {
            $targetBarcode = $barcode;
            if (! $targetBarcode && $order && $order->specimens->isNotEmpty()) {
                $targetBarcode = $order->specimens->first()->barcode;
            } elseif (! $targetBarcode && $specimenId) {
                $specimen = Specimen::find($specimenId);
                $targetBarcode = $specimen?->barcode;
            }
        }

        if (! $targetBarcode) {
            return response()->json([
                'success' => false,
                'message' => 'No specimen found for collection.',
            ], 422);
        }

        $userId = $request->user() ? (int) $request->user()->id : 1;

        // 1. Collect
        $this->collectSpecimen->execute($targetBarcode, $userId);

        // 2. Auto-receive so technician can enter results immediately
        $this->receiveSpecimen->execute($targetBarcode, $userId, 'normal');

        $updatedSpecimen = Specimen::where('barcode', $targetBarcode)->first();
        $updatedOrder = $updatedSpecimen?->order;

        return response()->json([
            'success' => true,
            'message' => "Sample collected & received successfully for Accession {$updatedOrder?->accession_no}!",
            'barcode' => $targetBarcode,
            'collected_at' => $updatedSpecimen?->collected_at ? Carbon::parse($updatedSpecimen->collected_at)->format('d M, h:i A') : 'Just now',
            'order_status' => $updatedOrder?->status->value ?? 'received',
            'order_status_label' => $updatedOrder?->status->label() ?? 'Sample Collected',
            'badge_class' => $updatedOrder?->status->badgeClass() ?? 'badge-status-paid',
            'barcode_url' => $updatedOrder ? route('lab.billing.barcode', $updatedOrder->id) : null,
            'result_entry_url' => $updatedOrder ? route('lab.results.entry', $updatedOrder->id) : null,
            'next_sample_no' => app(AccessionGenerator::class)->previewNextBarcode(),
        ]);
    }

    /**
     * Update sample number for an order and restart series from there.
     */
    public function updateSampleNumber(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:lab_orders,id'],
            'custom_sample_no' => ['required', 'string', 'max:50'],
            'start_series' => ['nullable', 'boolean'],
        ]);

        $order = LabOrder::with('specimens')->findOrFail((int) $validated['order_id']);
        $firstSpecimen = $order->specimens->first();
        if (! $firstSpecimen) {
            return response()->json([
                'success' => false,
                'message' => 'No specimen found for this order.',
            ], 422);
        }

        $accessionGen = app(AccessionGenerator::class);
        $setting = LabSetting::current();
        $customInput = trim($validated['custom_sample_no']);
        $parsed = $accessionGen->parseBarcode($customInput, $setting);
        $newBarcode = $parsed['barcode'];

        // Check if barcode belongs to another specimen
        $conflict = Specimen::where('barcode', $newBarcode)
            ->where('id', '!=', $firstSpecimen->id)
            ->exists();

        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => "Sample number '{$newBarcode}' is already assigned to another specimen.",
            ], 422);
        }

        $oldBarcode = $firstSpecimen->barcode;
        $firstSpecimen->barcode = $newBarcode;
        $firstSpecimen->save();

        $startSeries = $request->boolean('start_series', true);
        if ($startSeries) {
            $prefix = $parsed['prefix'] ?: trim((string) ($setting?->sample_no_prefix ?? 'SMP'));
            $cleanPrefix = rtrim($prefix, '-');
            if ($cleanPrefix === '') {
                $cleanPrefix = 'SMP';
            }
            $resetPeriod = (string) ($setting?->sample_no_reset_period ?? 'continuous');
            $fy = $resetPeriod === 'daily' ? Carbon::now()->format('Ymd') : 'GLOBAL';

            $seq = LabBillSequence::where('prefix', $cleanPrefix)
                ->where('financial_year', $fy)
                ->first();
            if ($seq) {
                $seq->last_number = $parsed['number'];
                $seq->save();
            } else {
                LabBillSequence::create([
                    'prefix' => $cleanPrefix,
                    'financial_year' => $fy,
                    'last_number' => $parsed['number'],
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Sample number updated to '{$newBarcode}'" . ($startSeries ? " and series restarted from {$parsed['number']}!" : "!"),
            'barcode' => $newBarcode,
            'old_barcode' => $oldBarcode,
            'next_sample_no' => $accessionGen->previewNextBarcode(),
            'barcode_url' => route('lab.billing.barcode', $order->id),
        ]);
    }

    /**
     * Set the next starting number for the sample series.
     */
    public function setSampleSeries(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'next_number' => ['required', 'integer', 'min:1'],
            'prefix' => ['nullable', 'string', 'max:20'],
        ]);

        $nextNumber = (int) $validated['next_number'];
        $prefix = isset($validated['prefix']) ? trim($validated['prefix']) : null;

        $accessionGen = app(AccessionGenerator::class);
        $accessionGen->setNextSampleNumber($nextNumber, $prefix);

        if ($prefix !== null && $prefix !== '') {
            $setting = LabSetting::current();
            $setting->sample_no_prefix = $prefix;
            $setting->save();
        }

        $nextPreview = $accessionGen->previewNextBarcode();

        return response()->json([
            'success' => true,
            'message' => "Sample series started from {$nextNumber}! Next sample will be {$nextPreview}.",
            'next_sample_no' => $nextPreview,
        ]);
    }

    /**
     * Get preview of the next sample number in series.
     */
    public function getNextSampleNumber(): JsonResponse
    {
        $accessionGen = app(AccessionGenerator::class);
        return response()->json([
            'success' => true,
            'next_sample_no' => $accessionGen->previewNextBarcode(),
        ]);
    }

    /**
     * 1-Click Asynchronous In-Place Report Approval & Release.
     */
    public function approveOrder(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => ['required', 'integer', 'exists:lab_orders,id'],
        ]);

        $orderId = (int) $request->input('order_id');
        $order = LabOrder::with('items')->findOrFail($orderId);
        $user = $request->user();
        $userId = $user ? (int) $user->id : 1;

        // Transition through valid intermediate states if order is not yet at resulted
        if ($order->status === LabState::ORDERED) {
            $order->status = LabState::COLLECTED;
            $order->save();
        }
        if ($order->status === LabState::COLLECTED) {
            $order->status = LabState::RECEIVED;
            $order->save();
        }
        if ($order->status === LabState::RECEIVED) {
            $order->status = LabState::IN_PROCESS;
            $order->save();
        }
        if ($order->status === LabState::IN_PROCESS) {
            $order->status = LabState::RESULTED;
            $order->save();
        }

        // Execute verified release
        $updatedOrder = $this->verifyResults->execute($orderId, $userId, true);

        return response()->json([
            'success' => true,
            'message' => "Order #{$updatedOrder->accession_no} approved and report released successfully!",
            'order_id' => $updatedOrder->id,
            'accession_no' => $updatedOrder->accession_no,
            'status' => 'completed',
            'status_label' => 'Completed',
            'approval_time' => Carbon::now()->format('d/m/y h:i A'),
            'reporting_time' => Carbon::now()->format('d/m/y h:i A'),
            'report_url' => route('lab.reports.view', $updatedOrder->id),
            'pdf_url' => route('lab.reports.pdf', $updatedOrder->id),
            'print_pdf_url' => route('lab.reports.print', $updatedOrder->id),
            'result_entry_url' => route('lab.results.entry', $updatedOrder->id),
        ]);
    }

    /**
     * 1-Click Asynchronous In-Place Report Rejection / Disapproval.
     */
    public function rejectOrder(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => ['required', 'integer', 'exists:lab_orders,id'],
            'remarks' => ['nullable', 'string'],
        ]);

        $orderId = (int) $request->input('order_id');
        $order = LabOrder::with('items')->findOrFail($orderId);
        $user = $request->user();
        $userId = $user ? (int) $user->id : 1;
        $remarks = trim((string) $request->input('remarks', 'Disapproved by pathologist'));

        $fromState = $order->status->value;
        $order->status = LabState::IN_PROCESS;
        $order->save();

        foreach ($order->items()->get() as $item) {
            $item->status = LabState::IN_PROCESS;
            $item->save();

            if ($item->gen_bill_id) {
                LabInvestigation::where('lab_order_id', $item->gen_bill_id)->update([
                    'status' => 'submitted',
                    'approval_status' => 0,
                    'approval_time' => null,
                    'approved_by' => null,
                ]);
            }
        }

        LabOrderEvent::create([
            'order_id' => $order->id,
            'from_state' => $fromState,
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
            'message' => "Order #{$order->accession_no} disapproved and returned to In-Process for review.",
            'order_id' => $order->id,
            'accession_no' => $order->accession_no,
            'status' => 'in_process',
            'status_label' => 'In-Process',
            'result_entry_url' => route('lab.results.entry', $order->id),
            'report_url' => route('lab.reports.view', $order->id),
        ]);
    }
}
