<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\Principal;
use App\Domain\Models\MetaConnection;
use App\Policies\AuthorizationException;

/** Tenant-scoped access to Meta connections (one per client tenant). */
final class MetaConnectionRepository
{
    /**
     * Resolve which tenant this principal is acting on:
     *  - client → always their own tenant (the request param is ignored)
     *  - agency → the requested client (or the impersonated one)
     */
    public function resolveClientEmail(Principal $p, ?string $requested): string
    {
        if ($p->isClient()) {
            return (string) $p->clientEmail;
        }
        $client = $requested ?: $p->impersonatedClientEmail;
        if (!$client) {
            throw new AuthorizationException('A client_email is required for agency access');
        }
        return (string) $client;
    }

    public function forClient(Principal $p, ?string $requested): ?MetaConnection
    {
        return MetaConnection::where('client_email', $this->resolveClientEmail($p, $requested))->first();
    }

    public function firstOrNewForClient(Principal $p, ?string $requested): MetaConnection
    {
        return MetaConnection::firstOrNew(['client_email' => $this->resolveClientEmail($p, $requested)]);
    }
}
