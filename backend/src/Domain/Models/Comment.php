<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $table = 'comments';
    protected $guarded = ['id'];
    protected $casts = [
        'attachments' => 'array',
        'is_internal' => 'boolean',
    ];
}
