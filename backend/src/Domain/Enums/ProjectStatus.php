<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/** One-off project lifecycle (Master Spec §10). */
enum ProjectStatus: string
{
    case Draft     = 'draft';     // created, no quote yet
    case Quoted    = 'quoted';    // a quote has been sent
    case Active    = 'active';    // deposit paid — work may proceed
    case Completed = 'completed'; // delivered; balance invoiced
    case Cancelled = 'cancelled';
}
