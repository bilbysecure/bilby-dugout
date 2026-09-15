<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Core unit of work. Tenant key is `client_email`.
 * Assignees are stored in the normalized `request_assignees` table (decision #4);
 * `assigned_to` is exposed as a read-only convenience accessor for the SPA.
 */
class Request extends Model
{
    protected $table = 'requests';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_type'     => 'array',
        'platform'     => 'array',
        'attachments'  => 'array',
        'deliverables' => 'array',
        'due_date'     => 'date',
        'publish_date' => 'datetime',
    ];

    protected $appends = ['assigned_to'];

    public function assignees(): HasMany
    {
        return $this->hasMany(RequestAssignee::class, 'request_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RequestApproval::class, 'request_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'request_id');
    }

    /** @return string[] assignee emails */
    public function getAssignedToAttribute(): array
    {
        return $this->assignees->pluck('team_member_email')->all();
    }
}
