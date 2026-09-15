<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\Principal;
use App\Domain\Models\Request;
use Illuminate\Database\Eloquent\Builder;

/**
 * The PRIMARY tenant-isolation defense (architecture §5.2, layer a).
 * Every read path goes through visibleTo(); there is no unscoped list query.
 * Replaces the Base44 RLS that was missing/relied-on-client-side.
 */
final class RequestRepository
{
    /** Base query already scoped to what the principal may see. */
    public function visibleTo(Principal $p): Builder
    {
        $q = Request::query()->with('assignees');

        if ($p->isAgency()) {
            // Agency staff see the whole workspace (all clients' requests).
            // Manager "view as client" narrows to the impersonated client.
            if ($p->isManager() && $p->impersonatedClientEmail) {
                $q->where('client_email', $p->impersonatedClientEmail);
            }
            return $q;
        }

        // client_owner / client_member: only their company's rows.
        return $q->where('client_email', $p->clientEmail);
    }

    /** @return \Illuminate\Support\Collection<int,Request> */
    public function list(Principal $p, int $limit = 200)
    {
        return $this->visibleTo($p)->orderByDesc('created_at')->limit($limit)->get();
    }

    /** Returns the request only if the principal's scope includes it, else null. */
    public function find(Principal $p, int $id): ?Request
    {
        return $this->visibleTo($p)->whereKey($id)->first();
    }
}
