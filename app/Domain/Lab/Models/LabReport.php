<?php

declare(strict_types=1);

namespace App\Domain\Lab\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabReport extends Model
{
    use HasFactory;

    protected $table = 'lab_reports';

    protected $guarded = [];

    protected $casts = [
        'version' => 'integer',
        'released_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id', 'id');
    }

    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by', 'id');
    }

    public function amendedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'amended_from_id', 'id');
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(self::class, 'amended_from_id', 'id');
    }

    public function isAmended(): bool
    {
        return $this->amended_from_id !== null || $this->version > 1;
    }
}
