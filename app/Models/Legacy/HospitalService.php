<?php

declare(strict_types=1);

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $service
 * @property string|null $short_code
 * @property string|null $comment
 * @property string|null $loinc_code
 * @property string|null $flag
 * @property bool $is_group
 * @property array|string|null $input_attributes
 * @property array|string|null $group_attributes
 */
class HospitalService extends Model
{
    protected $table = 'hospital_services';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'active' => 'integer',
        'is_group' => 'boolean',
        'is_reserved' => 'boolean',
        'is_subgroup' => 'boolean',
        'input_attributes' => 'array',
        'group_attributes' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function groupTests(): HasMany
    {
        return $this->hasMany(InvestigationGroupTestIndex::class, 'group_id', 'id')
            ->orderBy('sequence_no');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(HospitalServiceCharge::class, 'service', 'id');
    }
}
