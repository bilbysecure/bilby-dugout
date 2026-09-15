<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class RequestReview extends Model
{
    protected $table = 'request_reviews';
    protected $guarded = ['id'];
    protected $casts = ['rating' => 'integer'];
}
