<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $id
 * @property string $email
 * @property string $full_name
 * @property string $azure_object_id
 * @property string $role
 * @property bool   $onboarding_completed
 * @property string $status
 */
class User extends Model
{
    protected $table = 'users';
    protected $guarded = ['id'];
    protected $casts = [
        'onboarding_completed' => 'boolean',
    ];
}
