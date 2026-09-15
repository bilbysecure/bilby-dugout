<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Normalized request ↔ team-member assignment (decision #4). */
class RequestAssignee extends Model
{
    protected $table = 'request_assignees';
    protected $guarded = ['id'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class, 'request_id');
    }
}
