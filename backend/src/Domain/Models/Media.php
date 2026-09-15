<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An uploaded file. Tenant key is `client_email` (null = agency-owned). `disk`
 * and `path` are storage internals — hidden from API responses so a raw
 * location never leaks; clients receive short-lived signed URLs instead.
 */
class Media extends Model
{
    protected $table = 'media';
    protected $guarded = ['id'];

    protected $casts = [
        'size' => 'integer',
    ];

    /** Storage location is never serialized to clients. */
    protected $hidden = ['disk', 'path'];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Servable only once the async scan has passed. */
    public function isServable(): bool
    {
        return $this->scan_status === 'clean';
    }
}
