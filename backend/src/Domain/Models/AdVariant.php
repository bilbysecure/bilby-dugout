<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class AdVariant extends Model
{
    protected $table = 'ad_variants';
    protected $guarded = ['id'];

    protected $casts = ['is_control' => 'boolean'];
}
