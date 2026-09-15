<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/** The four unified access roles (see architecture §1). */
enum Role: string
{
    case GlobalAdmin  = 'global_admin';
    case AgencyStaff  = 'agency_staff';
    case ClientOwner  = 'client_owner';
    case ClientMember = 'client_member';

    public function isAgency(): bool
    {
        return $this === self::GlobalAdmin || $this === self::AgencyStaff;
    }

    public function isClient(): bool
    {
        return $this === self::ClientOwner || $this === self::ClientMember;
    }
}
