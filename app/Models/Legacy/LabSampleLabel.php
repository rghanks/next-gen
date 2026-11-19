<?php

declare(strict_types=1);

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class LabSampleLabel extends Model
{
    protected $table = 'lab_sample_labels';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'lab_order_id' => 'integer',
        'is_outsource' => 'boolean',
        'outsource_lab_id' => 'integer',
        'collected_by' => 'integer',
        'collect_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
