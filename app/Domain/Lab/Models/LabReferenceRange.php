<?php

declare(strict_types=1);

namespace App\Domain\Lab\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabReferenceRange extends Model
{
    use HasFactory;

    protected $table = 'lab_reference_ranges';

    protected $guarded = [];

    protected $casts = [
        'age_from_days' => 'integer',
        'age_to_days' => 'integer',
        'low' => 'decimal:4',
        'high' => 'decimal:4',
        'critical_low' => 'decimal:4',
        'critical_high' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(LabParameter::class, 'parameter_id', 'id');
    }
}
