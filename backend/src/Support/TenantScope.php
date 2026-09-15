<?php

declare(strict_types=1);

namespace App\Support;

use App\Auth\Principal;
use Illuminate\Database\Eloquent\Builder;

/** Applies the standard tenant scope to a query (agency=all/impersonation, client=own). */
final class TenantScope
{
    public static function apply(Builder $q, Principal $p, string $column = 'client_email'): Builder
    {
        if ($p->isAgency()) {
            if ($p->isManager() && $p->impersonatedClientEmail) {
                $q->where($column, $p->impersonatedClientEmail);
            }
            return $q;
        }
        return $q->where($column, $p->clientEmail);
    }

    /** The client_email a create/write should be stamped with for this principal. */
    public static function tenantFor(Principal $p, ?string $requested = null): string
    {
        if ($p->isAgency()) {
            return $requested ?? $p->impersonatedClientEmail ?? '';
        }
        return (string) $p->clientEmail;
    }
}
