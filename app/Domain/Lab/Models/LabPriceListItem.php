<?php

declare(strict_types=1);

namespace App\Domain\Lab\Models;

use App\Models\Legacy\HospitalService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabPriceListItem extends Model
{
    use HasFactory;

    protected $table = 'lab_price_list_items';

    protected $guarded = [];

    protected $casts = [
        'charge' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(LabPriceList::class, 'price_list_id', 'id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(HospitalService::class, 'service_id', 'id');
    }
}
