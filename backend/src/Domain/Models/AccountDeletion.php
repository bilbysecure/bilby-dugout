<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class AccountDeletion extends Model
{
    protected $table = 'account_deletions';
    protected $guarded = ['id'];
    protected $casts = ['grace_days' => 'integer'];
}
