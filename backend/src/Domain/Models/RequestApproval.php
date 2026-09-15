<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RequestApproval extends Model
{
    protected $table = 'request_approvals';
    protected $guarded = ['id'];
    protected $casts = ['deliverable_count' => 'integer'];
}
