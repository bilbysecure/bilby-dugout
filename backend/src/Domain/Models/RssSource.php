<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RssSource extends Model
{
    protected $table = 'rss_sources';
    protected $guarded = ['id'];
    protected $casts = [
        'default_channel_ids' => 'array',
        'auto_schedule'       => 'boolean',
        'apply_tag_rules'     => 'boolean',
        'last_fetched_at'     => 'datetime',
    ];
}
