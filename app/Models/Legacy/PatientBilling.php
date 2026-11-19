<?php

declare(strict_types=1);

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientBilling extends Model
{
    protected $table = 'patient_billing';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'visit_id' => 'integer',
        'bill_number' => 'integer',
        'discount' => 'decimal:2',
        'discount_per' => 'decimal:4',
        'is_settled' => 'boolean',
        'is_cancelled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(VisitLog::class, 'visit_id', 'id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PatientGenBill::class, 'bill_id', 'id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillingPaymentType::class, 'bill_id', 'id');
    }
}
