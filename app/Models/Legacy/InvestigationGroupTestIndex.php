<?php

declare(strict_types=1);

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $test_id
 * @property int $sequence_no
 * @property bool $is_active
 * @property HospitalService|null $test
 */
class InvestigationGroupTestIndex extends Model
{
    protected $table = 'table_investigation_group_test_index';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'group_id' => 'integer',
        'test_id' => 'integer',
        'sequence_no' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(HospitalService::class, 'group_id', 'id');
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(HospitalService::class, 'test_id', 'id');
    }
}
