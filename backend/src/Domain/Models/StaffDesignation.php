<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** A manageable BilbyPixel staff designation (table: designations). */
class StaffDesignation extends Model
{
    protected $table = 'designations';
    protected $guarded = ['id'];
}
