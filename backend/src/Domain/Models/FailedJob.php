<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** Dead-lettered job — retained for inspection and replay. */
class FailedJob extends Model
{
    protected $table = 'failed_jobs';
    protected $guarded = ['id'];

    protected $casts = [
        'payload'   => 'array',
        'attempts'  => 'integer',
        'failed_at' => 'datetime',
    ];
}
