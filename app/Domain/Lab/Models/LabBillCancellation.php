<?php

declare(strict_types=1);

namespace App\Domain\Lab\Models;

use App\Models\Legacy\PatientBilling;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabBillCancellation extends Model
{
    protected $table = 'lab_bill_cancellations';

    protected $guarded = [];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(PatientBilling::class, 'bill_id', 'id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class, 'order_id', 'id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }
}
