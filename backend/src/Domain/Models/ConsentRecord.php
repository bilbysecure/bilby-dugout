<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ConsentRecord extends Model
{
    protected $table = 'consent_records';
    protected $guarded = ['id'];
}
