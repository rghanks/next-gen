<?php

declare(strict_types=1);

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $service
 * @property string|null $charges
 * @property bool $is_active
 * @property float|string|null $opd
 * @property float|string|null $er_opd
 */
class HospitalServiceCharge extends Model
{
    protected $table = 'hospital_service_charges';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'hospital_id' => 'integer',
        'insurance' => 'integer',
        'service' => 'integer',
        'is_active' => 'boolean',
        'opd' => 'decimal:2',
        'er_opd' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function serviceRelation(): BelongsTo
    {
        return $this->belongsTo(HospitalService::class, 'service', 'id');
    }
}
