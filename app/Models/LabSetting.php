<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabSetting extends Model
{
    protected $table = 'lab_settings';

    protected $guarded = [];

    protected $casts = [
        'use_letterhead_image' => 'boolean',
        'show_barcode' => 'boolean',
        'show_qr_code' => 'boolean',
        'header_height' => 'integer',
        'footer_height' => 'integer',
        'print_top_margin' => 'integer',
        'print_bottom_margin' => 'integer',
        'print_with_header' => 'boolean',
        'combine_reports_on_print' => 'boolean',
        'sample_no_digits' => 'integer',
        'starting_sample_no' => 'integer',
        'is_sample_series_locked' => 'boolean',
    ];

    protected static ?self $cachedCurrent = null;

    protected static function booted(): void
    {
        static::saved(function () {
            static::$cachedCurrent = null;
        });

        static::deleted(function () {
            static::$cachedCurrent = null;
        });
    }

    public static function clearCache(): void
    {
        static::$cachedCurrent = null;
    }

    /**
     * Get or create the singleton lab settings record.
     */
    public static function current(bool $fresh = false): self
    {
        if (!$fresh && static::$cachedCurrent !== null) {
            return static::$cachedCurrent;
        }

        $record = static::first() ?? static::create([
            'lab_name' => 'Central Hospital Laboratory',
            'tagline' => 'Department of Pathology & Clinical Diagnostics',
            'accreditation_text' => 'Fully Automated Computerized Diagnostic Lab • Accredited Quality Care',
            'gst_number' => '06AAAAA0000A1Z5',
            'reg_number' => 'LAB/HAR/2026/884',
            'mobile_no' => '9416204655',
            'address_line1' => 'Hospital Road',
            'address_line2' => 'City Center',
            'technician_name' => 'Anita',
            'technician_designation' => 'Lab Technician',
            'technician_degree' => 'DMLT',
            'technician_signature_path' => 'assets/files/signatures/sign_anita.png',
            'pathologist_name' => 'Dr. Sonali Kalyan',
            'pathologist_designation' => 'Consultant Pathologist',
            'pathologist_degree' => 'M.B.B.S., MD (Path)',
            'pathologist_signature_path' => 'assets/files/signatures/sign_sonali.png',
            'show_barcode' => true,
            'show_qr_code' => false,
            'combine_reports_on_print' => false,
        ]);

        static::$cachedCurrent = $record;
        return $record;
    }

    /**
     * Resolve file path to an inline base64 Data URI for robust DomPDF & Print rendering.
     */
    public function getBase64Uri(?string $relativePath): string
    {
        if (! $relativePath) {
            return '';
        }

        $cleanPath = ltrim($relativePath, '/\\');
        $fullPath = public_path($cleanPath);
        if (! file_exists($fullPath) || is_dir($fullPath)) {
            // Also check storage/app/public
            $storagePath = storage_path('app/public/' . $cleanPath);
            if (file_exists($storagePath) && ! is_dir($storagePath)) {
                $fullPath = $storagePath;
            } else {
                return '';
            }
        }

        try {
            $mime = mime_content_type($fullPath) ?: 'image/png';
            $data = base64_encode(file_get_contents($fullPath));

            return "data:{$mime};base64,{$data}";
        } catch (\Throwable) {
            return '';
        }
    }

    public function getLogoUri(): string
    {
        return $this->getBase64Uri($this->logo_path);
    }

    public function getHeaderImageUri(): string
    {
        $uri = $this->getBase64Uri($this->header_image_path);
        if (! $uri) {
            try {
                $printSetting = \App\Models\Legacy\HospitalPrintSetting::first();
                if ($printSetting && $printSetting->lab_investigation) {
                    $data = json_decode((string) $printSetting->lab_investigation, true);
                    if (! empty($data['header_image'])) {
                        $uri = $this->getBase64Uri($data['header_image']);
                    }
                }
            } catch (\Throwable) {
            }
        }

        return $uri;
    }

    public function getFooterImageUri(): string
    {
        $uri = $this->getBase64Uri($this->footer_image_path);
        if (! $uri) {
            try {
                $printSetting = \App\Models\Legacy\HospitalPrintSetting::first();
                if ($printSetting && $printSetting->lab_investigation) {
                    $data = json_decode((string) $printSetting->lab_investigation, true);
                    if (! empty($data['footer_image'])) {
                        $uri = $this->getBase64Uri($data['footer_image']);
                    }
                }
            } catch (\Throwable) {
            }
        }

        return $uri;
    }

    public function getTechnicianSignUri(): string
    {
        return $this->getBase64Uri($this->technician_signature_path);
    }

    public function getPathologistSignUri(): string
    {
        return $this->getBase64Uri($this->pathologist_signature_path);
    }
}
