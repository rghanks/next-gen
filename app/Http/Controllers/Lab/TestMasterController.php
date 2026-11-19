<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lab;

use App\Domain\Lab\Actions\CreateOrUpdateLabTest;
use App\Domain\Lab\Actions\SaveTestParametersAndRanges;
use App\Domain\Lab\Actions\SaveTestPricing;
use App\Domain\Lab\Models\LabParameter;
use App\Domain\Lab\Models\LabPriceList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\CreateTestRequest;
use App\Http\Requests\Lab\UpdateTestRequest;
use App\Models\Legacy\HospitalService;
use App\Models\Legacy\HospitalServiceCharge;
use App\Models\Legacy\InvestigationGroupTestIndex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TestMasterController extends Controller
{
    public function __construct(
        protected CreateOrUpdateLabTest $createOrUpdateTest,
        protected SaveTestParametersAndRanges $saveParametersAndRanges,
        protected SaveTestPricing $savePricingAction
    ) {}

    /**
     * Display list of lab tests, profiles, and packages.
     */
    public function index(Request $request): View
    {
        $tab = (string) $request->input('tab', 'all');
        $search = trim((string) $request->input('q', ''));
        $department = trim((string) $request->input('department', ''));

        $query = HospitalService::query()
            ->whereIn('flag', ['test', 'lab_package'])
            ->with(['charges', 'groupTests'])
            ->withCount('groupTests')
            ->latest('id');

        if ($tab === 'single') {
            $query->where('is_group', 0);
        } elseif ($tab === 'group') {
            $query->where('is_group', 1);
        } elseif ($tab === 'active') {
            $query->where('active', 1);
        } elseif ($tab === 'inactive') {
            $query->where('active', 0);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('service', 'like', "%{$search}%")
                    ->orWhere('short_code', 'like', "%{$search}%")
                    ->orWhere('loinc_code', 'like', "%{$search}%")
                    ->orWhere('display_full_name', 'like', "%{$search}%");
            });
        }

        if ($department !== '') {
            $query->where('group_attributes', 'like', '%"group":"'.$department.'"%');
        }

        $tests = $query->paginate(25);

        // Fetch distinct departments for filtering (optimized pluck)
        $departments = HospitalService::whereIn('flag', ['test', 'lab_package'])
            ->whereNotNull('group_attributes')
            ->pluck('group_attributes')
            ->map(function ($ga) {
                $decoded = is_array($ga) ? $ga : json_decode((string) $ga, true);

                return is_array($decoded) && ! empty($decoded['group']) && is_string($decoded['group'])
                    ? trim($decoded['group'])
                    : null;
            })
            ->filter()
            ->unique()
            ->sort()
            ->values();

        // Summary counts for filter pill counters
        $counts = [
            'total' => HospitalService::whereIn('flag', ['test', 'lab_package'])->count(),
            'single' => HospitalService::whereIn('flag', ['test', 'lab_package'])->where('is_group', 0)->count(),
            'group' => HospitalService::whereIn('flag', ['test', 'lab_package'])->where('is_group', 1)->count(),
            'active' => HospitalService::whereIn('flag', ['test', 'lab_package'])->where('active', 1)->count(),
            'inactive' => HospitalService::whereIn('flag', ['test', 'lab_package'])->where('active', 0)->count(),
        ];

        return view('lab.masters.tests.index', [
            'tests' => $tests,
            'tab' => $tab,
            'search' => $search,
            'department' => $department,
            'departments' => $departments,
            'counts' => $counts,
        ]);
    }

    /**
     * Show form to create new test or group panel.
     */
    public function create(): View
    {
        $allTests = HospitalService::where('flag', 'test')
            ->where('is_group', 0)
            ->where('active', 1)
            ->orderBy('service')
            ->get(['id', 'service', 'short_code']);

        return view('lab.masters.tests.create', [
            'allTests' => $allTests,
        ]);
    }

    /**
     * Store newly created test or profile.
     */
    public function store(CreateTestRequest $request): JsonResponse|RedirectResponse
    {
        $service = $this->createOrUpdateTest->execute($request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Lab test master created successfully.',
                'test' => $service,
                'redirect_url' => route('lab.masters.tests.edit', $service->id),
            ]);
        }

        return redirect()->route('lab.masters.tests.edit', $service->id)
            ->with('success', 'Lab test master created successfully.');
    }

    /**
     * Show the detailed editor for a test.
     */
    public function edit(HospitalService $test): View
    {
        $parameters = LabParameter::with('referenceRanges')
            ->where('service_id', $test->id)
            ->orderBy('sort_order')
            ->get();

        $groupComponentIds = [];
        $groupComponents = collect();
        if ($test->is_group) {
            $groupComponents = InvestigationGroupTestIndex::leftJoin('hospital_services', 'hospital_services.id', '=', 'table_investigation_group_test_index.test_id')
                ->where('table_investigation_group_test_index.group_id', $test->id)
                ->orderBy('table_investigation_group_test_index.sequence_no')
                ->select('hospital_services.*', 'table_investigation_group_test_index.sequence_no')
                ->get();
            $groupComponentIds = $groupComponents->pluck('id')->toArray();
        }

        $allAvailableTests = HospitalService::where('flag', 'test')
            ->where('is_group', 0)
            ->where('id', '!=', $test->id)
            ->where('active', 1)
            ->orderBy('service')
            ->get(['id', 'service', 'short_code']);

        // Pricing
        $priceLists = LabPriceList::with(['items' => function ($q) use ($test) {
            $q->where('service_id', $test->id);
        }])->get();

        $firstCharge = HospitalServiceCharge::where('service', $test->id)->first();
        $baseCharge = $firstCharge ? (float) $firstCharge->charges : 0.0;

        return view('lab.masters.tests.edit', [
            'test' => $test,
            'parameters' => $parameters,
            'groupComponents' => $groupComponents,
            'groupComponentIds' => $groupComponentIds,
            'allAvailableTests' => $allAvailableTests,
            'priceLists' => $priceLists,
            'baseCharge' => $baseCharge,
        ]);
    }

    /**
     * Update existing test details.
     */
    public function update(UpdateTestRequest $request, HospitalService $test): JsonResponse|RedirectResponse
    {
        $service = $this->createOrUpdateTest->execute($request->validated(), $test);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Test updated successfully.',
                'test' => $service,
            ]);
        }

        return back()->with('success', 'Test updated successfully.');
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus(HospitalService $test): JsonResponse
    {
        $test->active = $test->active ? 0 : 1;
        $test->save();

        return response()->json([
            'success' => true,
            'active' => (bool) $test->active,
            'message' => 'Status updated successfully.',
        ]);
    }

    /**
     * Save parameters and reference range matrix.
     */
    public function saveParameters(Request $request, HospitalService $test): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_code' => ['required', 'string', 'max:50'],
            'unit' => ['nullable', 'string', 'max:50'],
            'method' => ['nullable', 'string', 'max:100'],
            'loinc_code' => ['nullable', 'string', 'max:50'],
            'input_type' => ['required', 'string', 'in:numeric,text,options,formula'],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:4'],
            'formula' => ['nullable', 'string', 'max:500'],
            'options' => ['nullable', 'array'],
            'text_range' => ['nullable', 'string', 'max:255'],
            'ranges' => ['nullable', 'array'],
            'ranges.*.sex' => ['required', 'string', 'in:male,female,both'],
            'ranges.*.group_name' => ['required', 'string'],
            'ranges.*.age_from_days' => ['required', 'integer', 'min:0'],
            'ranges.*.age_to_days' => ['required', 'integer', 'min:0'],
            'ranges.*.low' => ['nullable', 'numeric'],
            'ranges.*.high' => ['nullable', 'numeric'],
            'ranges.*.critical_low' => ['nullable', 'numeric'],
            'ranges.*.critical_high' => ['nullable', 'numeric'],
            'ranges.*.text_range' => ['nullable', 'string'],
        ]);

        $this->saveParametersAndRanges->execute($test, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Parameters and Reference Ranges saved successfully.',
        ]);
    }

    /**
     * Save pricing across payer categories.
     */
    public function savePricing(Request $request, HospitalService $test): JsonResponse
    {
        $validated = $request->validate([
            'pricing' => ['required', 'array'],
            'pricing.*' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        /** @var array<string, float|int|numeric-string> $pricingMap */
        $pricingMap = $validated['pricing'];
        $cost = (float) ($validated['cost'] ?? 0.0);

        $this->savePricingAction->execute($test, $pricingMap, $cost);

        return response()->json([
            'success' => true,
            'message' => 'Pricing saved and synchronized successfully.',
        ]);
    }

    /**
     * Inline quick update for base test charge.
     */
    public function updateChargeInline(Request $request, HospitalService $test): JsonResponse
    {
        $validated = $request->validate([
            'charge' => ['required', 'numeric', 'min:0'],
        ]);

        $charge = (float) $validated['charge'];

        $this->savePricingAction->execute($test, [
            'general' => $charge,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Charge for '{$test->service}' updated to ₹".number_format($charge, 2).' successfully.',
            'charge' => $charge,
            'formatted_charge' => '₹ '.number_format($charge, 2),
        ]);
    }

    /**
     * Search available tests for group inclusion autocomplete.
     */
    public function searchAvailableTests(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 1) {
            return response()->json([]);
        }

        $tests = HospitalService::where('flag', 'test')
            ->where('is_group', 0)
            ->where('active', 1)
            ->where(function ($query) use ($q) {
                $query->where('service', 'like', "%{$q}%")
                    ->orWhere('short_code', 'like', "%{$q}%");
            })
            ->limit(20)
            ->get(['id', 'service', 'short_code']);

        return response()->json($tests);
    }
}
