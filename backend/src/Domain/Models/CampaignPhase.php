<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignPhase extends Model
{
    protected $table = 'campaign_phases';
    protected $guarded = ['id'];

    protected $casts = [
        'phase_number'   => 'integer',
        'budget_split'   => 'decimal:2',
        'meta_adset_ids' => 'array',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(AdVariant::class, 'phase_id');
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(PhaseAudience::class, 'phase_id');
    }
}
