<?php

declare(strict_types=1);

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabInvestigation extends Model
{
    protected $table = 'lab_investigations';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'lab_order_id' => 'integer', // 1:1 with patient_gen_bill.id
        'approval_status' => 'integer',
        'is_outsource' => 'boolean',
        'is_printed' => 'boolean',
        'in_process' => 'boolean',
        'test_results' => 'array',
        'sample_collect_time' => 'datetime',
        'in_process_time' => 'datetime',
        'reporting_time' => 'datetime',
        'approval_time' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function genBill(): BelongsTo
    {
        return $this->belongsTo(PatientGenBill::class, 'lab_order_id', 'id');
    }
}
