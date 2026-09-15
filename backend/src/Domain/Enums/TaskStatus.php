<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/** Task dual-approval lifecycle (Master Spec §11 Layer B). */
enum TaskStatus: string
{
    case Draft                  = 'draft';
    case PendingInternalApproval = 'pending_internal_approval';
    case ChangesRequested       = 'changes_requested';
    case PendingClientApproval  = 'pending_client_approval';
    case InternalApproved       = 'internal_approved'; // internal gate passed, no client approval required
    case ClientApproved         = 'client_approved';
    case InProgress             = 'in_progress';
    case Done                   = 'done';
    case Cancelled              = 'cancelled';

    public function isTerminal(): bool
    {
        return $this === self::Done || $this === self::Cancelled;
    }
}
