<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class QuotaNotification extends Model
{
    protected $table = 'quota_notifications';
    protected $guarded = ['id'];
    protected $casts = [
        'threshold'     => 'integer',
        'request_count' => 'integer',
    ];
}
