<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EmailOutbox extends Model
{
    protected $table = 'email_outbox';
    protected $guarded = ['id'];
    public $timestamps = false; // only created_at, set by DB default / explicitly
}
