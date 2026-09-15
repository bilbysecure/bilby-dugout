<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A planned social post that can fan out to multiple channels/brands. */
class ScheduledPost extends Model
{
    protected $table = 'scheduled_posts';
    protected $guarded = ['id'];

    protected $casts = [
        'media'             => 'array',
        'product_tags'      => 'array',
        'tags'              => 'array',
        'scheduled_at'      => 'datetime',
        'published_at'      => 'datetime',
        'best_time_applied' => 'boolean',
    ];

    protected $with = ['targets'];

    public function targets(): HasMany
    {
        return $this->hasMany(ScheduledPostTarget::class, 'scheduled_post_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PostApproval::class, 'scheduled_post_id');
    }
}
