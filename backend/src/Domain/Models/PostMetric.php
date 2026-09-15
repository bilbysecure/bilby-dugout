<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PostMetric extends Model
{
    protected $table = 'post_metrics';
    protected $guarded = ['id'];

    protected $casts = [
        // metric_date stays a plain 'Y-m-d' string so upsert-by-date matches exactly.
        'impressions' => 'integer',
        'reach'       => 'integer',
        'engagement'  => 'integer',
        'likes'       => 'integer',
        'comments'    => 'integer',
        'shares'      => 'integer',
        'saves'       => 'integer',
        'clicks'      => 'integer',
    ];
}
