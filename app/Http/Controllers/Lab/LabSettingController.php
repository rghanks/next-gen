<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use App\Models\LabSetting;
use App\Models\Legacy\HospitalPrintSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class LabSettingController extends Controller
{
    /**
     * Show Laboratory Profile & Print Configuration Desk.
     */
    public function index(): View
    {
        $setting = LabSetting::current();
        $accessionGen = app(\App\Domain\Lab\Services\AccessionGenerator::class);
        $nextSampleBarcode = $accessionGen->previewNextBarcode();

        return view('lab.settings.index', [
            'setting' => $setting,
            'nextSampleBarcode' => $nextSampleBarcode,
        ]);
    }

    /**
     * Update laboratory settings, visual branding, and signatures.
     */
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'lab_name' => ['required', 'string', 'max:191'],
            'tagline' => ['nullable', 'string', 'max:191'],
            'accreditation_text' => ['nullable', 'string', 'max:255'],
            'gst_number' => ['nullable', 'string', 'max:50'],
            'reg_number' => ['nullable', 'string', 'max:50'],
            'mobile_no' => ['nullable', 'string', 'max:30'],
            'phone_alt' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:100'],
            'website' => ['nullable', 'string', 'max:150'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],

            'technician_name' => ['required', 'string', 'max:100'],
            'technician_designation' => ['required', 'string', 'max:100'],
            'technician_degree' => ['nullable', 'string', 'max:100'],

            'pathologist_name' => ['required', 'string', 'max:100'],
            'pathologist_designation' => ['required', 'string', 'max:100'],
            'pathologist_degree' => ['nullable', 'string', 'max:100'],

            'show_barcode' => ['nullable', 'boolean'],
            'show_qr_code' => ['nullable', 'boolean'],
            'use_letterhead_image' => ['nullable', 'boolean'],
            'header_height' => ['nullable', 'integer', 'min:30', 'max:300'],
            'footer_height' => ['nullable', 'integer', 'min:20', 'max:200'],

            'print_top_margin' => ['nullable', 'integer', 'min:0', 'max:150'],
            'print_bottom_margin' => ['nullable', 'integer', 'min:0', 'max:150'],
            'print_with_header' => ['nullable', 'boolean'],
            'combine_reports_on_print' => ['nullable', 'boolean'],

            'sample_no_prefix' => ['nullable', 'string', 'max:20'],
            'sample_no' => ['nullable', 'integer', 'min:1'],
            'sample_no_digits' => ['nullable', 'integer', 'min:0', 'max:12'],
            'sample_no_format' => ['nullable', 'string', 'in:prefix_padded,prefix_unpadded,numeric_only'],
            'sample_no_reset_period' => ['nullable', 'string', 'in:continuous,daily'],
            'start_series_number' => ['nullable', 'integer', 'min:1'],

            'report_disclaimer' => ['nullable', 'string', 'max:1000'],
            'receipt_footer_note' => ['nullable', 'string', 'max:1000'],

            // Uploaded images
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:3072'],
            'header_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'footer_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'technician_signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:3072'],
            'pathologist_signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:3072'],
        ]);

        $setting = LabSetting::current();

        $uploadDir = public_path('assets/files/lab');
        if (! File::exists($uploadDir)) {
            File::makeDirectory($uploadDir, 0755, true);
        }

        // 1. Handle File Uploads
        $uploadFields = [
            'logo' => 'logo_path',
            'header_image' => 'header_image_path',
            'footer_image' => 'footer_image_path',
            'technician_signature' => 'technician_signature_path',
            'pathologist_signature' => 'pathologist_signature_path',
        ];

        foreach ($uploadFields as $inputKey => $dbCol) {
            if ($request->hasFile($inputKey) && $request->file($inputKey)->isValid()) {
                $file = $request->file($inputKey);
                $ext = $file->getClientOriginalExtension() ?: 'png';
                $filename = "{$inputKey}_" . time() . '_' . substr(md5(uniqid()), 0, 6) . ".{$ext}";
                $file->move($uploadDir, $filename);
                $validated[$dbCol] = "assets/files/lab/{$filename}";
            }
        }

        // 2. Normalize Booleans
        $validated['show_barcode'] = $request->boolean('show_barcode', true);
        $validated['show_qr_code'] = $request->boolean('show_qr_code', true);
        $validated['combine_reports_on_print'] = $request->boolean('combine_reports_on_print', false);
        if ($request->hasFile('header_image') || $request->hasFile('footer_image')) {
            $validated['use_letterhead_image'] = true;
            $validated['print_with_header'] = true;
        } else {
            $validated['use_letterhead_image'] = $request->boolean('use_letterhead_image', false);
            $validated['print_with_header'] = $request->boolean('print_with_header', false);
        }

        // Handle One-Time Sample Series Setup: locked once configured
        if ($setting->is_sample_series_locked) {
            unset($validated['sample_no_prefix'], $validated['sample_no'], $validated['start_series_number']);
        } else {
            $startingSampleNo = $request->input('sample_no') ?? $request->input('start_series_number');
            if ($startingSampleNo !== null && $startingSampleNo !== '') {
                $rawStr = trim((string) $startingSampleNo);
                $startNum = (int) $rawStr;
                $prefix = $request->filled('sample_no_prefix') ? trim((string) $request->input('sample_no_prefix')) : null;

                $hasLeadingZero = str_starts_with($rawStr, '0') && strlen($rawStr) > strlen((string) $startNum);
                $digits = $hasLeadingZero ? strlen($rawStr) : 0;
                $format = $hasLeadingZero ? 'prefix_padded' : 'prefix_unpadded';

                $validated['sample_no_prefix'] = $prefix;
                $validated['starting_sample_no'] = $startNum;
                $validated['sample_no_digits'] = $digits;
                $validated['sample_no_format'] = $format;
                $validated['is_sample_series_locked'] = true;

                $accessionGen = app(\App\Domain\Lab\Services\AccessionGenerator::class);
                $accessionGen->setNextSampleNumber($startNum, $prefix);

                // Sync pending uncollected orders to start from configured series
                $uncollected = \App\Domain\Lab\Models\LabOrder::where('status', \App\Domain\Lab\States\LabState::ORDERED->value)
                    ->with('specimens')
                    ->get();
                $cur = $startNum;
                $tempSetting = clone $setting;
                $tempSetting->sample_no_prefix = $prefix;
                $tempSetting->sample_no_digits = $digits;
                $tempSetting->sample_no_format = $format;

                foreach ($uncollected as $unOrder) {
                    foreach ($unOrder->specimens as $sp) {
                        $sp->barcode = $accessionGen->formatBarcode($cur, $tempSetting);
                        $sp->save();
                        $cur++;
                    }
                }
                if ($cur > $startNum) {
                    $accessionGen->setNextSampleNumber($cur, $prefix);
                }
            }
        }

        // Remove non-table fields from database fields array
        unset(
            $validated['logo'],
            $validated['header_image'],
            $validated['footer_image'],
            $validated['technician_signature'],
            $validated['pathologist_signature'],
            $validated['sample_no'],
            $validated['start_series_number']
        );

        $setting->update($validated);

        // Sync legacy table hospital_print_settings
        try {
            $printSetting = HospitalPrintSetting::first();
            if ($printSetting) {
                $payload = [
                    'header_image' => $setting->header_image_path,
                    'footer_image' => $setting->footer_image_path,
                    'header_image_size' => (string) $setting->header_height,
                    'footer_image_size' => (string) $setting->footer_height,
                    'top_margin' => (string) $setting->print_top_margin,
                    'bottom_margin' => (string) $setting->print_bottom_margin,
                    'signatures' => [
                        'DEFAULT' => [
                            [
                                'signature' => $setting->pathologist_signature_path,
                                'sign_auth' => $setting->pathologist_name,
                                'sign_auth_post' => $setting->pathologist_designation . ($setting->pathologist_degree ? ' (' . $setting->pathologist_degree . ')' : ''),
                            ],
                            [
                                'signature' => $setting->technician_signature_path,
                                'sign_auth' => $setting->technician_name,
                                'sign_auth_post' => $setting->technician_designation,
                            ],
                        ],
                    ],
                ];
                $printSetting->lab_investigation = json_encode($payload);
                $printSetting->save();
            }
        } catch (\Throwable) {
            // Non-blocking legacy sync
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Laboratory settings updated successfully.',
                'setting' => $setting,
            ]);
        }

        return redirect()->route('lab.settings.index')->with('success', 'Laboratory settings updated successfully.');
    }

    /**
     * Dedicated Quick Update for Print & Margin Settings (AJAX-friendly from Orders desk).
     */
    public function updatePrintSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'print_top_margin' => ['required', 'integer', 'min:0', 'max:150'],
            'print_bottom_margin' => ['required', 'integer', 'min:0', 'max:150'],
            'print_with_header' => ['nullable', 'boolean'],
            'combine_reports_on_print' => ['nullable', 'boolean'],
            'use_letterhead_image' => ['nullable', 'boolean'],
            'header_height' => ['nullable', 'integer', 'min:30', 'max:300'],
            'header_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'footer_height' => ['nullable', 'integer', 'min:20', 'max:200'],
            'footer_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ]);

        $setting = LabSetting::current();

        $uploadDir = public_path('assets/files/lab');
        if (! File::exists($uploadDir)) {
            File::makeDirectory($uploadDir, 0755, true);
        }

        if ($request->hasFile('header_image') && $request->file('header_image')->isValid()) {
            $file = $request->file('header_image');
            $ext = $file->getClientOriginalExtension() ?: 'png';
            $filename = "header_image_" . time() . '_' . substr(md5(uniqid()), 0, 6) . ".{$ext}";
            $file->move($uploadDir, $filename);
            $validated['header_image_path'] = "assets/files/lab/{$filename}";
            $validated['use_letterhead_image'] = true;
        }

        if ($request->hasFile('footer_image') && $request->file('footer_image')->isValid()) {
            $file = $request->file('footer_image');
            $ext = $file->getClientOriginalExtension() ?: 'png';
            $filename = "footer_image_" . time() . '_' . substr(md5(uniqid()), 0, 6) . ".{$ext}";
            $file->move($uploadDir, $filename);
            $validated['footer_image_path'] = "assets/files/lab/{$filename}";
        }

        if ($request->hasFile('header_image') || $request->hasFile('footer_image')) {
            $validated['use_letterhead_image'] = true;
            $validated['print_with_header'] = true;
        } else {
            $validated['print_with_header'] = $request->boolean('print_with_header', false);
            if ($request->has('use_letterhead_image')) {
                $validated['use_letterhead_image'] = $request->boolean('use_letterhead_image');
            }
        }

        if ($request->has('combine_reports_on_print')) {
            $validated['combine_reports_on_print'] = $request->boolean('combine_reports_on_print');
        }

        unset($validated['header_image'], $validated['footer_image']);

        $setting->update($validated);

        // Sync legacy table
        try {
            $printSetting = HospitalPrintSetting::first();
            if ($printSetting) {
                $existing = json_decode((string) $printSetting->lab_investigation, true) ?: [];
                $existing['top_margin'] = (string) $setting->print_top_margin;
                $existing['bottom_margin'] = (string) $setting->print_bottom_margin;
                if ($setting->header_image_path) {
                    $existing['header_image'] = $setting->header_image_path;
                }
                if ($setting->header_height) {
                    $existing['header_image_size'] = (string) $setting->header_height;
                }
                if ($setting->footer_image_path) {
                    $existing['footer_image'] = $setting->footer_image_path;
                }
                if ($setting->footer_height) {
                    $existing['footer_image_size'] = (string) $setting->footer_height;
                }
                $printSetting->lab_investigation = json_encode($existing);
                $printSetting->save();
            }
        } catch (\Throwable) {
            // Ignore legacy sync failures
        }

        return response()->json([
            'success' => true,
            'message' => 'Print margin and header/footer settings saved successfully!',
            'setting' => $setting,
            'header_image_url' => $setting->header_image_path ? asset($setting->header_image_path) : null,
            'footer_image_url' => $setting->footer_image_path ? asset($setting->footer_image_path) : null,
        ]);
    }

    /**
     * One-time lock and initialization of sample number series from Settings.
     */
    public function lockSampleSeries(Request $request): JsonResponse
    {
        $setting = LabSetting::current();
        if ($setting->is_sample_series_locked) {
            return response()->json([
                'success' => false,
                'message' => 'Sample number series is already configured and locked.',
            ], 422);
        }

        $validated = $request->validate([
            'sample_no' => ['required'],
            'sample_no_prefix' => ['nullable', 'string', 'max:20'],
        ]);

        $prefix = isset($validated['sample_no_prefix']) && trim((string) $validated['sample_no_prefix']) !== ''
            ? trim((string) $validated['sample_no_prefix'])
            : null;

        $rawStr = trim((string) $validated['sample_no']);
        $startingNo = (int) $rawStr;

        $hasLeadingZero = str_starts_with($rawStr, '0') && strlen($rawStr) > strlen((string) $startingNo);
        $digits = $hasLeadingZero ? strlen($rawStr) : 0;
        $format = $hasLeadingZero ? 'prefix_padded' : 'prefix_unpadded';

        $setting->sample_no_prefix = $prefix;
        $setting->starting_sample_no = $startingNo;
        $setting->sample_no_digits = $digits;
        $setting->sample_no_format = $format;
        $setting->is_sample_series_locked = true;
        $setting->save();

        $accessionGen = app(\App\Domain\Lab\Services\AccessionGenerator::class);
        $accessionGen->setNextSampleNumber($startingNo, $prefix);

        // Sync pending uncollected orders so they immediately show the configured sample series
        $uncollected = \App\Domain\Lab\Models\LabOrder::where('status', \App\Domain\Lab\States\LabState::ORDERED->value)
            ->with('specimens')
            ->get();
        $cur = $startingNo;
        foreach ($uncollected as $unOrder) {
            foreach ($unOrder->specimens as $sp) {
                $sp->barcode = $accessionGen->formatBarcode($cur, $setting);
                $sp->save();
                $cur++;
            }
        }
        if ($cur > $startingNo) {
            $accessionGen->setNextSampleNumber($cur, $prefix);
        }

        $nextPreview = $accessionGen->previewNextBarcode();

        return response()->json([
            'success' => true,
            'message' => "Sample number series locked! Starting from {$startingNo}. Next sample will be {$nextPreview}.",
            'next_sample_no' => $nextPreview,
            'is_locked' => true,
            'starting_sample_no' => $startingNo,
            'sample_no_prefix' => $prefix,
        ]);
    }
}
