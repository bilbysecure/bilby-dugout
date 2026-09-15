<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A one-off project. Tenant key is `client_email`. */
class Project extends Model
{
    protected $table = 'projects';
    protected $guarded = ['id'];

    protected $casts = [
        'scope' => 'array',
        'price' => 'decimal:2',
    ];

    public function quotes(): HasMany
    {
        return $this->hasMany(ProjectQuote::class, 'project_id');
    }
}
