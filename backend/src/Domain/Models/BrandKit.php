<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class BrandKit extends Model
{
    protected $table = 'brand_kits';
    protected $guarded = ['id'];
    protected $casts = [
        'colors'     => 'array',
        'typography' => 'array',
    ];
}
