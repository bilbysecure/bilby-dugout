<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** Per-channel publish target + status for a scheduled post. */
class ScheduledPostTarget extends Model
{
    protected $table = 'scheduled_post_targets';
    protected $guarded = ['id'];
    protected $casts = ['published_at' => 'datetime'];
}
