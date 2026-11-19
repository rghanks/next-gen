<?php

declare(strict_types=1);

namespace App\Domain\Lab\Services;

use App\Domain\Lab\Models\LabBillSequence;
use Carbon\Carbon;

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
     * Generate a compact unique specimen barcode (e.g. "SMP-000001").
     */
    public function generateBarcode(string $specimenType = 'blood', ?Carbon $date = null): string
    {
        $prefix = 'SMP';

        $sequence = LabBillSequence::where('prefix', $prefix)
            ->where('financial_year', 'GLOBAL')
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $sequence = LabBillSequence::create([
                'prefix' => $prefix,
                'financial_year' => 'GLOBAL',
                'last_number' => 0,
            ]);
            $sequence = LabBillSequence::where('id', $sequence->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $sequence->last_number += 1;
        $sequence->save();

        return sprintf('SMP-%06d', $sequence->last_number);
    }
}
