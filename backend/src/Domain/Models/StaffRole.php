<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class StaffRole extends Model
{
    protected $table = 'staff_roles';
    protected $guarded = ['id'];
    protected $casts = ['permissions' => 'array'];
}
