<?php

declare(strict_types=1);

namespace App\Domain\Lab\Models;

use App\Models\Legacy\PatientBilling;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabDiscountApproval extends Model
{
    protected $table = 'lab_discount_approvals';

    protected $guarded = [];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'approved_at' => 'datetime',
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

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by', 'id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }
}
