<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lab;

use App\Domain\Lab\Models\LabOrder;
use App\Domain\Lab\Models\LabReport;
use App\Domain\Lab\Models\LabResultValue;
use App\Domain\Lab\Models\Specimen;
use App\Domain\Lab\States\LabState;
use App\Http\Controllers\Controller;
use App\Models\LabSetting;
use App\Models\Legacy\HospitalService;
use App\Models\Legacy\Patient;
use App\Models\Legacy\PatientBilling;
use App\Models\User;
use App\Services\Licensing\HardwareFingerprint;
use App\Services\Licensing\LicenseManager;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the System Overview Dashboard.
     */
    public function index(Request $request): View
    {
        $period = (string) $request->input('period', 'today');
        $customFrom = $request->input('date_from');
        $customTo = $request->input('date_to');

        // Resolve Date Boundaries
        [$startDate, $endDate, $periodLabel] = $this->resolveDateRange($period, $customFrom, $customTo);

        // 1. High-Level Order, Patient & Visit KPI Counters
        $ordersQuery = LabOrder::query()
            ->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()]);

        $totalOrdersInPeriod = (clone $ordersQuery)->count();
        $totalOrdersAllTime = LabOrder::count();

        // New patient registrations in period (pi table)
        $patientsInPeriod = Patient::whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])->count();
        $totalPatientsAllTime = Patient::count();

        // Patient visits in period (visitlog table)
        $visitsInPeriod = DB::table('visitlog')
            ->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->count();
        $totalVisitsAllTime = DB::table('visitlog')->count();

        // Recent registered patients list for detail audit modal
        $recentRegisteredPatients = Patient::whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->latest('id')
            ->take(100)
            ->get();

        // 2. Financial Metrics for Selected Period
        $billingQuery = DB::table('patient_billing as pb')
            ->where('pb.is_cancelled', 0)
            ->whereBetween('pb.created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()]);

        $periodBillIds = (clone $billingQuery)->pluck('id')->toArray();

        $grossBilled = 0.0;
        $totalDiscounts = 0.0;
        $totalCollected = 0.0;

        if (! empty($periodBillIds)) {
            $grossBilled = (float) DB::table('patient_gen_bill')
                ->whereIn('bill_id', $periodBillIds)
                ->where('is_deleted', 0)
                ->where('is_cancelled', 0)
                ->sum(DB::raw('(charges * qty)'));

            $itemDiscounts = (float) DB::table('patient_gen_bill')
                ->whereIn('bill_id', $periodBillIds)
                ->where('is_deleted', 0)
                ->where('is_cancelled', 0)
                ->sum('discount');

            $billLevelDiscounts = (float) (clone $billingQuery)->sum('pb.discount');
            $totalDiscounts = $itemDiscounts + $billLevelDiscounts;

            $totalCollected = (float) DB::table('billing_payment_type')
                ->whereIn('bill_id', $periodBillIds)
                ->where('is_deleted', 0)
                ->sum('amount');
        }

        $netBilled = max(0.0, $grossBilled - $totalDiscounts);
        $totalDue = max(0.0, round($netBilled - $totalCollected, 2));
        $collectionRate = $netBilled > 0 ? round(($totalCollected / $netBilled) * 100, 1) : 0.0;

        // 3. Laboratory Workflow Funnel / Lifecycle Breakdown (Orders in current state)
        $statusCountsRaw = LabOrder::select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->toArray();

        $pipeline = [
            'ordered' => (int) ($statusCountsRaw[LabState::ORDERED->value] ?? 0),
            'collected' => (int) ($statusCountsRaw[LabState::COLLECTED->value] ?? 0),
            'received' => (int) ($statusCountsRaw[LabState::RECEIVED->value] ?? 0),
            'in_process' => (int) ($statusCountsRaw[LabState::IN_PROCESS->value] ?? 0),
            'resulted' => (int) ($statusCountsRaw[LabState::RESULTED->value] ?? 0),
            'verified' => (int) ($statusCountsRaw[LabState::VERIFIED->value] ?? 0),
            'released' => (int) ($statusCountsRaw[LabState::RELEASED->value] ?? 0),
            'cancelled' => (int) ($statusCountsRaw[LabState::CANCELLED->value] ?? 0),
        ];

        $activeWorkload = $pipeline['ordered'] + $pipeline['collected'] + $pipeline['received']
            + $pipeline['in_process'] + $pipeline['resulted'] + $pipeline['verified'];

        // 4. Critical & Panic Value Alerts (Unacknowledged or Latest)
        $criticalAlerts = LabResultValue::whereIn('flag', ['LL', 'HH'])
            ->with([
                'parameter',
                'orderItem.order.patient',
                'orderItem.service',
                'criticalAcknowledgement.informedByUser',
            ])
            ->latest('id')
            ->take(6)
            ->get();

        $totalCriticalCount = LabResultValue::whereIn('flag', ['LL', 'HH'])->count();
        $unackedCriticalCount = LabResultValue::whereIn('flag', ['LL', 'HH'])
            ->whereDoesntHave('criticalAcknowledgement')
            ->count();

        // 5. 7-Day Performance Trend (Daily Orders & Collections)
        $trendDates = [];
        $trendOrders = [];
        $trendRevenue = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $dayStr = $day->toDateString();
            $dayLabel = $day->format('D, d M');

            $dayOrdersCount = LabOrder::whereDate('created_at', $dayStr)->count();

            $dayRevenue = (float) DB::table('billing_payment_type')
                ->where('is_deleted', 0)
                ->whereDate('payment_date', $dayStr)
                ->sum('amount');

            $trendDates[] = $dayLabel;
            $trendOrders[] = $dayOrdersCount;
            $trendRevenue[] = round($dayRevenue, 2);
        }

        // 6. Payment Modes Distribution & Comprehensive Collection Report
        // A. Period Payment Modes (Selected Date Filter)
        $rawPeriodPm = DB::table('billing_payment_type')
            ->where('is_deleted', 0)
            ->whereBetween(DB::raw('DATE(payment_date)'), [$startDate->toDateString(), $endDate->toDateString()])
            ->select(
                'payment_mode',
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('COUNT(*) as tx_count')
            )
            ->groupBy('payment_mode')
            ->orderByDesc('total_amount')
            ->get();

        $periodPaymentModes = $this->formatPaymentModes($rawPeriodPm);
        $periodCollectionSum = (float) $periodPaymentModes->sum('amount');
        $periodTxCount = (int) $periodPaymentModes->sum('tx_count');

        // B. All-Time Payment Modes (Cumulative System History)
        $rawAllTimePm = DB::table('billing_payment_type')
            ->where('is_deleted', 0)
            ->select(
                'payment_mode',
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('COUNT(*) as tx_count')
            )
            ->groupBy('payment_mode')
            ->orderByDesc('total_amount')
            ->get();

        $allTimePaymentModes = $this->formatPaymentModes($rawAllTimePm);
        $allTimeCollectionSum = (float) $allTimePaymentModes->sum('amount');
        $allTimeTxCount = (int) $allTimePaymentModes->sum('tx_count');

        // Compatible for charts: use period if has data, else fallback to all-time
        $paymentModes = $periodPaymentModes->isNotEmpty() ? $periodPaymentModes : $allTimePaymentModes;

        // 7. Top High-Volume Lab Tests / Services
        $topServices = DB::table('patient_gen_bill as pgb')
            ->join('hospital_services as hs', 'pgb.service_id', '=', 'hs.id')
            ->where('pgb.is_deleted', 0)
            ->where('pgb.is_cancelled', 0)
            ->select(
                'hs.id',
                'hs.service',
                'hs.code',
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(pgb.charges * pgb.qty) as gross_revenue')
            )
            ->groupBy('hs.id', 'hs.service', 'hs.code')
            ->orderByDesc('order_count')
            ->limit(6)
            ->get();

        // 8. Recent Lab Orders Feed (Live Operational Worklist preview)
        $recentOrders = LabOrder::with([
            'patient',
            'specimens',
            'items.service',
            'bill.payments',
            'latestReport',
        ])
            ->latest('id')
            ->take(8)
            ->get();

        // 9. System Diagnostics & License Health
        $labSetting = LabSetting::current();
        $licenseStatus = LicenseManager::verify();
        $machineId = HardwareFingerprint::getMachineId();
        $totalServices = HospitalService::where('active', 1)->count();
        $totalUsers = User::count();
        $activeUsers = User::where('active', 1)->count();
        $inactiveUsers = max(0, $totalUsers - $activeUsers);
        $doctorUsers = User::where('is_doctor', 1)->count();
        $staffUsers = User::where('type', 'emp')->count();
        $operatorUsers = User::where('type', '1')->count();
        $consultantUsers = User::where('type', 'non-emp')->count();
        $proUsers = User::where('type', 'pro')->count();
        $adminUsers = max(0, $totalUsers - ($doctorUsers + $staffUsers + $operatorUsers + $consultantUsers + $proUsers));

        $usersList = User::select(
            'id', 'name', 'username', 'email', 'contact_no', 'department', 'designation', 'type', 'is_doctor', 'active', 'created_at'
        )->orderBy('name')->get();

        $totalSpecimens = Specimen::count();
        $totalReports = LabReport::count();

        return view('lab.dashboard.index', [
            'period' => $period,
            'periodLabel' => $periodLabel,
            'startDate' => $startDate->toDateString(),
            'endDate' => $endDate->toDateString(),
            'totalOrdersInPeriod' => $totalOrdersInPeriod,
            'totalOrdersAllTime' => $totalOrdersAllTime,
            'patientsInPeriod' => $patientsInPeriod,
            'totalPatientsAllTime' => $totalPatientsAllTime,
            'visitsInPeriod' => $visitsInPeriod,
            'totalVisitsAllTime' => $totalVisitsAllTime,
            'recentRegisteredPatients' => $recentRegisteredPatients,
            'grossBilled' => $grossBilled,
            'totalDiscounts' => $totalDiscounts,
            'netBilled' => $netBilled,
            'totalCollected' => $totalCollected,
            'totalDue' => $totalDue,
            'collectionRate' => $collectionRate,
            'pipeline' => $pipeline,
            'activeWorkload' => $activeWorkload,
            'criticalAlerts' => $criticalAlerts,
            'totalCriticalCount' => $totalCriticalCount,
            'unackedCriticalCount' => $unackedCriticalCount,
            'trendDates' => $trendDates,
            'trendOrders' => $trendOrders,
            'trendRevenue' => $trendRevenue,
            'paymentModes' => $paymentModes,
            'periodPaymentModes' => $periodPaymentModes,
            'periodCollectionSum' => $periodCollectionSum,
            'periodTxCount' => $periodTxCount,
            'allTimePaymentModes' => $allTimePaymentModes,
            'allTimeCollectionSum' => $allTimeCollectionSum,
            'allTimeTxCount' => $allTimeTxCount,
            'topServices' => $topServices,
            'recentOrders' => $recentOrders,
            'labSetting' => $labSetting,
            'licenseStatus' => $licenseStatus,
            'machineId' => $machineId,
            'totalServices' => $totalServices,
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'inactiveUsers' => $inactiveUsers,
            'doctorUsers' => $doctorUsers,
            'staffUsers' => $staffUsers,
            'operatorUsers' => $operatorUsers,
            'consultantUsers' => $consultantUsers,
            'proUsers' => $proUsers,
            'adminUsers' => $adminUsers,
            'usersList' => $usersList,
            'totalSpecimens' => $totalSpecimens,
            'totalReports' => $totalReports,
        ]);
    }

    /**
     * JSON Endpoint for Live Dashboard Metrics.
     */
    public function apiStats(Request $request): JsonResponse
    {
        $today = Carbon::today()->toDateString();

        $todayOrders = LabOrder::whereDate('created_at', $today)->count();
        $todayCollected = (float) DB::table('billing_payment_type')
            ->where('is_deleted', 0)
            ->whereDate('payment_date', $today)
            ->sum('amount');

        $pendingCollection = LabOrder::where('status', LabState::ORDERED->value)->count();
        $inProcess = LabOrder::where('status', LabState::IN_PROCESS->value)->count();
        $pendingVerification = LabOrder::where('status', LabState::RESULTED->value)->count();
        $unackedCritical = LabResultValue::whereIn('flag', ['LL', 'HH'])
            ->whereDoesntHave('criticalAcknowledgement')
            ->count();

        return response()->json([
            'success' => true,
            'timestamp' => Carbon::now()->toIso8601String(),
            'today_orders' => $todayOrders,
            'today_revenue' => round($todayCollected, 2),
            'pending_collection' => $pendingCollection,
            'in_process' => $inProcess,
            'pending_verification' => $pendingVerification,
            'unacknowledged_critical' => $unackedCritical,
        ]);
    }

    /**
     * Resolve date range from preset period or custom inputs.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    protected function resolveDateRange(string $period, ?string $customFrom, ?string $customTo): array
    {
        $today = Carbon::today();

        return match ($period) {
            'yesterday' => [
                $today->copy()->subDay(),
                $today->copy()->subDay(),
                'Yesterday (' . $today->copy()->subDay()->format('d M Y') . ')',
            ],
            'this_week' => [
                $today->copy()->subDays(6),
                $today->copy(),
                'Last 7 Days (' . $today->copy()->subDays(6)->format('d M') . ' - ' . $today->format('d M Y') . ')',
            ],
            'this_month' => [
                $today->copy()->startOfMonth(),
                $today->copy(),
                'This Month (' . $today->format('M Y') . ')',
            ],
            'all' => [
                Carbon::parse('2000-01-01'),
                $today->copy(),
                'All-Time Records',
            ],
            'custom' => [
                $customFrom ? Carbon::parse($customFrom) : $today->copy()->startOfMonth(),
                $customTo ? Carbon::parse($customTo) : $today->copy(),
                'Custom Range (' . ($customFrom ?: 'Start') . ' to ' . ($customTo ?: $today->toDateString()) . ')',
            ],
            default => [
                $today->copy(),
                $today->copy(),
                'Today (' . $today->format('d M Y') . ')',
            ],
        };
    }

    /**
     * Format payment mode records with friendly labels, icons, amounts, and percent share.
     *
     * @param \Illuminate\Support\Collection<int, object> $collection
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function formatPaymentModes(\Illuminate\Support\Collection $collection): \Illuminate\Support\Collection
    {
        $grandTotal = (float) $collection->sum('total_amount');

        return $collection->map(function ($row) use ($grandTotal) {
            $modeRaw = strtolower(trim((string) ($row->payment_mode ?? 'cash')));
            if ($modeRaw === '') {
                $modeRaw = 'cash';
            }

            [$label, $icon, $color] = match ($modeRaw) {
                'cash' => ['Cash Payment', 'uil uil-money-bill', '#0d766e'],
                'upi' => ['UPI / Digital QR', 'uil uil-qrcode-scan', '#0284c7'],
                'card' => ['Credit / Debit Card', 'uil uil-credit-card', '#7c3aed'],
                'neft_rtgs' => ['NEFT / RTGS Transfer', 'uil uil-university', '#d97706'],
                'cheque' => ['Cheque Payment', 'uil uil-file-alt', '#64748b'],
                'net_banking' => ['Net Banking', 'uil uil-globe', '#059669'],
                default => [ucfirst(str_replace('_', ' ', $modeRaw)), 'uil uil-wallet', '#475569'],
            };

            $amount = (float) $row->total_amount;
            $percent = $grandTotal > 0 ? round(($amount / $grandTotal) * 100, 1) : 0.0;

            $row->mode = $modeRaw;
            $row->label = $label;
            $row->icon = $icon;
            $row->color = $color;
            $row->amount = $amount;
            $row->percent = $percent;

            return $row;
        });
    }
}
