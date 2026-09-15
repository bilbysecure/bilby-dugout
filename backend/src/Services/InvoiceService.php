<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Principal;
use App\Domain\Models\Invoice;

/** Read access to invoices, scoped like subscriptions. */
final class InvoiceService
{
    public function list(Principal $p, int $limit = 500): array
    {
        $q = Invoice::query()->orderByDesc('created_at');

        if ($p->isAgency()) {
            if ($p->isManager() && $p->impersonatedClientEmail) {
                $q->where('client_email', $p->impersonatedClientEmail);
            }
        } else {
            $q->where('client_email', $p->clientEmail);
        }

        return $q->limit($limit)->get()->toArray();
    }
}
