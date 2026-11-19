<?php

declare(strict_types=1);

namespace App\Domain\Lab\Actions;

use App\Domain\Lab\Models\LabOrder;
use App\Domain\Lab\Models\LabOrderEvent;
use App\Domain\Lab\Models\LabReport;
use App\Domain\Lab\Services\ReportRenderer;
use App\Domain\Lab\States\LabState;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AmendReport
{
    public function __construct(
        protected ReportRenderer $reportRenderer
    ) {}

    /**
     * Create an amended version (N+1) of a released report with mandatory reason.
     */
    public function execute(int $orderId, int $amendedBy, string $reason): LabReport
    {
        return DB::transaction(function () use ($orderId, $amendedBy, $reason) {
            $reason = trim($reason);
            if (strlen($reason) < 5) {
                throw ValidationException::withMessages([
                    'amend_reason' => ['A detailed clinical/technical reason is mandatory for report amendments.'],
                ]);
            }

            $order = LabOrder::with(['reports', 'items.service', 'patient'])->lockForUpdate()->findOrFail($orderId);

            if ($order->status !== LabState::RELEASED) {
                throw ValidationException::withMessages([
                    'order' => ['Only released reports can be amended.'],
                ]);
            }

            /** @var LabReport $latestReport */
            $latestReport = $order->reports()->orderByDesc('version')->firstOrFail();
            $newVersion = $latestReport->version + 1;

            $amendedReport = LabReport::create([
                'lab_order_id' => $order->id,
                'version' => $newVersion,
                'template' => $latestReport->template,
                'verified_by' => $amendedBy,
                'released_at' => Carbon::now(),
                'amended_from_id' => $latestReport->id,
                'amend_reason' => $reason,
            ]);

            // Render amended PDF and store with SHA-256
            $this->reportRenderer->renderAndStorePdf($order, $amendedReport);

            // Audit log
            LabOrderEvent::create([
                'order_id' => $order->id,
                'from_state' => LabState::RELEASED->value,
                'to_state' => LabState::RELEASED->value,
                'user_id' => $amendedBy,
                'meta' => [
                    'action' => 'amended_report',
                    'previous_version' => $latestReport->version,
                    'new_version' => $newVersion,
                    'amend_reason' => $reason,
                    'new_pdf_sha256' => $amendedReport->pdf_sha256,
                ],
            ]);

            return $amendedReport;
        });
    }
}
