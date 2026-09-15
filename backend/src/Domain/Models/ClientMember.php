<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ClientMember extends Model
{
    protected $table = 'client_members';
    protected $guarded = ['id'];
}
