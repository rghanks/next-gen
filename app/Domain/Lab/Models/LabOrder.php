<?php

declare(strict_types=1);

namespace App\Domain\Lab\Models;

use App\Domain\Lab\States\LabState;
use App\Models\Legacy\Patient;
use App\Models\Legacy\PatientBilling;
use App\Models\Legacy\VisitLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lab_orders';

    protected $guarded = [];

    protected $casts = [
        'status' => LabState::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'id');
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(VisitLog::class, 'visit_id', 'id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(PatientBilling::class, 'bill_id', 'id');
    }

    public function orderedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by', 'id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LabOrderItem::class, 'lab_order_id', 'id');
    }

    public function specimens(): HasMany
    {
        return $this->hasMany(Specimen::class, 'lab_order_id', 'id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(LabReport::class, 'lab_order_id', 'id')->orderByDesc('version');
    }

    public function events(): HasMany
    {
        return $this->hasMany(LabOrderEvent::class, 'order_id', 'id')->orderBy('id');
    }

    public function latestReport(): HasOne
    {
        return $this->hasOne(LabReport::class, 'lab_order_id', 'id')->latestOfMany('version');
    }
}
