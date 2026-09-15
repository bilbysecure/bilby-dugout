<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $table = 'subscription_plans';
    protected $guarded = ['id'];
    protected $casts = [
        'services'                 => 'array',
        'monthly_request_limit'    => 'integer',
        'brand_kit_allowance'      => 'integer',
        'concurrent_request_limit' => 'integer',
        'price'                    => 'decimal:2',
        'is_default'               => 'boolean',
        'hours'                    => 'integer',
        'credits'                  => 'integer',
        'trial_enabled'            => 'boolean',
        'trial_amount'             => 'decimal:2',
        'trial_period_count'       => 'integer',
        'setup_fee_enabled'        => 'boolean',
        'setup_fee_amount'         => 'decimal:2',
    ];
}
