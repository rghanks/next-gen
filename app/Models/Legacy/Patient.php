<?php

declare(strict_types=1);

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $prefix
 * @property string $Fname
 * @property string|null $Lname
 * @property int $age
 * @property string $sex
 * @property int|null $mobile_no
 * @property string|null $uhid
 * @property string|null $abha_number
 * @property string|null $abha_address
 */
class Patient extends Model
{
    protected $table = 'pi';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'age' => 'integer',
        'dob' => 'date',
        'is_dob_calc' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Patient $patient) {
            if (empty($patient->uhid)) {
                $patient->uhid = static::generateNextUhid();
            }
        });
    }

    /**
     * Generate the next sequential UHID integer.
     */
    public static function generateNextUhid(): int
    {
        $sequence = \App\Domain\Lab\Models\LabBillSequence::where('prefix', 'UHID')
            ->where('financial_year', 'GLOBAL')
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $maxUhid = (int) (\Illuminate\Support\Facades\DB::table('pi')->where('uhid', '<', 1000000)->max('uhid') ?: 0);
            $sequence = \App\Domain\Lab\Models\LabBillSequence::create([
                'prefix' => 'UHID',
                'financial_year' => 'GLOBAL',
                'last_number' => $maxUhid,
            ]);
            $sequence = \App\Domain\Lab\Models\LabBillSequence::where('id', $sequence->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $sequence->last_number += 1;
        $sequence->save();

        return (int) $sequence->last_number;
    }

    public function getFullNameAttribute(): string
    {
        $prefix = $this->prefix ? trim($this->prefix).' ' : '';
        $lname = $this->Lname ? ' '.trim($this->Lname) : '';

        return trim($prefix.$this->Fname.$lname);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(VisitLog::class, 'patient_id', 'id');
    }

    public function billings(): HasMany
    {
        return $this->hasMany(PatientBilling::class, 'visit_id', 'id');
    }
}
