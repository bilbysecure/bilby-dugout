<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class BrandAsset extends Model
{
    protected $table = 'brand_assets';
    protected $guarded = ['id'];

    /**
     * Files backing this asset, via the polymorphic `media` relation
     * (Master Spec §17.2). Additive: the legacy `file_url` column still works
     * until existing rows are backfilled (pending approval).
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'attachable');
    }
}
