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

class ReleaseReport
{
    public function __construct(
        protected ReportRenderer $reportRenderer
    ) {}

    /**
     * Release final diagnostic report and generate immutable stored PDF.
     */
    public function execute(int $orderId, int $verifiedBy, string $template = 'standard'): LabReport
    {
        return DB::transaction(function () use ($orderId, $verifiedBy, $template) {
            $order = LabOrder::with(['items', 'patient', 'bill'])->lockForUpdate()->findOrFail($orderId);

            $fromState = $order->status->value;
            $order->status = LabState::RELEASED;
            $order->save();

            foreach ($order->items as $item) {
                $item->status = LabState::RELEASED;
                $item->save();
            }

            // Create or update version 1 report
            $report = LabReport::firstOrCreate(
                [
                    'lab_order_id' => $order->id,
                    'version' => 1,
                ],
                [
                    'template' => $template,
                    'verified_by' => $verifiedBy,
                    'released_at' => Carbon::now(),
                ]
            );

            // Generate immutable PDF and store SHA-256
            $this->reportRenderer->renderAndStorePdf($order, $report);

            // Audit log
            LabOrderEvent::create([
                'order_id' => $order->id,
                'from_state' => $fromState,
                'to_state' => LabState::RELEASED->value,
                'user_id' => $verifiedBy,
                'meta' => [
                    'report_id' => $report->id,
                    'version' => $report->version,
                    'pdf_sha256' => $report->pdf_sha256,
                    'released_at' => Carbon::now()->toIso8601String(),
                ],
            ]);

            return $report;
        });
    }
}
