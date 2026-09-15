<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Principal;
use App\Domain\Models\Subscription;
use App\Policies\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Read + edit client accounts (subscriptions). Agency reads; managers edit.
 * stripe_customer_id is hidden at the model level, so never serialized here.
 */
final class SubscriptionService
{
    private const FIELDS = [
        'client_name', 'company_name', 'avatar_url', 'plan_id', 'plan_name', 'services',
        'sla_tier', 'status', 'start_date', 'renewal_date', 'monthly_request_limit',
        'account_manager_email', 'account_manager_name', 'account_managers', 'notes', 'require_owner_approval',
    ];

    public function list(Principal $p, int $limit = 500): array
    {
        $q = Subscription::query()->orderByDesc('created_at');

        if ($p->isAgency()) {
            if ($p->isManager() && $p->impersonatedClientEmail) {
                $q->where('client_email', $p->impersonatedClientEmail);
            }
        } else {
            $q->where('client_email', $p->clientEmail);
        }

        return $q->limit($limit)->get()->toArray();
    }

    public function update(Principal $p, int $id, array $data): Subscription
    {
        if (!$p->isManager()) {
            throw new AuthorizationException('Manager access required');
        }
        $sub = Subscription::find($id);
        if (!$sub) {
            throw new ModelNotFoundException('Client not found');
        }
        $payload = array_intersect_key($data, array_flip(self::FIELDS));
        foreach (['account_managers', 'services'] as $arr) {
            if (array_key_exists($arr, $payload)) {
                $payload[$arr] = array_values((array) $payload[$arr]);
            }
        }
        $sub->fill($payload)->save();
        return $sub;
    }
}
