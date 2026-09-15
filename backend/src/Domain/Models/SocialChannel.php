<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SocialChannel extends Model
{
    protected $table = 'social_channels';
    protected $guarded = ['id'];
}
