<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $table = 'reports';
    protected $guarded = ['id'];

    protected $casts = [
        'params'      => 'array',
        'white_label' => 'boolean',
    ];

    public const TYPES = ['social_performance', 'campaign_wrapup', 'delivery_summary'];
}
