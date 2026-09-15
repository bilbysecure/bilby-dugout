<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** A queued unit of work. `client_email` carries tenant context to the handler. */
class Job extends Model
{
    protected $table = 'jobs';
    protected $guarded = ['id'];

    protected $casts = [
        'payload'      => 'array',
        'attempts'     => 'integer',
        'max_attempts' => 'integer',
        'available_at' => 'datetime',
        'reserved_at'  => 'datetime',
    ];
}
