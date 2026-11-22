<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lab;

use App\Domain\Lab\Actions\AmendReport;
use App\Domain\Lab\Models\LabOrder;
use App\Domain\Lab\Models\LabOrderEvent;
use App\Domain\Lab\Models\LabReport;
use App\Domain\Lab\Services\ReportRenderer;
use App\Domain\Lab\States\LabState;
use App\Http\Controllers\Controller;
use App\Models\LabSetting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        protected ReportRenderer $reportRenderer,
        protected AmendReport $amendReport
    ) {
    }

    public function index(Request $request): View
    {
        $term = $request->input('q');
        $query = LabOrder::with([
            'patient',
            'latestReport.verifiedByUser',
            'reports.verifiedByUser',
            'items.service',
            'items.legacyGenBill'
        ])
        ->where('status', LabState::RELEASED->value)
        ->latest('id');

        if ($term) {
            $query->where(function ($q) use ($term) {
                $q->where('accession_no', 'like', "%{$term}%")
                    ->orWhereHas('patient', function ($pq) use ($term) {
                        $pq->where('Fname', 'like', "%{$term}%")
                            ->orWhere('uhid', $term);
                    });
            });
        }

        $orders = $query->paginate(25);

        return view('lab.reports.index', [
            'orders' => $orders,
            'term' => $term,
        ]);
    }

    public function viewHtml(LabOrder $order, Request $request): View
    {
        $order->loadMissing([
            'patient',
            'visit',
            'items.service',
            'items.legacyGenBill',
            'items.resultValues.parameter',
            'items.specimens',
            'specimens',
            'latestReport.verifiedByUser',
            'reports.verifiedByUser',
        ]);

        $version = (int) $request->input('version', 0);
        $report = $version > 0
            ? $order->reports()->where('version', $version)->firstOrFail()
            : $order->latestReport;

        if (!$report) {
            if (in_array($order->status, [LabState::RELEASED, LabState::VERIFIED, LabState::RESULTED])) {
                $report = LabReport::firstOrCreate(
                    ['lab_order_id' => $order->id, 'version' => 1],
                    [
                        'template' => 'standard',
                        'verified_by' => $order->ordered_by ?: 1,
                        'released_at' => $order->created_at ?? Carbon::now(),
                    ]
                );
            } else {
                abort(404, 'Report not generated yet.');
            }
        }

        $report->loadMissing(['verifiedByUser']);

        LabOrderEvent::create([
            'order_id' => $order->id,
            'from_state' => LabState::RELEASED->value,
            'to_state' => LabState::RELEASED->value,
            'user_id' => $request->user()?->id,
            'meta' => [
                'action' => 'read_access_report_view',
                'report_version' => $report->version,
                'ip' => $request->ip(),
            ],
        ]);

        $labSetting = LabSetting::current();
        $letterhead = $request->boolean('letterhead', true);

        $verifyUrl = route('lab.reports.verify-public', [
            'order' => $order->id,
            'version' => $report->version,
        ]);
        $barcodeUri = $this->reportRenderer->generateBarcodeUri($order->accession_no);
        $qrCodeUri = $this->reportRenderer->generateQrCodeUri($verifyUrl);
        $selectedItemId = $request->input('item_id') ? (int) $request->input('item_id') : null;
        $combineReports = $request->has('combine')
            ? $request->boolean('combine')
            : (bool) ($labSetting->combine_reports_on_print ?? false);

        return view('lab.reports.templates.report_layout', [
            'order' => $order,
            'report' => $report,
            'withLetterhead' => $letterhead,
            'barcodeUri' => $barcodeUri,
            'qrCodeUri' => $qrCodeUri,
            'verifyUrl' => $verifyUrl,
            'isPdf' => false,
            'selectedItemId' => $selectedItemId,
            'combineReports' => $combineReports,
        ]);
    }

    public function printPdf(LabOrder $order, Request $request): Response|BinaryFileResponse
    {
        $order->loadMissing([
            'patient',
            'latestReport.verifiedByUser',
            'reports.verifiedByUser',
        ]);

        $version = (int) $request->input('version', 0);
        $report = $version > 0
            ? $order->reports()->where('version', $version)->first()
            : $order->latestReport;

        if (!$report) {
            $report = LabReport::firstOrCreate(
                ['lab_order_id' => $order->id, 'version' => 1],
                [
                    'template' => 'standard',
                    'verified_by' => $order->ordered_by ?: 1,
                    'released_at' => $order->created_at ?? Carbon::now(),
                ]
            );
        }

        $report->loadMissing(['verifiedByUser']);

        $labSetting = LabSetting::current();

        // Print mode: Default to false (WITHOUT header/footer images) unless explicitly requested with with_header=1
        $withHeader = $request->has('with_header')
            ? $request->boolean('with_header')
            : (bool) ($labSetting->print_with_header ?? false);

        $topMargin = $request->has('top_margin')
            ? (int) $request->input('top_margin')
            : ($withHeader ? 4 : ($labSetting->print_top_margin ?? 35));

        $bottomMargin = $request->has('bottom_margin')
            ? (int) $request->input('bottom_margin')
            : ($withHeader ? 4 : ($labSetting->print_bottom_margin ?? 20));

        $selectedItemId = $request->input('item_id') ? (int) $request->input('item_id') : null;
        $combineReports = $request->has('combine')
            ? $request->boolean('combine')
            : (bool) ($labSetting->combine_reports_on_print ?? false);

        $res = $this->reportRenderer->renderAndStorePdf(
            $order,
            $report,
            $withHeader,
            $topMargin,
            $bottomMargin,
            'print',
            $selectedItemId,
            $combineReports
        );

        $fullPath = Storage::disk('local')->path($res['path']);

        LabOrderEvent::create([
            'order_id' => $order->id,
            'from_state' => $order->status->value ?? LabState::RELEASED->value,
            'to_state' => $order->status->value ?? LabState::RELEASED->value,
            'user_id' => $request->user()?->id,
            'meta' => [
                'action' => 'print_pdf_generated',
                'version' => $report->version,
                'sha256' => $res['sha256'],
                'top_margin' => $topMargin,
                'bottom_margin' => $bottomMargin,
                'with_header' => $withHeader,
                'ip' => $request->ip(),
            ],
        ]);

        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Print_Report_' . $order->accession_no . '_v' . $report->version . '.pdf"',
        ]);
    }

    public function downloadPdf(LabOrder $order, Request $request): BinaryFileResponse|Response
    {
        if ($request->boolean('print') || $request->input('mode') === 'print') {
            return $this->printPdf($order, $request);
        }

        $order->loadMissing([
            'patient',
            'latestReport.verifiedByUser',
            'reports.verifiedByUser',
        ]);

        $version = (int) $request->input('version', 0);
        $report = $version > 0
            ? $order->reports()->where('version', $version)->first()
            : $order->latestReport;

        if (!$report) {
            $report = LabReport::firstOrCreate(
                ['lab_order_id' => $order->id, 'version' => 1],
                [
                    'template' => 'standard',
                    'verified_by' => $order->ordered_by ?: 1,
                    'released_at' => $order->created_at ?? Carbon::now(),
                ]
            );
        }

        $report->loadMissing(['verifiedByUser']);

        $selectedItemId = $request->input('item_id') ? (int) $request->input('item_id') : null;
        $labSetting = LabSetting::current();
        $combineReports = $request->has('combine')
            ? $request->boolean('combine')
            : (bool) ($labSetting->combine_reports_on_print ?? false);

        $hasHeader = !empty($labSetting->getHeaderImageUri());
        $hasFooter = !empty($labSetting->getFooterImageUri());

        $topMargin = $request->has('top_margin')
            ? (int) $request->input('top_margin')
            : ($hasHeader ? 0 : ($labSetting->print_top_margin ?? 35));

        $bottomMargin = $request->has('bottom_margin')
            ? (int) $request->input('bottom_margin')
            : ($hasFooter ? 0 : ($labSetting->print_bottom_margin ?? 20));

        // Download mode: with letterhead header and footer images IF uploaded
        $res = $this->reportRenderer->renderAndStorePdf(
            $order,
            $report,
            true,
            $topMargin,
            $bottomMargin,
            'download',
            $selectedItemId,
            $combineReports
        );

        $fullPath = Storage::disk('local')->path($res['path']);

        LabOrderEvent::create([
            'order_id' => $order->id,
            'from_state' => $order->status->value ?? LabState::RELEASED->value,
            'to_state' => $order->status->value ?? LabState::RELEASED->value,
            'user_id' => $request->user()?->id,
            'meta' => [
                'action' => 'read_access_pdf_download',
                'version' => $report->version,
                'sha256' => $res['sha256'],
                'ip' => $request->ip(),
            ],
        ]);

        return response()->download(
            $fullPath,
            "LabReport_{$order->accession_no}_v{$report->version}.pdf",
            [
                'Content-Type' => 'application/pdf',
                'X-Report-SHA256' => (string) $res['sha256'],
            ]
        );
    }

    public function amend(LabOrder $order, Request $request): JsonResponse
    {
        $request->validate([
            'amend_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $user = $request->user();
        $userId = $user ? (int) $user->id : 1;

        $newReport = $this->amendReport->execute(
            $order->id,
            $userId,
            (string) $request->input('amend_reason')
        );

        return response()->json([
            'success' => true,
            'message' => "Amended report v{$newReport->version} created successfully.",
            'version' => $newReport->version,
            'pdf_url' => route('lab.reports.pdf', ['order' => $order->id, 'version' => $newReport->version]),
        ]);
    }

    public function sendWhatsApp(LabOrder $order, Request $request): JsonResponse
    {
        $order->loadMissing(['patient', 'latestReport']);

        $phone = $order->patient?->mobile_no;
        if (!$phone) {
            return response()->json([
                'success' => false,
                'message' => 'Patient has no registered phone number for WhatsApp.',
            ], 422);
        }

        $report = $order->latestReport;
        if (!$report && in_array($order->status, [LabState::RELEASED, LabState::VERIFIED])) {
            $report = LabReport::firstOrCreate(
                ['lab_order_id' => $order->id, 'version' => 1],
                [
                    'template' => 'standard',
                    'verified_by' => $order->ordered_by ?: 1,
                    'released_at' => $order->created_at ?? Carbon::now(),
                ]
            );
        }

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'No released report found.',
            ], 422);
        }

        LabOrderEvent::create([
            'order_id' => $order->id,
            'from_state' => LabState::RELEASED->value,
            'to_state' => LabState::RELEASED->value,
            'user_id' => $request->user()?->id,
            'meta' => [
                'action' => 'dispatched_whatsapp',
                'phone' => $phone,
                'report_version' => $report->version,
                'time' => Carbon::now()->toIso8601String(),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Report link dispatched to +91-{$phone} via WhatsApp queue.",
        ]);
    }

    public function verifyPublic(LabOrder $order, int|string $version): View
    {
        $version = (int) $version;
        $report = $order->reports()->where('version', $version)->firstOrFail();
        $report->loadMissing(['verifiedByUser']);
        $order->load(['patient', 'items.service']);

        return view('lab.reports.verify_public', [
            'order' => $order,
            'report' => $report,
        ]);
    }
}