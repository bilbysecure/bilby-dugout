<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ClientRole extends Model
{
    protected $table = 'client_roles';
    protected $guarded = ['id'];
    protected $casts = ['permissions' => 'array'];
}
