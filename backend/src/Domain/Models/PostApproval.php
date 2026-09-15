<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class PostApproval extends Model
{
    protected $table = 'post_approvals';
    protected $guarded = ['id'];
}
