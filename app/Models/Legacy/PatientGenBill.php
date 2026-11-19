<?php

declare(strict_types=1);

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PatientGenBill extends Model
{
    protected $table = 'patient_gen_bill';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'bill_id' => 'integer',
        'service_id' => 'integer',
        'charges' => 'decimal:2',
        'qty' => 'decimal:2',
        'discount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function billing(): BelongsTo
    {
        return $this->belongsTo(PatientBilling::class, 'bill_id', 'id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(HospitalService::class, 'service_id', 'id');
    }

    public function investigation(): HasOne
    {
        return $this->hasOne(LabInvestigation::class, 'lab_order_id', 'id');
    }
}
