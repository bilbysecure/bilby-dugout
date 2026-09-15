<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/** Quote lifecycle (Master Spec §10). */
enum QuoteStatus: string
{
    case Draft    = 'draft';
    case Sent     = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired  = 'expired';

    /** Allowed transitions in the quote state machine. */
    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Draft => in_array($to, [self::Sent, self::Declined], true),
            self::Sent  => in_array($to, [self::Accepted, self::Declined, self::Expired], true),
            default     => false, // accepted/declined/expired are terminal
        };
    }
}
