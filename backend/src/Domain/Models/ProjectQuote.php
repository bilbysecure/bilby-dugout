<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A quote for a project. Signature + terms snapshot captured on acceptance. */
class ProjectQuote extends Model
{
    protected $table = 'project_quotes';
    protected $guarded = ['id'];

    protected $casts = [
        'line_items'     => 'array',
        'terms_snapshot' => 'array',
        'subtotal'       => 'decimal:2',
        'deposit_pct'    => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'valid_until'    => 'date',
        'accepted_at'    => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
