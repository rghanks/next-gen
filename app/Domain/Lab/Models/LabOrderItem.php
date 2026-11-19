<?php

declare(strict_types=1);

namespace App\Domain\Lab\Models;

use App\Domain\Lab\States\LabState;
use App\Models\Legacy\HospitalService;
use App\Models\Legacy\PatientGenBill;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabOrderItem extends Model
{
    use HasFactory;

    protected $table = 'lab_order_items';

    protected $guarded = [];

    protected $casts = [
        'status' => LabState::class,
        'tat_due_at' => 'datetime',
        'is_outsource' => 'boolean',
        'row_version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id', 'id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(HospitalService::class, 'service_id', 'id');
    }

    public function legacyGenBill(): BelongsTo
    {
        return $this->belongsTo(PatientGenBill::class, 'gen_bill_id', 'id');
    }

    public function specimens(): BelongsToMany
    {
        return $this->belongsToMany(Specimen::class, 'specimen_items', 'lab_order_item_id', 'specimen_id')
            ->withTimestamps();
    }

    public function resultValues(): HasMany
    {
        return $this->hasMany(LabResultValue::class, 'order_item_id', 'id');
    }
}
