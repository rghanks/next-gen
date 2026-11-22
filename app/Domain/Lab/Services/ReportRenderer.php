<?php

declare(strict_types=1);

namespace App\Domain\Lab\Services;

use App\Domain\Lab\Models\LabOrder;
use App\Domain\Lab\Models\LabReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Picqer\Barcode\BarcodeGeneratorPNG;

class ReportRenderer
{
    /**
     * Generate 1D barcode as base64 PNG data URI.
     */
    public function generateBarcodeUri(string $code): string
    {
        try {
            $generator = new BarcodeGeneratorPNG;
            $barcodeData = $generator->getBarcode($code, $generator::TYPE_CODE_128, 2, 35);

            return 'data:image/png;base64,'.base64_encode($barcodeData);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Generate QR code for verification URL as base64 PNG data URI.
     */
    public function generateQrCodeUri(string $url): string
    {
        try {
            $qr = new QrCode($url);
            $writer = new PngWriter;
            $result = $writer->write($qr);

            return $result->getDataUri();
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Render the report HTML for an order and report version.
     */
    public function renderHtml(
        LabOrder $order,
        LabReport $report,
        bool $withLetterhead = true,
        bool $isPdf = false,
        ?int $topMargin = null,
        ?int $bottomMargin = null,
        ?int $selectedItemId = null,
        ?bool $combineReports = null
    ): string {
        $order->loadMissing([
            'patient',
            'visit',
            'items.service',
            'items.resultValues.parameter',
            'items.specimens',
            'specimens',
        ]);

        $report->loadMissing(['verifiedByUser']);

        $verifyUrl = URL::signedRoute('lab.reports.verify-public', [
            'order' => $order->id,
            'version' => $report->version ?? 1,
        ]);

        $barcodeUri = $this->generateBarcodeUri($order->accession_no);
        $qrCodeUri = $this->generateQrCodeUri($verifyUrl);

        $labSetting = \App\Models\LabSetting::current();
        $hasHeader = $withLetterhead && !empty($labSetting->getHeaderImageUri());
        $hasFooter = $withLetterhead && !empty($labSetting->getFooterImageUri());
        $topMargin = $topMargin ?? ($hasHeader ? 0 : ($withLetterhead ? 4 : ($labSetting->print_top_margin ?? 35)));
        $bottomMargin = $bottomMargin ?? ($hasFooter ? 0 : ($withLetterhead ? 4 : ($labSetting->print_bottom_margin ?? 20)));
        $combineReports = $combineReports ?? (bool) ($labSetting->combine_reports_on_print ?? false);

        /** @var view-string $viewName */
        $viewName = 'lab.reports.templates.report_layout';

        return view($viewName, [
            'order' => $order,
            'report' => $report,
            'withLetterhead' => $withLetterhead,
            'barcodeUri' => $barcodeUri,
            'qrCodeUri' => $qrCodeUri,
            'verifyUrl' => $verifyUrl,
            'isPdf' => $isPdf,
            'topMargin' => $topMargin,
            'bottomMargin' => $bottomMargin,
            'labSetting' => $labSetting,
            'selectedItemId' => $selectedItemId,
            'combineReports' => $combineReports,
        ])->render();
    }

    /**
     * Generate PDF binary content, store in storage, and calculate SHA-256 hash.
     *
     * @return array{path: string, sha256: string}
     */
    public function renderAndStorePdf(
        LabOrder $order,
        LabReport $report,
        bool $withLetterhead = true,
        ?int $topMargin = null,
        ?int $bottomMargin = null,
        string $variant = 'download',
        ?int $selectedItemId = null,
        ?bool $combineReports = null
    ): array {
        $html = $this->renderHtml($order, $report, $withLetterhead, true, $topMargin, $bottomMargin, $selectedItemId, $combineReports);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('defaultFont', 'sans-serif');

        $pdfOutput = $pdf->output();
        $sha256 = hash('sha256', $pdfOutput);

        $itemSuffix = $selectedItemId ? "_item{$selectedItemId}" : '';
        $combineSuffix = ($combineReports !== null) ? ($combineReports ? '_cmb' : '_sep') : '';
        $relativePath = sprintf('lab_reports/%d/report_v%d_%s%s%s.pdf', $order->id, $report->version, $variant, $itemSuffix, $combineSuffix);
        Storage::disk('local')->put($relativePath, $pdfOutput);

        if ($variant === 'download' && ! $selectedItemId) {
            $report->pdf_path = $relativePath;
            $report->pdf_sha256 = $sha256;
            $report->save();
        }

        return [
            'path' => $relativePath,
            'sha256' => $sha256,
        ];
    }
}
