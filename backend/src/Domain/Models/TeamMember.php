<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TeamMember extends Model
{
    protected $table = 'team_members';
    protected $guarded = ['id'];
    protected $casts = ['is_admin' => 'boolean'];
}
