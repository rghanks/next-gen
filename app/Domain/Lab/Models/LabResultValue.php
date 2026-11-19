<?php

declare(strict_types=1);

namespace App\Domain\Lab\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LabResultValue extends Model
{
    use HasFactory;

    protected $table = 'lab_result_values';

    protected $guarded = [];

    protected $casts = [
        'value_numeric' => 'float',
        'ref_low' => 'float',
        'ref_high' => 'float',
        'version' => 'integer',
        'entered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get clean display value for UI and reports (strips redundant .0000 from decimal(14,4) legacy/storage).
     */
    public function getDisplayValueAttribute(): string
    {
        if ($this->value_text !== null && trim((string) $this->value_text) !== '') {
            $txt = trim((string) $this->value_text);
            if (is_numeric($txt) && str_contains($txt, '.')) {
                $cleaned = rtrim(rtrim($txt, '0'), '.');
                return $cleaned === '' ? '0' : $cleaned;
            }
            return $txt;
        }

        if ($this->value_numeric !== null) {
            $num = (float) $this->value_numeric;
            return (string) $num;
        }

        return '';
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(LabOrderItem::class, 'order_item_id', 'id');
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(LabParameter::class, 'parameter_id', 'id');
    }

    public function enteredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by', 'id');
    }

    public function criticalAcknowledgement(): HasOne
    {
        return $this->hasOne(LabCriticalAcknowledgement::class, 'result_value_id', 'id');
    }

    public function isCritical(): bool
    {
        return in_array($this->flag, ['LL', 'HH'], true);
    }
}
