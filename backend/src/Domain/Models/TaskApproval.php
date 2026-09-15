<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TaskApproval extends Model
{
    protected $table = 'task_approvals';
    protected $guarded = ['id'];

    protected $casts = ['round' => 'integer'];
}
