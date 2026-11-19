<?php

declare(strict_types=1);

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReferringDoctor extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'referring_doctors';

    protected $fillable = [
        'name',
        'mobile_no',
        'degree',
        'hospital_name',
        'commission_percent',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'commission_percent' => 'decimal:2',
    ];
}
