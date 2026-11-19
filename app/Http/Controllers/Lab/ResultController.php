<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lab;

use App\Domain\Lab\Actions\SaveResults;
use App\Domain\Lab\Models\LabCriticalAcknowledgement;
use App\Domain\Lab\Models\LabOrder;
use App\Domain\Lab\Models\LabOrderItem;
use App\Domain\Lab\Models\LabParameter;
use App\Domain\Lab\Services\FormulaEvaluator;
use App\Domain\Lab\Services\PriceResolver;
use App\Domain\Lab\Services\ReferenceRangeResolver;
use App\Domain\Lab\States\LabState;
use App\Http\Controllers\Controller;
use App\Models\Legacy\HospitalService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResultController extends Controller
{
    public function __construct(
        protected SaveResults $saveResults,
        protected FormulaEvaluator $formulaEvaluator,
        protected ReferenceRangeResolver $rangeResolver,
        protected PriceResolver $priceResolver
    ) {}

    /**
     * Show Result Entry Worklist with TAT timers and priority filters.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,resulted,completed,all'],
            'priority' => ['nullable', 'in:routine,stat'],
            'q' => ['nullable', 'string', 'max:120'],
            'uhid' => ['nullable', 'string', 'max:60'],
            'bill_id' => ['nullable', 'string', 'max:60'],
            'name' => ['nullable', 'string', 'max:120'],
            'patient_name' => ['nullable', 'string', 'max:120'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'service_id' => ['nullable'],
        ]);
        $status = $filters['status'] ?? 'pending';
        $priority = $filters['priority'] ?? null;
        $term = trim($filters['q'] ?? '');
        $uhid = trim((string) ($filters['uhid'] ?? ''));
        $billId = trim((string) ($filters['bill_id'] ?? ''));
        $patientName = trim((string) ($filters['patient_name'] ?? $filters['name'] ?? ''));
        $mobileNo = trim((string) ($filters['mobile'] ?? ''));
        $serviceId = $filters['service_id'] ?? null;
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        $query = LabOrder::with([
            'patient',
            'items.service',
            'items.resultValues.parameter',
            'specimens',
            'bill',
            'latestReport',
        ])->latest('id');

        if ($status === 'pending') {
            $query->whereIn('status', [LabState::RECEIVED->value, LabState::IN_PROCESS->value]);
        } elseif ($status === 'resulted') {
            $query->where('status', LabState::RESULTED->value);
        } elseif ($status === 'completed') {
            $query->whereIn('status', [LabState::VERIFIED->value, LabState::RELEASED->value]);
        }

        if ($priority) {
            $query->where('priority', $priority);
        }

        if ($term !== '') {
            $query->where(function ($search) use ($term) {
                $search->where('accession_no', 'like', "%{$term}%")
                    ->orWhereHas('patient', function ($patient) use ($term) {
                        $patient->where('Fname', 'like', "%{$term}%")
                            ->orWhere('Lname', 'like', "%{$term}%")
                            ->orWhere('uhid', 'like', "%{$term}%")
                            ->orWhere('mobile_no', 'like', "%{$term}%");
                    })
                    ->orWhereHas('items.service', fn ($service) => $service->where('service', 'like', "%{$term}%"));
            });
        }

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
        if (! empty($serviceId)) {
            $query->whereHas('items', function ($it) use ($serviceId) {
                $it->where('service_id', (int) $serviceId);
            });
        }

        if (! empty($dateFrom)) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if (! empty($dateTo)) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $orders = $query->paginate(25);

        $services = HospitalService::where('active', 1)->orderBy('service')->get(['id', 'service']);

        $baseCountQuery = LabOrder::query();
        if (! empty($dateFrom)) {
            $baseCountQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if (! empty($dateTo)) {
            $baseCountQuery->whereDate('created_at', '<=', $dateTo);
        }

        $counts = [
            'all' => (clone $baseCountQuery)->count(),
            'pending' => (clone $baseCountQuery)->whereIn('status', [LabState::RECEIVED->value, LabState::IN_PROCESS->value])->count(),
            'resulted' => (clone $baseCountQuery)->where('status', LabState::RESULTED->value)->count(),
            'completed' => (clone $baseCountQuery)->whereIn('status', [LabState::VERIFIED->value, LabState::RELEASED->value])->count(),
        ];

        return view('lab.results.index', [
            'orders' => $orders,
            'status' => $status,
            'priority' => $priority,
            'term' => $term,
            'uhid' => $uhid,
            'billId' => $billId,
            'patientName' => $patientName,
            'mobileNo' => $mobileNo,
            'serviceId' => $serviceId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'services' => $services,
            'counts' => $counts,
        ]);
    }

    /**
     * Show parameter entry grid for an order.
     */
    public function entry(LabOrder $order): View
    {
        $order->load([
            'patient',
            'specimens',
            'items.service',
            'items.legacyGenBill',
            'items.resultValues.parameter',
            'items.resultValues.criticalAcknowledgement',
        ]);

        $patient = $order->patient;
        $ageInDays = $patient ? $this->rangeResolver->calculateAgeInDays((int) $patient->age) : 365 * 30;
        $sex = $patient ? (string) $patient->sex : 'both';

        // Build item grids with component tests, parameters and resolved reference ranges
        $itemGrids = [];
        foreach ($order->items as $item) {
            $componentServices = $this->priceResolver->expandComponents($item->service_id);
            $sections = [];
            $allFlatParams = [];

            foreach ($componentServices as $compSrv) {
                $params = LabParameter::where('service_id', $compSrv->id)
                    ->orderBy('sort_order')
                    ->get();

                // Auto-provision fallback if no parameter record exists yet
                if ($params->isEmpty()) {
                    $inputAttrs = is_array($compSrv->input_attributes)
                        ? $compSrv->input_attributes
                        : json_decode((string) $compSrv->input_attributes, true);

                    $unit = is_array($inputAttrs) && isset($inputAttrs['unit'])
                        ? (is_numeric($inputAttrs['unit'])
                            ? (\Illuminate\Support\Facades\DB::table('inv_test_units')->where('id', (int) $inputAttrs['unit'])->value('unit_name') ?: '')
                            : (string) $inputAttrs['unit'])
                        : '';

                    $inputType = is_array($inputAttrs) && isset($inputAttrs['input_type']) && in_array($inputAttrs['input_type'], ['numeric', 'text', 'options', 'formula'], true)
                        ? (string) $inputAttrs['input_type']
                        : 'numeric';

                    $params = collect([
                        LabParameter::firstOrCreate(
                            [
                                'service_id' => $compSrv->id,
                                'short_code' => $compSrv->short_code ?: 'VAL_'.$compSrv->id,
                            ],
                            [
                                'name' => $compSrv->service ?: 'Test Result',
                                'input_type' => $inputType,
                                'unit' => $unit,
                            ]
                        ),
                    ]);
                }

                $paramRows = [];
                foreach ($params as $p) {
                    $existingVal = $item->resultValues->firstWhere('parameter_id', $p->id);
                    $range = $this->rangeResolver->resolve($p, $ageInDays, $sex);

                    $rowData = [
                        'parameter' => $p,
                        'existing' => $existingVal,
                        'range' => $range,
                    ];
                    $paramRows[] = $rowData;
                    $allFlatParams[] = $rowData;
                }

                $sections[] = [
                    'component_service' => $compSrv,
                    'parameters' => $paramRows,
                ];
            }

            $itemGrids[] = [
                'item' => $item,
                'is_group' => count($componentServices) > 1 || (bool) $item->service?->is_group,
                'sections' => $sections,
                'parameters' => $allFlatParams,
            ];
        }

        return view('lab.results.entry', [
            'order' => $order,
            'itemGrids' => $itemGrids,
        ]);
    }

    /**
     * Save results via SaveResults action.
     */
    public function save(Request $request): JsonResponse
    {
        $request->validate([
            'order_item_id' => ['required', 'integer', 'exists:lab_order_items,id'],
            'row_version' => ['required', 'integer'],
            'results' => ['required', 'array'],
            'results.*.parameter_id' => ['required', 'integer'],
            'results.*.value' => ['nullable'],
        ]);

        $user = $request->user();
        $userId = $user ? (int) $user->id : 1;

        $result = $this->saveResults->execute([
            'order_item_id' => (int) $request->input('order_item_id'),
            'expected_row_version' => (int) $request->input('row_version'),
            'results' => $request->input('results'),
            'entered_by' => $userId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Results saved successfully.',
            'new_version' => $result['order_item']->row_version,
            'delta_alerts' => $result['delta_alerts'],
            'critical_count' => $result['critical_count'],
        ]);
    }

    /**
     * Live formula evaluation preview endpoint.
     */
    public function previewFormula(Request $request): JsonResponse
    {
        $formula = (string) $request->input('formula', '');
        $variables = (array) $request->input('variables', []);
        $decimals = (int) $request->input('decimals', 2);

        $computed = $this->formulaEvaluator->evaluate($formula, $variables, $decimals);

        return response()->json([
            'computed_value' => $computed,
        ]);
    }

    /**
     * Acknowledge critical panic value.
     */
    public function acknowledgeCritical(Request $request): JsonResponse
    {
        $request->validate([
            'result_value_id' => ['required', 'integer', 'exists:lab_result_values,id'],
            'informed_person' => ['required', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $userId = $user ? (int) $user->id : 1;

        $ack = LabCriticalAcknowledgement::create([
            'result_value_id' => (int) $request->input('result_value_id'),
            'informed_person' => trim($request->input('informed_person')),
            'informed_by' => $userId,
            'informed_at' => Carbon::now(),
            'remarks' => $request->input('remarks'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Critical value acknowledgement recorded.',
            'ack' => $ack,
        ]);
    }

    /**
     * Upload outsourced test PDF report.
     */
    public function outsourceUpload(Request $request): JsonResponse
    {
        $request->validate([
            'order_item_id' => ['required', 'integer', 'exists:lab_order_items,id'],
            'outsource_lab_id' => ['nullable', 'integer'],
            'outsource_notes' => ['nullable', 'string'],
            'pdf_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $item = LabOrderItem::findOrFail($request->input('order_item_id'));
        $path = $request->file('pdf_file')->store('outsource_reports', 'public');

        $item->is_outsource = true;
        $item->outsource_lab_id = $request->input('outsource_lab_id');
        $item->outsource_notes = $request->input('outsource_notes');
        $item->outsource_report_path = $path;
        $item->status = LabState::RESULTED;
        $item->save();

        return response()->json([
            'success' => true,
            'message' => 'Outsourced report uploaded and attached.',
            'path' => $path,
        ]);
    }
}
