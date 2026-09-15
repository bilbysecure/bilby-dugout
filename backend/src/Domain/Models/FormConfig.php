<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class FormConfig extends Model
{
    protected $table = 'form_configs';
    protected $guarded = ['id'];
    protected $casts = ['items' => 'array'];
}
