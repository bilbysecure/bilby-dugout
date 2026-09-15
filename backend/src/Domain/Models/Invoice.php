<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $table = 'invoices';
    protected $guarded = ['id'];
    protected $casts = [
        'line_items'  => 'array',
        'request_ids' => 'array',
        'amount'      => 'decimal:2',
        'due_date'    => 'date',
        'paid_date'   => 'date',
    ];

    protected $hidden = ['stripe_customer_id'];
}
