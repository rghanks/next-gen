<?php

declare(strict_types=1);

namespace App\Domain\Lab\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabCriticalAcknowledgement extends Model
{
    use HasFactory;

    protected $table = 'lab_critical_acknowledgements';

    protected $guarded = [];

    protected $casts = [
        'informed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function resultValue(): BelongsTo
    {
        return $this->belongsTo(LabResultValue::class, 'result_value_id', 'id');
    }

    public function informedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'informed_by', 'id');
    }
}
