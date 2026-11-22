<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lab;

use App\Domain\Lab\Actions\CancelLabBill;
use App\Domain\Lab\Actions\CreateLabBill;
use App\Domain\Lab\Models\LabOrder;
use App\Domain\Lab\Models\LabOrderItem;
use App\Domain\Lab\States\LabState;
use App\Domain\Lab\Services\AccessionGenerator;
use App\Domain\Lab\Services\PriceResolver;
use App\Domain\Lab\Services\ReportRenderer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\CancelBillRequest;
use App\Http\Requests\Lab\CreateBillRequest;
use App\Http\Resources\Lab\LabOrderResource;
use App\Models\Legacy\BillingPaymentType;
use App\Models\Legacy\HospitalPrintSetting;
use App\Models\Legacy\HospitalService;
use App\Models\Legacy\LabInvestigation;
use App\Models\Legacy\Patient;
use App\Models\Legacy\PatientBilling;
use App\Models\Legacy\PatientGenBill;
use App\Models\Legacy\ReferringDoctor;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function __construct(
        protected CreateLabBill $createLabBill,
        protected CancelLabBill $cancelLabBill,
        protected PriceResolver $priceResolver,
        protected ReportRenderer $reportRenderer,
        protected AccessionGenerator $accessionGenerator
    ) {}

    /**
     * Show the compact, keyboard-first Lab Billing Desk.
     */
    public function index(Request $request): View
    {
        $doctors = [
           
        ];

        // Fetch initial list of laboratory services (popular & common tests prioritized)
        // $popularIds = [];
        $popularOrder; //= implode(',', $popularIds);

        $services = HospitalService::query()
            ->whereIn('flag', ['test'])
            ->where('active', 1)
           // ->orderByRaw("CASE WHEN id IN ({$popularOrder}) THEN 0 ELSE 1 END")
            ->orderBy('service')
            ->limit(100)
            ->get();

        $priceMap = $this->priceResolver->resolveBatch($services, 'general');

        $initialServices = [];
        foreach ($services as $srv) {
            $isOutsource = str_contains(strtolower($srv->service), 'outsource')
                || str_contains(strtolower($srv->service), 'pus culture')
                || str_contains(strtolower($srv->service), 'sputum for afb');

            $initialServices[] = [
                'id' => $srv->id,
                'name' => $srv->service,
                'short_code' => $srv->short_code,
                'is_group' => (bool) $srv->is_group,
                'price' => $priceMap[$srv->id] ?? 0.00,
                'is_outsource' => $isOutsource,
            ];
        }

        // Default to clean blank slate for billing desk
        $preloadedPatient = null;

        $referrals = ReferringDoctor::where('is_active', true)->orderBy('name')->get();
        $todayVisitsCount = DB::table('patient_billing')
            ->whereBetween('created_at', [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()])
            ->count();
        $nextSampleBarcode = $this->accessionGenerator->previewNextBarcode();

        return view('lab.billing.index', [
            'doctors' => $doctors,
            'referrals' => $referrals,
            'todayVisitsCount' => $todayVisitsCount,
            'initialServices' => $initialServices,
            'preloadedPatient' => $preloadedPatient,
            'nextSampleBarcode' => $nextSampleBarcode,
        ]);
    }

    /**
     * Debounced, keyboard-selectable patient search.
     */
    public function searchPatients(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));
        if (strlen($term) < 1) {
            return response()->json([]);
        }

        $patients = Patient::query()
            ->where(function ($query) use ($term) {
                if (is_numeric($term)) {
                    $query->where('id', (int) $term)
                        ->orWhere('uhid', 'like', "%{$term}%")
                        ->orWhere('mobile_no', 'like', "%{$term}%");
                } else {
                    $query->where('Fname', 'like', "%{$term}%")
                        ->orWhere('Lname', 'like', "%{$term}%")
                        ->orWhere('uhid', 'like', "%{$term}%")
                        ->orWhere('mobile_no', 'like', "%{$term}%")
                        ->orWhere('abha_number', 'like', "%{$term}%")
                        ->orWhere('abha_address', 'like', "%{$term}%");
                }
            })
            ->when(is_numeric($term), function ($query) use ($term) {
                $query->orderByRaw("CASE WHEN id = ? THEN 0 WHEN uhid = ? THEN 1 ELSE 2 END", [(int) $term, $term]);
            })
            ->limit(12)
            ->get(['id', 'uhid', 'prefix', 'Fname', 'Lname', 'age', 'sex', 'mobile_no', 'referring_doctor', 'abha_number']);

        return response()->json($patients->map(function ($p) {
            $formattedAge = $p->age ? ($p->age . ' Yr (' . ($p->sex ?: 'Male') . ')') : 'N/A';
            return [
                'id' => $p->id,
                'uhid' => $p->uhid ?: $p->id,
                'name' => trim(($p->Fname ?? '') . ' ' . ($p->Lname ?? '')),
                'age' => $formattedAge,
                'sex' => $p->sex ?: 'Male',
                'mobile_no' => $p->mobile_no,
                'category' => 'Cash',
                'ref_by' => $p->referring_doctor ?: 'Self',
                'abha_number' => $p->abha_number,
            ];
        }));
    }

    /**
     * Debounced test & package search with component expansion and server pricing.
     */
    public function searchServices(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));
        $payerCategory = (string) $request->input('payer_category', 'general');

        $servicesQuery = HospitalService::query()
            ->where('active', 1)
            ->whereNotIn('flag', ['room', 'admission_charge', 'balance', 'pharmacy_charge', 'nursing_charges', 'registration_charge', 'consultation_charge']);

        if (strlen($term) >= 1) {
            $servicesQuery->where(function ($query) use ($term) {
                $query->where('service', 'like', "%{$term}%")
                    ->orWhere('short_code', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            })
            ->orderByRaw("CASE WHEN service LIKE ? THEN 0 WHEN short_code = ? THEN 1 ELSE 2 END", ["{$term}%", $term]);
        }

        $services = $servicesQuery->limit(35)->get();

        $results = [];
        foreach ($services as $srv) {
            $components = $this->priceResolver->expandComponents($srv->id);
            $price = $this->priceResolver->resolve($srv->id, $payerCategory);
            $isOutsource = str_contains(strtolower($srv->service), 'outsource')
                || str_contains(strtolower($srv->service), 'pus culture')
                || str_contains(strtolower($srv->service), 'sputum for afb');

            $results[] = [
                'id' => $srv->id,
                'name' => $srv->service,
                'short_code' => $srv->short_code,
                'is_group' => (bool) $srv->is_group,
                'price' => $price,
                'is_outsource' => $isOutsource,
                'components_count' => count($components),
                'component_names' => array_map(fn ($c) => $c->service, $components),
            ];
        }

        return response()->json($results);
    }

    /**
     * Today's patient visits with billing details, paid amounts, service counts, and referrals.
     */
    public function todayVisits(Request $request): JsonResponse
    {
        $date = $request->input('date', Carbon::today()->toDateString());

        $bills = DB::table('patient_billing as pb')
            ->leftJoin('visitlog as v', 'pb.visit_id', '=', 'v.id')
            ->leftJoin('pi as p', 'v.patient_id', '=', 'p.id')
            ->leftJoin('lab_orders as lo', 'lo.bill_id', '=', 'pb.id')
            ->leftJoin('specimens as sp', 'sp.lab_order_id', '=', 'lo.id')
            ->select(
                'pb.id as bill_id',
                'lo.id as order_id',
                'lo.accession_no',
                'sp.barcode as sample_no',
                'pb.bill_number',
                'pb.created_at',
                'pb.discount',
                'pb.discount_per',
                'p.id as patient_id',
                'p.uhid',
                'p.Fname',
                'p.Lname',
                'p.mobile_no',
                'v.ref_by',
                'p.referring_doctor'
            )
            ->whereDate('pb.created_at', $date)
            ->orderByDesc('pb.id')
            ->get();

        $billIds = $bills->pluck('bill_id')->toArray();

        // Aggregate service counts & total bill amounts
        $itemTotals = DB::table('patient_gen_bill')
            ->whereIn('bill_id', $billIds)
            ->where('is_deleted', 0)
            ->selectRaw('bill_id, COUNT(*) as count, SUM((charges * qty) - discount) as total_amount')
            ->groupBy('bill_id')
            ->get()
            ->keyBy('bill_id');

        // Aggregate payments
        $paymentTotals = DB::table('billing_payment_type')
            ->whereIn('bill_id', $billIds)
            ->where('is_deleted', 0)
            ->selectRaw('bill_id, SUM(amount) as total_paid')
            ->groupBy('bill_id')
            ->get()
            ->keyBy('bill_id');

        $visits = [];
        $totalBilledSum = 0;
        $totalPaidSum = 0;

        foreach ($bills as $b) {
            $itemInfo = $itemTotals->get($b->bill_id);
            $serviceCount = (int) ($itemInfo->count ?? 0);
            $billedAmount = (float) ($itemInfo->total_amount ?? 0);
            $billedAmount = max(0, $billedAmount - (float) ($b->discount ?? 0));

            $paidAmount = (float) ($paymentTotals->get($b->bill_id)->total_paid ?? 0);
            $dueAmount = max(0, round($billedAmount - $paidAmount, 2));

            $status = 'Unpaid';
            if ($billedAmount > 0) {
                if ($paidAmount >= $billedAmount) {
                    $status = 'Paid';
                } elseif ($paidAmount > 0) {
                    $status = 'Partial';
                }
            } else {
                $status = 'Paid';
            }

            $referral = trim((string) ($b->ref_by ?: ($b->referring_doctor ?: 'Self')));
            $patientName = trim(($b->Fname ?? '') . ' ' . ($b->Lname ?? ''));
            if ($patientName === '') {
                $patientName = 'Patient #' . ($b->uhid ?? $b->patient_id);
            }

            $timeFormatted = Carbon::parse($b->created_at)->format('h:i A');

            $targetOrderKey = $b->order_id ?: $b->bill_id;
            $receiptUrl = route('lab.billing.receipt', ['order' => $targetOrderKey]);

            $visits[] = [
                'bill_id' => $b->bill_id,
                'order_id' => $b->order_id,
                'accession_no' => $b->accession_no,
                'sample_no' => $b->sample_no ?: ($b->accession_no ?? '-'),
                'bill_number' => $b->bill_number,
                'receipt_url' => $receiptUrl,
                'patient_id' => $b->patient_id,
                'uhid' => $b->uhid ?? $b->patient_id,
                'patient_name' => $patientName,
                'mobile_no' => $b->mobile_no ? (string) $b->mobile_no : 'N/A',
                'ref_by' => $referral,
                'time' => $timeFormatted,
                'services_count' => $serviceCount,
                'billed_amount' => round($billedAmount, 2),
                'paid_amount' => round($paidAmount, 2),
                'due_amount' => $dueAmount,
                'status' => $status,
            ];

            $totalBilledSum += $billedAmount;
            $totalPaidSum += $paidAmount;
        }

        return response()->json([
            'count' => count($visits),
            'total_billed' => round($totalBilledSum, 2),
            'total_paid' => round($totalPaidSum, 2),
            'total_due' => round(max(0, $totalBilledSum - $totalPaidSum), 2),
            'visits' => $visits,
        ]);
    }

    /**
     * Store new referring doctor.
     */
    public function storeReferral(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'mobile_no' => ['nullable', 'string', 'max:25'],
            'degree' => ['nullable', 'string', 'max:100'],
        ]);

        $referral = ReferringDoctor::firstOrCreate(
            ['name' => trim($validated['name'])],
            [
                'mobile_no' => $validated['mobile_no'] ?? null,
                'degree' => $validated['degree'] ?? null,
                'is_active' => true,
            ]
        );

        return response()->json([
            'success' => true,
            'referral' => [
                'id' => $referral->id,
                'name' => $referral->name,
                'mobile_no' => $referral->mobile_no,
                'degree' => $referral->degree,
            ],
            'message' => 'Referring doctor added successfully',
        ]);
    }

    /**
     * Store new lab bill & order via CreateLabBill Action.
     */
    public function store(CreateBillRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $validated['user_id'] = $user ? (int) $user->id : 1;

        $order = $this->createLabBill->execute($validated);

        return response()->json([
            'success' => true,
            'message' => 'Lab bill created successfully.',
            'order' => new LabOrderResource($order),
            'receipt_url' => route('lab.billing.receipt', $order->id),
            'barcode_url' => route('lab.billing.barcode', $order->id),
            'sample_no' => $order->specimens->first()?->barcode ?? $order->accession_no,
            'next_sample_no' => $this->accessionGenerator->previewNextBarcode(),
        ]);
    }

    /**
     * Get preview of the next sample number in series.
     */
    public function nextSampleNumber(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'next_sample_no' => $this->accessionGenerator->previewNextBarcode(),
        ]);
    }

    /**
     * Cancel settled lab bill via CancelLabBill Action.
     */
    public function cancel(CancelBillRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $validated['approved_by'] = $user ? (int) $user->id : 1;

        $cancellation = $this->cancelLabBill->execute($validated);

        return response()->json([
            'success' => true,
            'message' => 'Lab bill cancelled successfully with Credit Note.',
            'credit_note_no' => $cancellation->credit_note_no,
            'refund_amount' => $cancellation->refund_amount,
        ]);
    }

    /**
     * Printable thermal receipt / compact A4 bill.
     */
    public function receipt(Request $request, mixed $order): View
    {
        if (! $order instanceof LabOrder) {
            $resolved = null;
            if (is_numeric($order)) {
                $resolved = LabOrder::find($order) ?? LabOrder::where('bill_id', (int) $order)->first();
            }
            if (! $resolved) {
                $resolved = LabOrder::where('accession_no', (string) $order)->first();
            }
            if (! $resolved) {
                abort(404, 'Bill receipt not found.');
            }
            $order = $resolved;
        }

        $order->load(['bill.payments', 'items.service', 'items.legacyGenBill', 'patient', 'specimens']);
        $printSetting = HospitalPrintSetting::where('hospital_id', $order->branch_id)->first();
        $barcodeUri = $this->reportRenderer->generateBarcodeUri($order->accession_no);

        return view('lab.billing.receipt', [
            'order' => $order,
            'printSetting' => $printSetting,
            'barcodeUri' => $barcodeUri,
        ]);
    }

    /**
     * Printable 1D barcode label for tube/specimen.
     */
    public function barcodeLabel(LabOrder $order): View
    {
        $order->load(['patient', 'items.service', 'items.legacyGenBill', 'specimens']);
        $specimensWithBarcodes = $order->specimens->map(function ($specimen) {
            return [
                'specimen' => $specimen,
                'barcode_uri' => $this->reportRenderer->generateBarcodeUri($specimen->barcode),
            ];
        });

        return view('lab.billing.barcode_label', [
            'order' => $order,
            'specimens' => $specimensWithBarcodes,
        ]);
    }

    /**
     * Comprehensive Bill Report and Daily Collection Summary.
     */
    public function dailySummary(Request $request): View
    {
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        // Default to current month if not provided
        if (! $fromDate) {
            $fromDate = Carbon::now()->startOfMonth()->toDateString();
        }
        if (! $toDate) {
            $toDate = Carbon::now()->toDateString();
        }

        $search = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', 'all');

        $baseQuery = DB::table('patient_billing as pb')
            ->leftJoin('visitlog as v', 'pb.visit_id', '=', 'v.id')
            ->leftJoin('pi as p', 'v.patient_id', '=', 'p.id')
            ->whereBetween(DB::raw('DATE(pb.created_at)'), [$fromDate, $toDate])
            ->where('pb.is_cancelled', 0);

        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('pb.bill_number', 'like', "%{$search}%")
                    ->orWhere('pb.id', $search)
                    ->orWhere('p.uhid', 'like', "%{$search}%")
                    ->orWhere('p.mobile_no', 'like', "%{$search}%")
                    ->orWhere('p.Fname', 'like', "%{$search}%")
                    ->orWhere('p.Lname', 'like', "%{$search}%")
                    ->orWhere(DB::raw("CONCAT(COALESCE(p.Fname,''), ' ', COALESCE(p.Lname,''))"), 'like', "%{$search}%");
            });
        }

        // Summary counts for filter tabs
        $countAll = (clone $baseQuery)->count('pb.id');
        $countPaid = (clone $baseQuery)->where('pb.is_settled', 1)->count('pb.id');
        $countDue = (clone $baseQuery)->where('pb.is_settled', 0)->count('pb.id');

        // Apply Status Filter to create filtered base
        $filteredBase = clone $baseQuery;
        if ($status === 'paid') {
            $filteredBase->where('pb.is_settled', 1);
        } elseif ($status === 'due') {
            $filteredBase->where('pb.is_settled', 0);
        }

        // Compute Financial KPIs on filtered bills
        $allFilteredBillIds = (clone $filteredBase)->select('pb.id')->pluck('id')->toArray();
        $totalBilledGross = 0.0;
        $totalCollected = 0.0;

        if (! empty($allFilteredBillIds)) {
            $totalBilledGross = (float) DB::table('patient_gen_bill')
                ->whereIn('bill_id', $allFilteredBillIds)
                ->where('is_deleted', 0)
                ->sum(DB::raw('(charges * qty) - discount'));

            $totalCollected = (float) DB::table('billing_payment_type')
                ->whereIn('bill_id', $allFilteredBillIds)
                ->where('is_deleted', 0)
                ->sum('amount');
        }

        $totalDiscounts = (float) (clone $filteredBase)->sum('pb.discount');
        $totalBilledNet = max(0, $totalBilledGross - $totalDiscounts);
        $totalDue = max(0, round($totalBilledNet - $totalCollected, 2));

        $bills = (clone $filteredBase)
            ->leftJoin('lab_orders as lo', 'lo.bill_id', '=', 'pb.id')
            ->select(
                'pb.id as bill_id',
                'lo.id as order_id',
                'lo.accession_no',
                'pb.bill_number',
                'pb.created_at',
                'pb.discount',
                'pb.discount_per',
                'pb.is_settled',
                'pb.is_cancelled',
                'p.id as patient_id',
                'p.uhid',
                'p.prefix',
                'p.Fname',
                'p.Lname',
                'p.age',
                'p.sex',
                'p.mobile_no',
                'v.ref_by',
                'p.referring_doctor'
            )
            ->orderByDesc('pb.id')
            ->paginate(25);
        $pageBillIds = collect($bills->items())->pluck('bill_id')->toArray();

        // Eager fetch billed services for current page
        $pageItems = DB::table('patient_gen_bill as pgb')
            ->leftJoin('hospital_services as hs', 'pgb.service_id', '=', 'hs.id')
            ->whereIn('pgb.bill_id', $pageBillIds)
            ->where('pgb.is_deleted', 0)
            ->select(
                'pgb.bill_id',
                'pgb.charges',
                'pgb.qty',
                'pgb.discount',
                'hs.service as service_name',
                'hs.short_code'
            )
            ->get()
            ->groupBy('bill_id');

        // Eager fetch payments for current page
        $pagePayments = DB::table('billing_payment_type')
            ->whereIn('bill_id', $pageBillIds)
            ->where('is_deleted', 0)
            ->select('bill_id', 'amount', 'payment_mode', 'created_at')
            ->get()
            ->groupBy('bill_id');

        // Referrals for bill editing
        $referrals = ReferringDoctor::where('is_active', true)->orderBy('name')->get();

        return view('lab.billing.daily_summary', [
            'bills' => $bills,
            'pageItems' => $pageItems,
            'pagePayments' => $pagePayments,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'search' => $search,
            'status' => $status,
            'countAll' => $countAll,
            'countPaid' => $countPaid,
            'countDue' => $countDue,
            'totalBilledNet' => $totalBilledNet,
            'totalCollected' => $totalCollected,
            'totalDue' => $totalDue,
            'referrals' => $referrals,
        ]);
    }

    /**
     * Get single bill details for the Edit Bill & Settlement modal.
     */
    public function billDetails(int|string $billId): JsonResponse
    {
        $billId = (int) $billId;
        $bill = PatientBilling::with([
            'visit.patient',
            'items.service',
            'payments' => fn ($q) => $q->where('is_deleted', 0)->orderBy('id'),
        ])->findOrFail($billId);

        $order = LabOrder::where('bill_id', $bill->id)->first();
        $patient = $bill->visit?->patient;

        // Active items
        $items = $bill->items->where('is_deleted', 0)->where('is_cancelled', 0)->map(function ($it) {
            $charges = (float) $it->charges;
            $qty = (float) ($it->qty ?: 1);
            $disc = (float) ($it->discount ?: 0);
            $net = max(0, ($charges * $qty) - $disc);

            return [
                'id' => $it->id,
                'service_id' => $it->service_id,
                'service_name' => $it->service?->service ?? ('Service #' . $it->service_id),
                'short_code' => $it->service?->short_code,
                'charges' => $charges,
                'qty' => $qty,
                'discount' => $disc,
                'net_amount' => $net,
                'service_status' => $it->service_status,
            ];
        })->values();

        // Cancelled / soft-deleted items
        $cancelledItems = $bill->items->filter(fn ($it) => $it->is_deleted == 1 || $it->is_cancelled == 1)->map(function ($it) {
            return [
                'id' => $it->id,
                'service_id' => $it->service_id,
                'service_name' => $it->service?->service ?? ('Service #' . $it->service_id),
                'short_code' => $it->service?->short_code,
                'charges' => (float) $it->charges,
                'cancelled_at' => $it->cancelled_at ? Carbon::parse($it->cancelled_at)->format('d/m/Y h:i A') : '-',
            ];
        })->values();

        $gross = $items->sum('net_amount');
        $billDiscount = (float) ($bill->discount ?? 0);
        $net = max(0, $gross - $billDiscount);
        $paid = (float) $bill->payments->sum('amount');
        $due = max(0, round($net - $paid, 2));

        $payments = $bill->payments->map(function ($p) {
            return [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'payment_mode' => strtolower($p->payment_mode ?? 'cash'),
                'payment_mode_label' => strtoupper($p->payment_mode ?? 'cash'),
                'transaction_id' => $p->transaction_id,
                'remarks' => $p->remarks,
                'date' => Carbon::parse($p->payment_date ?: $p->created_at)->format('d/m/Y h:i A'),
            ];
        })->values();

        $doctorName = $bill->visit?->ref_by ?: ($patient?->referring_doctor ?: 'Dr Gurchand');
        $referrals = ReferringDoctor::where('is_active', true)->orderBy('name')->pluck('name')->toArray();

        return response()->json([
            'success' => true,
            'bill' => [
                'id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'created_at' => $bill->created_at?->format('d/m/Y h:i A'),
                'is_settled' => (bool) $bill->is_settled,
                'order_id' => $order?->id,
                'accession_no' => $order?->accession_no,
                'sample_no' => $order?->specimens()->first()?->barcode ?? ($order?->accession_no ?? '-'),
                'receipt_url' => route('lab.billing.receipt', $order?->id ?: $bill->id),
                'barcode_url' => $order ? route('lab.billing.barcode', $order->id) : null,
            ],
            'patient' => [
                'id' => $patient?->id,
                'uhid' => $patient?->uhid ?: ($patient?->id ?: '-'),
                'name' => $patient?->full_name ?: trim(($patient?->Fname ?? '') . ' ' . ($patient?->Lname ?? '')),
                'mobile_no' => $patient?->mobile_no ?: '-',
                'age' => $patient?->age ? ($patient->age . ' Yr / ' . ($patient->sex ?: 'Male')) : '-',
            ],
            'referral_doctor' => $doctorName,
            'referrals' => $referrals,
            'items' => $items,
            'cancelled_items' => $cancelledItems,
            'payments' => $payments,
            'financials' => [
                'gross_amount' => round($gross, 2),
                'bill_discount' => round($billDiscount, 2),
                'net_amount' => round($net, 2),
                'total_paid' => round($paid, 2),
                'due_amount' => round($due, 2),
                'status' => $due <= 0 ? 'Paid' : ($paid > 0 ? 'Partial' : 'Due'),
            ],
        ]);
    }

    /**
     * Remove a single service item: marks test as cancelled and soft-deletes it.
     */
    public function removeItem(Request $request, int|string $billId, int|string $itemId): JsonResponse
    {
        $billId = (int) $billId;
        $itemId = (int) $itemId;
        $bill = PatientBilling::findOrFail($billId);
        $userId = $request->user()?->id ?: 1;
        $now = Carbon::now();

        DB::transaction(function () use ($bill, $itemId, $userId, $now) {
            PatientGenBill::where('id', $itemId)
                ->where('bill_id', $bill->id)
                ->update([
                    'service_status' => 'cancelled',
                    'is_cancelled' => 1,
                    'is_deleted' => 1,
                    'cancelled_at' => $now,
                    'cancelled_by' => $userId,
                    'updated_by' => $userId,
                ]);

            LabInvestigation::where('lab_order_id', $itemId)->update([
                'status' => 'cancelled',
                'cancelled_at' => $now,
                'cancelled_by' => $userId,
                'cancelled_reason' => 'Cancelled and soft-deleted via bill edit',
            ]);

            $order = LabOrder::where('bill_id', $bill->id)->first();
            if ($order) {
                LabOrderItem::where('gen_bill_id', $itemId)->update([
                    'status' => LabState::CANCELLED,
                ]);

                $activeCount = LabOrderItem::where('lab_order_id', $order->id)
                    ->where('status', '!=', LabState::CANCELLED)
                    ->count();
                if ($activeCount === 0) {
                    $order->status = LabState::CANCELLED;
                    $order->save();
                }
            }

            // Recalculate bill financials
            $grossBilled = (float) DB::table('patient_gen_bill')
                ->where('bill_id', $bill->id)
                ->where('is_deleted', 0)
                ->sum(DB::raw('(charges * qty) - discount'));

            $billDiscount = (float) ($bill->discount ?? 0);
            if ($billDiscount > $grossBilled) {
                $billDiscount = $grossBilled;
            }
            $netBilled = max(0, $grossBilled - $billDiscount);

            $totalPaid = (float) DB::table('billing_payment_type')
                ->where('bill_id', $bill->id)
                ->where('is_deleted', 0)
                ->sum('amount');

            $due = max(0, round($netBilled - $totalPaid, 2));

            $bill->update([
                'discount' => $billDiscount,
                'discount_per' => $grossBilled > 0 ? round(($billDiscount / $grossBilled) * 100, 4) : 0,
                'is_settled' => ($due <= 0.00 ? 1 : 0),
                'is_partially_settled' => ($due > 0 && $totalPaid > 0 ? 1 : 0),
                'updated_by' => $userId,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Service removed and marked as cancelled.',
        ]);
    }

    /**
     * Save complete bill edits: add services, remove services (mark cancelled + soft delete),
     * edit payments, update referral doctor, discount, and synchronize all ledger tables.
     */
    public function saveFullBill(Request $request, int|string $billId): JsonResponse
    {
        $billId = (int) $billId;
        $bill = PatientBilling::with(['visit.patient', 'items'])->findOrFail($billId);
        $userId = $request->user()?->id ?: 1;

        $validated = $request->validate([
            'referral_doctor' => ['nullable', 'string', 'max:150'],
            'bill_discount' => ['nullable', 'numeric', 'min:0'],
            'services' => ['nullable', 'array'],
            'services.*.id' => ['nullable', 'integer'],
            'services.*.service_id' => ['nullable', 'integer'],
            'services.*.service_name' => ['nullable', 'string', 'max:255'],
            'services.*.charges' => ['required', 'numeric', 'min:0'],
            'services.*.qty' => ['required', 'numeric', 'min:0.01'],
            'services.*.discount' => ['nullable', 'numeric', 'min:0'],
            'services.*.is_removed' => ['nullable', 'boolean'],
            'payments' => ['nullable', 'array'],
            'payments.*.id' => ['nullable', 'integer'],
            'payments.*.amount' => ['required', 'numeric', 'min:0'],
            'payments.*.payment_mode' => ['required', 'string'],
            'payments.*.transaction_id' => ['nullable', 'string', 'max:100'],
            'payments.*.is_deleted' => ['nullable', 'boolean'],
        ]);

        $order = LabOrder::where('bill_id', $bill->id)->first();

        DB::transaction(function () use ($bill, $order, $validated, $userId) {
            $now = Carbon::now();

            // 1. Process Services / Investigations
            $submittedServices = $validated['services'] ?? [];
            $existingGenBillIds = $bill->items->where('is_deleted', 0)->where('is_cancelled', 0)->pluck('id')->toArray();
            $processedExistingIds = [];

            foreach ($submittedServices as $srv) {
                $genBillId = ! empty($srv['id']) ? (int) $srv['id'] : null;
                $isRemoved = ! empty($srv['is_removed']);

                // Resolve target service ID and name
                $targetServiceId = ! empty($srv['service_id']) ? (int) $srv['service_id'] : null;
                $serviceName = ! empty($srv['service_name']) ? trim((string) $srv['service_name']) : null;

                if (! empty($serviceName)) {
                    if ($targetServiceId) {
                        $svc = HospitalService::find($targetServiceId);
                        if ($svc && $svc->service !== $serviceName) {
                            $svc->update(['service' => $serviceName]);
                        }
                    } else {
                        $svc = HospitalService::firstOrCreate(
                            ['service' => $serviceName],
                            ['active' => 1, 'is_group' => false]
                        );
                        $targetServiceId = $svc->id;
                    }
                }

                if ($genBillId) {
                    $processedExistingIds[] = $genBillId;
                    if ($isRemoved) {
                        // Mark test as cancelled and soft-delete
                        PatientGenBill::where('id', $genBillId)
                            ->where('bill_id', $bill->id)
                            ->update([
                                'service_status' => 'cancelled',
                                'is_cancelled' => 1,
                                'is_deleted' => 1,
                                'cancelled_at' => $now,
                                'cancelled_by' => $userId,
                                'updated_by' => $userId,
                            ]);

                        LabInvestigation::where('lab_order_id', $genBillId)->update([
                            'status' => 'cancelled',
                            'cancelled_at' => $now,
                            'cancelled_by' => $userId,
                            'cancelled_reason' => 'Cancelled and removed via bill edit',
                        ]);

                        if ($order) {
                            LabOrderItem::where('gen_bill_id', $genBillId)->update([
                                'status' => LabState::CANCELLED,
                            ]);
                        }
                    } else {
                        // Update service_id, charges, qty, discount
                        $updateData = [
                            'charges' => (float) $srv['charges'],
                            'qty' => (float) ($srv['qty'] ?? 1),
                            'discount' => (float) ($srv['discount'] ?? 0),
                            'updated_by' => $userId,
                        ];
                        if ($targetServiceId) {
                            $updateData['service_id'] = $targetServiceId;
                        }

                        PatientGenBill::where('id', $genBillId)
                            ->where('bill_id', $bill->id)
                            ->update($updateData);

                        if ($order && $targetServiceId) {
                            LabOrderItem::where('gen_bill_id', $genBillId)->update([
                                'service_id' => $targetServiceId,
                            ]);
                        }
                    }
                } elseif (! $isRemoved && ($targetServiceId || ! empty($srv['service_id']))) {
                    $effectiveServiceId = $targetServiceId ?: (int) $srv['service_id'];
                    // New service added to the bill
                    $newGenBill = PatientGenBill::create([
                        'bill_id' => $bill->id,
                        'service_id' => $effectiveServiceId,
                        'charges' => (float) $srv['charges'],
                        'qty' => (float) ($srv['qty'] ?? 1),
                        'discount' => (float) ($srv['discount'] ?? 0),
                        'discount_per' => 0.00,
                        'service_status' => 'booked',
                        'is_settled' => 1,
                        'i_cover' => 0,
                        'is_cancelled' => 0,
                        'is_deleted' => 0,
                        'doctor_id' => $bill->visit?->doctor_id ?? 1,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);

                    // Legacy lab investigation row
                    LabInvestigation::create([
                        'lab_order_id' => $newGenBill->id,
                        'status' => 'collected',
                        'approval_status' => 0,
                        'sample_type' => 'blood',
                        'sample_condition' => 'normal',
                        'is_outsource' => 0,
                        'is_printed' => 0,
                        'in_process' => 0,
                        'test_results' => json_encode([]),
                    ]);

                    // If lab order exists, attach new order item
                    if ($order) {
                        $orderItem = LabOrderItem::create([
                            'lab_order_id' => $order->id,
                            'service_id' => $effectiveServiceId,
                            'gen_bill_id' => $newGenBill->id,
                            'status' => LabState::ORDERED,
                            'tat_due_at' => Carbon::now()->addHours(4),
                            'row_version' => 1,
                        ]);

                        $specimen = $order->specimens()->first();
                        if ($specimen) {
                            $specimen->items()->attach($orderItem->id);
                        }

                        if ($order->status === LabState::CANCELLED) {
                            $order->status = LabState::ORDERED;
                            $order->save();
                        }
                    }
                }
            }

            // If any active items were omitted from the services array, mark them cancelled
            $omitted = array_diff($existingGenBillIds, $processedExistingIds);
            if (! empty($omitted) && ! empty($submittedServices)) {
                PatientGenBill::whereIn('id', $omitted)
                    ->where('bill_id', $bill->id)
                    ->update([
                        'service_status' => 'cancelled',
                        'is_cancelled' => 1,
                        'is_deleted' => 1,
                        'cancelled_at' => $now,
                        'cancelled_by' => $userId,
                        'updated_by' => $userId,
                    ]);

                LabInvestigation::whereIn('lab_order_id', $omitted)->update([
                    'status' => 'cancelled',
                    'cancelled_at' => $now,
                    'cancelled_by' => $userId,
                    'cancelled_reason' => 'Removed via bill edit',
                ]);

                if ($order) {
                    LabOrderItem::whereIn('gen_bill_id', $omitted)->update([
                        'status' => LabState::CANCELLED,
                    ]);
                }
            }

            // Check if all order items are cancelled
            if ($order) {
                $activeOrderItems = LabOrderItem::where('lab_order_id', $order->id)
                    ->where('status', '!=', LabState::CANCELLED)
                    ->count();
                if ($activeOrderItems === 0) {
                    $order->status = LabState::CANCELLED;
                    $order->save();
                }
            }

            // 2. Process Payments
            $submittedPayments = $validated['payments'] ?? [];
            foreach ($submittedPayments as $pay) {
                $payId = ! empty($pay['id']) ? (int) $pay['id'] : null;
                $isDel = ! empty($pay['is_deleted']);

                if ($payId) {
                    if ($isDel) {
                        BillingPaymentType::where('id', $payId)
                            ->where('bill_id', $bill->id)
                            ->update([
                                'is_deleted' => 1,
                                'updated_by' => $userId,
                            ]);
                    } else {
                        BillingPaymentType::where('id', $payId)
                            ->where('bill_id', $bill->id)
                            ->update([
                                'amount' => (float) $pay['amount'],
                                'payment_mode' => strtolower($pay['payment_mode']),
                                'transaction_id' => $pay['transaction_id'] ?? null,
                                'updated_by' => $userId,
                            ]);
                    }
                } elseif (! $isDel && (float) $pay['amount'] > 0) {
                    BillingPaymentType::create([
                        'bill_id' => $bill->id,
                        'amount' => (float) $pay['amount'],
                        'payment_mode' => strtolower($pay['payment_mode']),
                        'transaction_id' => $pay['transaction_id'] ?? null,
                        'payment_date' => $now,
                        'is_deleted' => 0,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }
            }

            // 3. Recalculate Totals & Status
            $grossBilled = (float) DB::table('patient_gen_bill')
                ->where('bill_id', $bill->id)
                ->where('is_deleted', 0)
                ->sum(DB::raw('(charges * qty) - discount'));

            $billDiscount = isset($validated['bill_discount']) ? (float) $validated['bill_discount'] : (float) ($bill->discount ?? 0);
            if ($billDiscount > $grossBilled) {
                $billDiscount = $grossBilled;
            }

            $netBilled = max(0, $grossBilled - $billDiscount);

            $totalPaid = (float) DB::table('billing_payment_type')
                ->where('bill_id', $bill->id)
                ->where('is_deleted', 0)
                ->sum('amount');

            $due = max(0, round($netBilled - $totalPaid, 2));
            $isSettled = ($due <= 0.00) ? 1 : 0;
            $isPartiallySettled = ($due > 0 && $totalPaid > 0) ? 1 : 0;

            $bill->update([
                'discount' => $billDiscount,
                'discount_per' => $grossBilled > 0 ? round(($billDiscount / $grossBilled) * 100, 4) : 0,
                'is_settled' => $isSettled,
                'is_partially_settled' => $isPartiallySettled,
                'updated_by' => $userId,
                'updated_at' => $now,
            ]);

            // 4. Update Referring Doctor
            if (! empty($validated['referral_doctor'])) {
                $refDoc = trim($validated['referral_doctor']);
                if ($bill->visit) {
                    $bill->visit->ref_by = $refDoc;
                    $bill->visit->save();
                }
                if ($bill->visit?->patient) {
                    $bill->visit->patient->referring_doctor = $refDoc;
                    $bill->visit->patient->save();
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Bill #' . ($bill->bill_number ?: $bill->id) . ' saved and updated successfully.',
        ]);
    }

    /**
     * Record payment and settle bill due amount.
     */
    public function settlePayment(Request $request, int|string $billId): JsonResponse
    {
        $billId = (int) $billId;
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_mode' => ['required', 'string', 'in:cash,card,upi,credit,net_banking,cheque'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $bill = PatientBilling::with('items')->findOrFail($billId);
        $userId = $request->user()?->id ?: 1;
        $amount = (float) $validated['amount'];

        DB::transaction(function () use ($bill, $validated, $amount, $userId) {
            BillingPaymentType::create([
                'bill_id' => $bill->id,
                'amount' => $amount,
                'payment_mode' => strtolower($validated['payment_mode']),
                'transaction_id' => $validated['transaction_id'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'payment_date' => Carbon::now(),
                'created_by' => $userId,
                'updated_by' => $userId,
                'is_deleted' => 0,
            ]);

            // Re-calculate totals
            $totalPaid = (float) DB::table('billing_payment_type')
                ->where('bill_id', $bill->id)
                ->where('is_deleted', 0)
                ->sum('amount');

            $grossBilled = (float) DB::table('patient_gen_bill')
                ->where('bill_id', $bill->id)
                ->where('is_deleted', 0)
                ->sum(DB::raw('(charges * qty) - discount'));

            $netBilled = max(0, $grossBilled - (float) ($bill->discount ?? 0));

            if ($totalPaid >= $netBilled) {
                $bill->is_settled = 1;
                $bill->save();
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Payment of ₹' . number_format($amount, 2) . ' recorded successfully.',
        ]);
    }

    /**
     * Update bill metadata (referral doctor, notes).
     */
    public function updateBill(Request $request, int|string $billId): JsonResponse
    {
        $billId = (int) $billId;
        $validated = $request->validate([
            'referral_doctor' => ['nullable', 'string', 'max:150'],
            'discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $bill = PatientBilling::with('visit')->findOrFail($billId);

        DB::transaction(function () use ($bill, $validated) {
            if (isset($validated['discount'])) {
                $bill->discount = (float) $validated['discount'];
                $bill->save();
            }

            if (! empty($validated['referral_doctor']) && $bill->visit) {
                $bill->visit->ref_by = trim($validated['referral_doctor']);
                $bill->visit->save();
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Bill details updated successfully.',
        ]);
    }
}
