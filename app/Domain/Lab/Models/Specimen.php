<?php

declare(strict_types=1);

namespace App\Domain\Lab\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Specimen extends Model
{
    use HasFactory;

    protected $table = 'specimens';

    protected $guarded = [];

    protected $casts = [
        'collected_at' => 'datetime',
        'received_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id', 'id');
    }

    public function collectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by', 'id');
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by', 'id');
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(LabOrderItem::class, 'specimen_items', 'specimen_id', 'lab_order_item_id')
            ->withTimestamps();
    }
}
