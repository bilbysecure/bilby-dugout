<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\Principal;
use App\Domain\Models\BrandKit;
use Illuminate\Database\Eloquent\Builder;

/** Tenant-scoped access to brand kits (same pattern as RequestRepository). */
final class BrandKitRepository
{
    public function visibleTo(Principal $p): Builder
    {
        $q = BrandKit::query();

        if ($p->isAgency()) {
            if ($p->isManager() && $p->impersonatedClientEmail) {
                $q->where('client_email', $p->impersonatedClientEmail);
            }
            return $q; // managers/staff may see brand kits across clients
        }

        return $q->where('client_email', $p->clientEmail);
    }

    public function find(Principal $p, int $id): ?BrandKit
    {
        return $this->visibleTo($p)->whereKey($id)->first();
    }
}
