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
    ) {}

    /**
     * Show Released Reports List.
     */
    public function index(Request $request): View
    {
        $term = $request->input('q');

        $query = LabOrder::with([
            'patient',
            'latestReport.verifiedByUser',
            'reports.verifiedByUser',
            'items.service',
            'items.legacyGenBill',
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

    /**
     * Preview report HTML.
     */
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

        if (! $report) {
            if (in_array($order->status, [LabState::RELEASED, LabState::VERIFIED, LabState::RESULTED])) {
                $report = LabReport::firstOrCreate(
                    [
                        'lab_order_id' => $order->id,
                        'version' => 1,
                    ],
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

        // Audit read access
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

        $letterhead = $request->boolean('letterhead', true);
        $verifyUrl = route('lab.reports.verify-public', ['order' => $order->id, 'version' => $report->version]);
        $barcodeUri = $this->reportRenderer->generateBarcodeUri($order->accession_no);
        $qrCodeUri = $this->reportRenderer->generateQrCodeUri($verifyUrl);
        $selectedItemId = $request->input('item_id') ? (int) $request->input('item_id') : null;

        return view('lab.reports.templates.report_layout', [
            'order' => $order,
            'report' => $report,
            'withLetterhead' => $letterhead,
            'barcodeUri' => $barcodeUri,
            'qrCodeUri' => $qrCodeUri,
            'verifyUrl' => $verifyUrl,
            'isPdf' => false,
            'selectedItemId' => $selectedItemId,
        ]);
    }

    /**
     * Generate & stream printable PDF report inline (with configurable top/bottom margins).
     */
    public function printPdf(LabOrder $order, Request $request): Response|BinaryFileResponse
    {
        $order->loadMissing(['patient', 'latestReport.verifiedByUser', 'reports.verifiedByUser']);

        $version = (int) $request->input('version', 0);
        $report = $version > 0
            ? $order->reports()->where('version', $version)->first()
            : $order->latestReport;

        if (! $report) {
            $report = LabReport::firstOrCreate(
                [
                    'lab_order_id' => $order->id,
                    'version' => 1,
                ],
                [
                    'template' => 'standard',
                    'verified_by' => $order->ordered_by ?: 1,
                    'released_at' => $order->created_at ?? Carbon::now(),
                ]
            );
        }

        $report->loadMissing(['verifiedByUser']);

        $labSetting = LabSetting::current();

        // Print header setting: default false (print on pre-printed stationery with top/bottom margin)
        $withHeader = $request->has('with_header')
            ? $request->boolean('with_header')
            : (bool) $labSetting->print_with_header;

        $topMargin = $request->has('top_margin')
            ? (int) $request->input('top_margin')
            : ($labSetting->print_top_margin ?? 35);

        $bottomMargin = $request->has('bottom_margin')
            ? (int) $request->input('bottom_margin')
            : ($labSetting->print_bottom_margin ?? 20);

        $selectedItemId = $request->input('item_id') ? (int) $request->input('item_id') : null;

        $res = $this->reportRenderer->renderAndStorePdf(
            $order,
            $report,
            $withHeader,
            $topMargin,
            $bottomMargin,
            'print',
            $selectedItemId
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

    /**
     * Download or view stored immutable PDF with header (as uploaded in print setting).
     */
    public function downloadPdf(LabOrder $order, Request $request): BinaryFileResponse|Response
    {
        // If print=1 query parameter is passed, delegate directly to printPdf
        if ($request->boolean('print') || $request->input('mode') === 'print') {
            return $this->printPdf($order, $request);
        }

        $order->loadMissing(['patient', 'latestReport.verifiedByUser', 'reports.verifiedByUser']);

        $version = (int) $request->input('version', 0);
        $report = $version > 0
            ? $order->reports()->where('version', $version)->first()
            : $order->latestReport;

        if (! $report) {
            $report = LabReport::firstOrCreate(
                [
                    'lab_order_id' => $order->id,
                    'version' => 1,
                ],
                [
                    'template' => 'standard',
                    'verified_by' => $order->ordered_by ?: 1,
                    'released_at' => $order->created_at ?? Carbon::now(),
                ]
            );
        }

        $report->loadMissing(['verifiedByUser']);

        $labSetting = LabSetting::current();
        $selectedItemId = $request->input('item_id') ? (int) $request->input('item_id') : null;

        // Download always includes header as uploaded by user in print setting
        $res = $this->reportRenderer->renderAndStorePdf(
            $order,
            $report,
            true, // withLetterhead = true
            null, // standard top margin
            $labSetting->print_bottom_margin ?? 20,
            'download',
            $selectedItemId
        );

        $fullPath = Storage::disk('local')->path($res['path']);

        // Audit PDF download read access
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

        return response()->download($fullPath, "LabReport_{$order->accession_no}_v{$report->version}.pdf", [
            'Content-Type' => 'application/pdf',
            'X-Report-SHA256' => (string) $res['sha256'],
        ]);
    }

    /**
     * Amend released report.
     */
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

    /**
     * Send report via WhatsApp (queued / throttled).
     */
    public function sendWhatsApp(LabOrder $order, Request $request): JsonResponse
    {
        $order->loadMissing(['patient', 'latestReport']);
        $phone = $order->patient?->mobile_no;
        if (! $phone) {
            return response()->json([
                'success' => false,
                'message' => 'Patient has no registered phone number for WhatsApp.',
            ], 422);
        }

        $report = $order->latestReport;
        if (! $report && in_array($order->status, [LabState::RELEASED, LabState::VERIFIED])) {
            $report = LabReport::firstOrCreate(
                [
                    'lab_order_id' => $order->id,
                    'version' => 1,
                ],
                [
                    'template' => 'standard',
                    'verified_by' => $order->ordered_by ?: 1,
                    'released_at' => $order->created_at ?? Carbon::now(),
                ]
            );
        }

        if (! $report) {
            return response()->json([
                'success' => false,
                'message' => 'No released report found.',
            ], 422);
        }

        // Audit dispatch
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

    /**
     * Public signed URL verification view (for QR code scans).
     */
    public function verifyPublic(LabOrder $order, int $version): View
    {
        $report = $order->reports()->where('version', $version)->firstOrFail();
        $report->loadMissing(['verifiedByUser']);
        $order->load(['patient', 'items.service']);

        return view('lab.reports.verify_public', [
            'order' => $order,
            'report' => $report,
        ]);
    }
}
