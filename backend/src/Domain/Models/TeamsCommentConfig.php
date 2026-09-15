<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class TeamsCommentConfig extends Model
{
    protected $table = 'teams_comment_config';
    protected $guarded = ['id'];
    protected $casts = ['enabled' => 'boolean'];
}
