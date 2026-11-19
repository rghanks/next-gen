<?php

declare(strict_types=1);

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingPaymentType extends Model
{
    protected $table = 'billing_payment_type';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'bill_id' => 'integer',
        'amount' => 'decimal:2',
        'tds' => 'decimal:2',
        'is_deleted' => 'boolean',
        'payment_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function billing(): BelongsTo
    {
        return $this->belongsTo(PatientBilling::class, 'bill_id', 'id');
    }
}
