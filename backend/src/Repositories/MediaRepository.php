<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\Principal;
use App\Domain\Models\Media;
use Illuminate\Database\Eloquent\Builder;

/** Tenant-scoped access to media (same pattern as BrandKitRepository). */
final class MediaRepository
{
    public function visibleTo(Principal $p): Builder
    {
        $q = Media::query();

        if ($p->isAgency()) {
            if ($p->isManager() && $p->impersonatedClientEmail) {
                $q->where('client_email', $p->impersonatedClientEmail);
            }
            return $q; // agency sees all media (incl. agency-owned, client_email null)
        }

        return $q->where('client_email', $p->clientEmail);
    }

    public function find(Principal $p, int $id): ?Media
    {
        return $this->visibleTo($p)->whereKey($id)->first();
    }
}
