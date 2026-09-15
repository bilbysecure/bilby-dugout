<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class DataExport extends Model
{
    protected $table = 'data_exports';
    protected $guarded = ['id'];
}
