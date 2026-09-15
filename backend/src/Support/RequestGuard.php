<?php

declare(strict_types=1);

namespace App\Support;

use App\Auth\Principal;
use App\Domain\Models\Request;
use App\Policies\AuthorizationException;
use App\Repositories\RequestRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Shared access checks for request sub-resources (comments, approvals, …).
 * `visibleOrFail()` reuses the tenant-scoped repository so a caller can never
 * touch a sub-resource of a request they cannot see.
 */
final class RequestGuard
{
    public function __construct(private readonly RequestRepository $repo)
    {
    }

    public function visibleOrFail(Principal $p, int $requestId): Request
    {
        $request = $this->repo->find($p, $requestId);
        if (!$request) {
            throw new ModelNotFoundException('Request not found');
        }
        return $request;
    }

    /** Agency manager, or an agency staff member assigned to this request. */
    public function isAgencyWorkerOn(Principal $p, Request $r): bool
    {
        return $p->isAgency() && ($p->isManager() || in_array($p->email, $r->assigned_to, true));
    }

    /** A client (owner/member) belonging to this request's tenant. */
    public function isTenantClientOf(Principal $p, Request $r): bool
    {
        return $p->isClient() && $r->client_email === $p->clientEmail;
    }

    public function assertAgencyWorkerOn(Principal $p, Request $r, string $msg = 'Forbidden'): void
    {
        if (!$this->isAgencyWorkerOn($p, $r)) {
            throw new AuthorizationException($msg);
        }
    }

    public function assertParticipant(Principal $p, Request $r, string $msg = 'Forbidden'): void
    {
        if (!$this->isAgencyWorkerOn($p, $r) && !$this->isTenantClientOf($p, $r)) {
            throw new AuthorizationException($msg);
        }
    }
}
