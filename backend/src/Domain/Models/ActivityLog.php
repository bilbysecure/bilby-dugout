<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';
    protected $guarded = ['id'];
    protected $casts = ['metadata' => 'array'];

    // Audit rows are immutable; only created_at is meaningful.
    public const UPDATED_AT = null;
}
