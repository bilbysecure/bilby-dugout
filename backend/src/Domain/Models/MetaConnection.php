<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** A client's Meta integration. The encrypted token is never serialized. */
class MetaConnection extends Model
{
    protected $table = 'meta_connections';
    protected $guarded = ['id'];

    /** The token ciphertext must never appear in any API response. */
    protected $hidden = ['token_encrypted'];

    protected $casts = [
        'ad_account_ids'   => 'array',
        'page_ids'         => 'array',
        'ig_account_ids'   => 'array',
        'scopes_granted'   => 'array',
        'token_expires_at' => 'datetime',
        'last_success_at'  => 'datetime',
        'last_error_at'    => 'datetime',
        'rate_limit_pct'   => 'integer',
    ];

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }
}
