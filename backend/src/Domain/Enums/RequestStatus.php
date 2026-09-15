<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/** Request lifecycle states (see architecture §5.2 of the original docs). */
enum RequestStatus: string
{
    case PendingOwnerApproval = 'pending_owner_approval';
    case Submitted            = 'submitted';
    case InProgress           = 'in_progress';
    case Review               = 'review';
    case Revision             = 'revision';
    case Approved             = 'approved';
    case Scheduled            = 'scheduled';
    case Published            = 'published';
    case Completed            = 'completed';
    case Cancelled            = 'cancelled';

    /** Statuses that count toward a client's monthly quota. */
    public static function activeForQuota(): array
    {
        return [
            self::Submitted, self::InProgress, self::Review, self::Revision,
            self::Approved, self::Scheduled, self::Published, self::Completed,
        ];
    }
}
