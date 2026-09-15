<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TaskApprover extends Model
{
    protected $table = 'task_approvers';
    protected $guarded = ['id'];

    protected $casts = ['is_required' => 'boolean'];
}
