<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignAlert extends Model
{
    protected $table = 'campaign_alerts';
    protected $guarded = ['id'];

    protected $casts = [
        // threshold is intentionally uncast — it holds mixed scales (a $ CPL target
        // vs a 0.15 pacing ratio), so a fixed decimal cast would be wrong.
        'window_days' => 'integer',
        'enabled'     => 'boolean',
    ];
}
