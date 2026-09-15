<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An ad campaign attached to an ad_campaign project. Tenant key: client_email. */
class Campaign extends Model
{
    protected $table = 'campaigns';
    protected $guarded = ['id'];

    protected $casts = [
        'total_budget' => 'decimal:2',
        'cpl_target'   => 'decimal:2',
    ];

    public function phases(): HasMany
    {
        return $this->hasMany(CampaignPhase::class, 'campaign_id')->orderBy('phase_number');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(CampaignAlert::class, 'campaign_id');
    }
}
