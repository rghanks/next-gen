<?php

declare(strict_types=1);

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class HospitalPrintSetting extends Model
{
    protected $table = 'hospital_print_settings';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'hospital_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
