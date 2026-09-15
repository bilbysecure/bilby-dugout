<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TimeEntry extends Model
{
    protected $table = 'time_entries';
    protected $guarded = ['id'];
    protected $casts = [
        'seconds'    => 'integer',
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];
}
