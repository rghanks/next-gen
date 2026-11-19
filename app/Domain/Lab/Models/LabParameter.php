<?php

declare(strict_types=1);

namespace App\Domain\Lab\Models;

use App\Models\Legacy\HospitalService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabParameter extends Model
{
    use HasFactory;

    protected $table = 'lab_parameters';

    protected $guarded = [];

    protected $casts = [
        'options' => 'array',
        'decimal_places' => 'integer',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(HospitalService::class, 'service_id', 'id');
    }

    public function referenceRanges(): HasMany
    {
        return $this->hasMany(LabReferenceRange::class, 'parameter_id', 'id');
    }

    public function resultValues(): HasMany
    {
        return $this->hasMany(LabResultValue::class, 'parameter_id', 'id');
    }
}
