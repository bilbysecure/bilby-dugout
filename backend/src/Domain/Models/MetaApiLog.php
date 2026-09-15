<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MetaApiLog extends Model
{
    protected $table = 'meta_api_logs';
    protected $guarded = ['id'];

    protected $casts = [
        'ok'            => 'boolean',
        'http_status'   => 'integer',
        'error_code'    => 'integer',
        'error_subcode' => 'integer',
        'usage_pct'     => 'integer',
    ];
}
