<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class BrandAssetReview extends Model
{
    protected $table = 'brand_asset_reviews';
    protected $guarded = ['id'];
    protected $casts = [
        'matches'         => 'array',
        'issues'          => 'array',
        'recommendations' => 'array',
        'score'           => 'float',
    ];
}
