<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\Principal;
use App\Domain\Models\Task;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tenant-scoped access to tasks. Agency sees all (manager impersonation narrows).
 * Clients see only their own tenant's tasks that actually involve them —
 * internal-only tasks (requires_client_approval = false) never surface to clients.
 */
final class TaskRepository
{
    public function visibleTo(Principal $p): Builder
    {
        $q = Task::query();

        if ($p->isAgency()) {
            if ($p->isManager() && $p->impersonatedClientEmail) {
                $q->where('client_email', $p->impersonatedClientEmail);
            }
            return $q;
        }

        return $q->where('client_email', $p->clientEmail)
            ->where('requires_client_approval', true);
    }

    public function find(Principal $p, int $id): ?Task
    {
        return $this->visibleTo($p)->whereKey($id)->first();
    }
}
