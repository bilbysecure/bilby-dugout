<?php

declare(strict_types=1);

namespace App\Policies;

use App\Auth\Principal;
use App\Domain\Models\Request;

/**
 * Per-action authorization for requests (architecture §5.2, layer b).
 * Pairs with RequestRepository::visibleTo() (layer a — query scoping).
 */
final class RequestPolicy
{
    public function view(Principal $p, Request $r): bool
    {
        if ($p->isAgency()) {
            return $p->isManager() || in_array($p->email, $r->assigned_to, true);
        }
        // client_owner / client_member: same company tenant
        return $r->client_email === $p->clientEmail;
    }

    public function create(Principal $p): bool
    {
        // Agency staff create on behalf of a client (via the client-picker);
        // clients create for their own company.
        return $p->isAgency() || $p->isClient();
    }

    public function update(Principal $p, Request $r): bool
    {
        if ($p->isAgency()) {
            return $p->isManager() || in_array($p->email, $r->assigned_to, true);
        }
        return $r->client_email === $p->clientEmail;
    }

    /** Throwing helpers for controllers. */
    public function authorizeView(Principal $p, Request $r): void
    {
        if (!$this->view($p, $r)) {
            throw new AuthorizationException('You cannot view this request');
        }
    }

    public function authorizeCreate(Principal $p): void
    {
        if (!$this->create($p)) {
            throw new AuthorizationException('You cannot create requests');
        }
    }
}
