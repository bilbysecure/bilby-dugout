<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ClientOnboardingPreference extends Model
{
    protected $table = 'client_onboarding_preferences';
    protected $guarded = ['id'];
}
