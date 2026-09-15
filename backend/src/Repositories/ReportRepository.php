<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\Principal;
use App\Domain\Models\Report;
use Illuminate\Database\Eloquent\Builder;

/** Tenant-scoped access to reports. */
final class ReportRepository
{
    public function visibleTo(Principal $p): Builder
    {
        $q = Report::query();

        if ($p->isAgency()) {
            if ($p->isManager() && $p->impersonatedClientEmail) {
                $q->where('client_email', $p->impersonatedClientEmail);
            }
            return $q;
        }

        return $q->where('client_email', $p->clientEmail);
    }

    public function find(Principal $p, int $id): ?Report
    {
        return $this->visibleTo($p)->whereKey($id)->first();
    }
}
