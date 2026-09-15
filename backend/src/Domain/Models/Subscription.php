<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $table = 'subscriptions';
    protected $guarded = ['id'];
    protected $casts = [
        'services'               => 'array',
        'account_managers'       => 'array',
        'require_owner_approval' => 'boolean',
        'monthly_request_limit'  => 'integer',
        'start_date'             => 'date',
        'renewal_date'           => 'date',
    ];

    /** Stripe customer id must never be serialized to client-facing responses. */
    protected $hidden = ['stripe_customer_id'];
}
