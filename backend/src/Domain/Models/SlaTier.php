<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SlaTier extends Model
{
    protected $table = 'sla_tiers';
    protected $guarded = ['id'];
    protected $casts = ['duration_value' => 'integer'];
}
