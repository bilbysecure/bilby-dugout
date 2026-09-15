<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignMetricDaily extends Model
{
    protected $table = 'campaign_metrics_daily';
    protected $guarded = ['id'];

    protected $casts = [
        // metric_date stays a plain 'Y-m-d' string so upsert-by-date matches exactly.
        // Money/rate columns are left uncast: SQLite stores them as REAL, and a
        // decimal cast would push that float through brick/math on every read.
        'impressions' => 'integer',
        'reach'       => 'integer',
        'clicks'      => 'integer',
        'leads'       => 'integer',
    ];
}
