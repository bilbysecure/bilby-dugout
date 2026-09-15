<?php

declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** A Custom/Lookalike Audience a phase retargets. Recorded, not created via API. */
class PhaseAudience extends Model
{
    protected $table = 'phase_audiences';
    protected $guarded = ['id'];
}
