<?php

declare(strict_types=1);

namespace App\Domain\Lab\Models;

use Illuminate\Database\Eloquent\Model;

class LabBillSequence extends Model
{
    protected $table = 'lab_bill_sequences';

    protected $guarded = [];

    protected $casts = [
        'last_number' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
