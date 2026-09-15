<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\SubscriptionPlan;
use App\Policies\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/** Service plans (bundles/packages) — global-admin writes, agency read. */
final class PlanService
{
    private const FIELDS = [
        'name', 'description', 'sla_tier', 'monthly_request_limit', 'concurrent_request_limit',
        'brand_kit_allowance', 'badge_color', 'is_default', 'pricing_type', 'billing_period',
        'price', 'hours', 'credits', 'services', 'status', 'image_url',
        'trial_enabled', 'trial_amount', 'trial_period_count', 'trial_period_unit',
        'setup_fee_enabled', 'setup_fee_amount',
    ];

    public function list(Principal $p): array
    {
        if (!$p->isAgency()) {
            throw new AuthorizationException('Agency access required');
        }
        return SubscriptionPlan::orderByDesc('is_default')->orderBy('name')->get()->toArray();
    }

    public function create(Principal $p, array $data): SubscriptionPlan
    {
        $this->assertAdmin($p);
        $payload = $this->clean($data);
        if (empty($payload['name'])) {
            throw new InvalidArgumentException('name is required');
        }
        $plan = SubscriptionPlan::create($payload);
        if (!empty($payload['is_default'])) {
            SubscriptionPlan::where('id', '!=', $plan->id)->update(['is_default' => false]);
        }
        return $plan;
    }

    public function update(Principal $p, int $id, array $data): SubscriptionPlan
    {
        $this->assertAdmin($p);
        $plan = SubscriptionPlan::find($id);
        if (!$plan) {
            throw new ModelNotFoundException('Plan not found');
        }
        $plan->fill($this->clean($data))->save();
        if ($plan->is_default) {
            SubscriptionPlan::where('id', '!=', $plan->id)->update(['is_default' => false]);
        }
        return $plan;
    }

    public function delete(Principal $p, int $id): void
    {
        $this->assertAdmin($p);
        $plan = SubscriptionPlan::find($id);
        if (!$plan) {
            throw new ModelNotFoundException('Plan not found');
        }
        $plan->delete();
    }

    private function clean(array $data): array
    {
        $payload = array_intersect_key($data, array_flip(self::FIELDS));
        if (array_key_exists('services', $payload)) {
            $payload['services'] = array_values((array) $payload['services']);
        }
        foreach (['monthly_request_limit', 'concurrent_request_limit', 'brand_kit_allowance', 'hours', 'credits', 'trial_period_count'] as $intField) {
            if (array_key_exists($intField, $payload) && $payload[$intField] !== null && $payload[$intField] !== '') {
                $payload[$intField] = (int) $payload[$intField];
            }
        }
        return $payload;
    }

    private function assertAdmin(Principal $p): void
    {
        if ($p->role !== Role::GlobalAdmin) {
            throw new AuthorizationException('Global admin access required');
        }
    }
}
