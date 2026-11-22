<?php

declare(strict_types=1);

namespace App\Domain\Lab\Services;

use App\Domain\Lab\Models\LabBillSequence;
use App\Domain\Lab\Models\Specimen;
use App\Models\LabSetting;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AccessionGenerator
{
    /**
     * Generate an accession number (e.g. "ACC-20261114-000001").
     */
    public function generate(?Carbon $date = null): string
    {
        $date = $date ?? Carbon::now();
        $dateKey = $date->format('Ymd');
        $prefix = 'ACC-'.$dateKey;

        $sequence = LabBillSequence::where('prefix', $prefix)
            ->where('financial_year', $dateKey)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $sequence = LabBillSequence::create([
                'prefix' => $prefix,
                'financial_year' => $dateKey,
                'last_number' => 0,
            ]);
            $sequence = LabBillSequence::where('id', $sequence->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $sequence->last_number += 1;
        $sequence->save();

        return sprintf('%s-%05d', $prefix, $sequence->last_number);
    }

    /**
     * Generate a unique specimen barcode / sample number (e.g. "SMP-000001").
     * If $customBarcode is provided, it validates uniqueness, uses that sample number,
     * and restarts the sequence series from that number.
     */
    public function generateBarcode(string $specimenType = 'blood', ?Carbon $date = null, ?string $customBarcode = null): string
    {
        $setting = LabSetting::current();
        $prefix = trim((string) ($setting?->sample_no_prefix ?? ''));
        $cleanPrefix = rtrim($prefix, '-');
        $seqKey = $cleanPrefix !== '' ? $cleanPrefix : 'SAMPLE';
        $resetPeriod = (string) ($setting?->sample_no_reset_period ?? 'continuous');
        $fy = $resetPeriod === 'daily' ? ($date ?? Carbon::now())->format('Ymd') : 'GLOBAL';

        $sequence = LabBillSequence::where('prefix', $seqKey)
            ->where('financial_year', $fy)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $sequence = LabBillSequence::create([
                'prefix' => $seqKey,
                'financial_year' => $fy,
                'last_number' => 0,
            ]);
            $sequence = LabBillSequence::where('id', $sequence->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        if ($customBarcode !== null && trim($customBarcode) !== '') {
            $trimmed = trim($customBarcode);
            $parsed = $this->parseBarcode($trimmed, $setting);
            $finalBarcode = $parsed['barcode'];
            $number = $parsed['number'];

            $exists = Specimen::where('barcode', $finalBarcode)->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'custom_sample_no' => ["Sample number '{$finalBarcode}' is already assigned to another specimen. Please choose a different number."],
                ]);
            }

            $sequence->last_number = $number;
            $sequence->save();

            return $finalBarcode;
        }

        $sequence->last_number += 1;
        $sequence->save();

        return $this->formatBarcode($sequence->last_number, $setting);
    }

    /**
     * Preview the next sample number without incrementing the sequence counter.
     */
    public function previewNextBarcode(?Carbon $date = null): string
    {
        $setting = LabSetting::current();
        $prefix = trim((string) ($setting?->sample_no_prefix ?? ''));
        $cleanPrefix = rtrim($prefix, '-');
        $seqKey = $cleanPrefix !== '' ? $cleanPrefix : 'SAMPLE';
        $resetPeriod = (string) ($setting?->sample_no_reset_period ?? 'continuous');
        $fy = $resetPeriod === 'daily' ? ($date ?? Carbon::now())->format('Ymd') : 'GLOBAL';

        $sequence = LabBillSequence::where('prefix', $seqKey)
            ->where('financial_year', $fy)
            ->first();

        $nextNumber = ($sequence?->last_number ?? 0) + 1;

        return $this->formatBarcode($nextNumber, $setting);
    }

    /**
     * Format a sample number according to active lab settings.
     */
    public function formatBarcode(int $number, ?LabSetting $setting = null): string
    {
        $setting = $setting ?? LabSetting::current();
        $prefix = trim((string) ($setting?->sample_no_prefix ?? ''));
        $cleanPrefix = rtrim($prefix, '-');
        $digits = (int) ($setting?->sample_no_digits ?? 0);
        $format = (string) ($setting?->sample_no_format ?? 'prefix_unpadded');

        if ($digits > 0 && $format !== 'prefix_unpadded') {
            $numStr = sprintf('%0'.$digits.'d', $number);
        } else {
            $numStr = (string) $number;
        }

        if ($cleanPrefix !== '') {
            return sprintf('%s-%s', $cleanPrefix, $numStr);
        }

        return $numStr;
    }

    /**
     * Parse any custom input string into numeric suffix, prefix, and formatted barcode.
     * Handles inputs like "500", "SMP-000500", "SMP-500", "LAB-120", "101".
     *
     * @return array{number: int, prefix: string, barcode: string}
     */
    public function parseBarcode(string $input, ?LabSetting $setting = null): array
    {
        $setting = $setting ?? LabSetting::current();
        $input = trim($input);

        // Check if input has digits at the end
        if (preg_match('/^(.*?)(0*(\d+))$/', $input, $matches)) {
            $extractedPrefix = trim($matches[1]);
            $numericVal = (int) $matches[3];

            // If user did not provide a prefix (e.g. entered "500")
            if ($extractedPrefix === '') {
                $finalBarcode = $this->formatBarcode($numericVal, $setting);
                return [
                    'number' => $numericVal,
                    'prefix' => $setting?->sample_no_prefix ?? '',
                    'barcode' => $finalBarcode,
                ];
            }

            // User provided prefix (e.g. "SMP-", "LAB-", etc.)
            return [
                'number' => $numericVal,
                'prefix' => rtrim($extractedPrefix, '-'),
                'barcode' => $input,
            ];
        }

        // If purely non-numeric text was entered, use hash or current sequence + 1
        return [
            'number' => 1,
            'prefix' => '',
            'barcode' => $input,
        ];
    }

    /**
     * Set the next starting number for the sample series.
     */
    public function setNextSampleNumber(int $nextNumber, ?string $prefix = null, ?Carbon $date = null): void
    {
        $setting = LabSetting::current();
        $prefix = $prefix !== null ? trim($prefix) : trim((string) ($setting?->sample_no_prefix ?? ''));
        $cleanPrefix = rtrim($prefix, '-');
        $seqKey = $cleanPrefix !== '' ? $cleanPrefix : 'SAMPLE';
        $resetPeriod = (string) ($setting?->sample_no_reset_period ?? 'continuous');
        $fy = $resetPeriod === 'daily' ? ($date ?? Carbon::now())->format('Ymd') : 'GLOBAL';

        $lastNumber = max(0, $nextNumber - 1);

        $sequence = LabBillSequence::where('prefix', $seqKey)
            ->where('financial_year', $fy)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            LabBillSequence::create([
                'prefix' => $seqKey,
                'financial_year' => $fy,
                'last_number' => $lastNumber,
            ]);
        } else {
            $sequence->last_number = $lastNumber;
            $sequence->save();
        }
    }
}
