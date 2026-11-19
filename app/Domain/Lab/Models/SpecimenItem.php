<?php

declare(strict_types=1);

namespace App\Domain\Lab\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SpecimenItem extends Pivot
{
    protected $table = 'specimen_items';

    public $incrementing = true;
}
