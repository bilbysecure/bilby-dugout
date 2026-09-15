<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionRequest extends Model
{
    protected $table = 'revision_requests';
    protected $guarded = ['id'];
    protected $casts = ['assigned_team_emails' => 'array'];
}
