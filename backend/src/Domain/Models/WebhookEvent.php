<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** Raw inbound webhook, written before processing; deduped on (provider, event_id). */
class WebhookEvent extends Model
{
    protected $table = 'webhook_events';
    protected $guarded = ['id'];

    protected $casts = [
        'payload'      => 'array',
        'received_at'  => 'datetime',
        'processed_at' => 'datetime',
    ];
}
