<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ContentTagRule extends Model
{
    protected $table = 'content_tag_rules';
    protected $guarded = ['id'];
    protected $casts = [
        'add_tags'        => 'array',
        'add_channel_ids' => 'array',
        'enabled'         => 'boolean',
        'priority'        => 'integer',
    ];
}
