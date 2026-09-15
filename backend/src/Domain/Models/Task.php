<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A task under the dual-approval flow. Tenant key is `client_email`. */
class Task extends Model
{
    protected $table = 'tasks';
    protected $guarded = ['id'];

    protected $casts = [
        'requires_client_approval' => 'boolean',
        'current_round'            => 'integer',
        'due_date'                 => 'date',
    ];

    public function approvers(): HasMany
    {
        return $this->hasMany(TaskApprover::class, 'task_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(TaskApproval::class, 'task_id');
    }
}
