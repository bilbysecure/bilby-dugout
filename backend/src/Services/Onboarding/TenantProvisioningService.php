<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Domain\Enums\Role;
use App\Domain\Models\ClientMember;
use App\Domain\Models\ClientOnboardingPreference;
use App\Domain\Models\Subscription;
use App\Domain\Models\User;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;

/**
 * Reusable tenant creation (Master Spec §17.3). Creates the client account
 * (subscription), the owner user, the owner client-member, and an onboarding
 * preferences stub — everything a new tenant needs.
 *
 * ⚠️ EXTENSION POINT: a future PUBLIC self-signup path MUST call this same
 * service (do NOT re-implement provisioning in a controller). The invite flow
 * and self-signup differ only in *who* triggers it and how identity is proven;
 * the provisioning itself is identical and lives here.
 */
final class TenantProvisioningService
{
    /**
     * @param array{owner_email:string, company_name?:string, owner_name?:string, plan_id?:int, plan_name?:string} $data
     * @return array{subscription:Subscription, owner:User, member:ClientMember}
     */
    public function provision(array $data): array
    {
        $ownerEmail = strtolower(trim((string) ($data['owner_email'] ?? '')));
        if ($ownerEmail === '') {
            throw new InvalidArgumentException('owner_email is required');
        }
        $company = (string) ($data['company_name'] ?? '');
        $ownerName = (string) ($data['owner_name'] ?? $company ?: $ownerEmail);

        return DB::connection()->transaction(function () use ($ownerEmail, $company, $ownerName, $data) {
            // The tenant key is the owner's email.
            $subscription = Subscription::firstOrNew(['client_email' => $ownerEmail]);
            $subscription->client_name ??= $ownerName;
            $subscription->company_name = $company ?: $subscription->company_name;
            $subscription->status ??= 'active';
            if (!empty($data['plan_id'])) {
                $subscription->plan_id = (int) $data['plan_id'];
            }
            if (!empty($data['plan_name'])) {
                $subscription->plan_name = (string) $data['plan_name'];
            }
            $subscription->save();

            // Owner user (local auth; password set later via the invite link).
            $owner = User::firstOrNew(['email' => $ownerEmail]);
            $owner->full_name = $owner->full_name ?: $ownerName;
            $owner->role = Role::ClientOwner->value;
            $owner->status ??= 'active';
            $owner->save();

            // Owner as a client member of their own company.
            $member = ClientMember::firstOrNew(['email' => $ownerEmail]);
            $member->client_email = $ownerEmail;
            $member->company_name = $company ?: $member->company_name;
            $member->full_name = $member->full_name ?: $ownerName;
            $member->role = 'Owner';
            $member->status ??= 'active';
            $member->save();

            // Onboarding preferences stub.
            $prefs = ClientOnboardingPreference::firstOrNew(['client_email' => $ownerEmail]);
            $prefs->save();

            return ['subscription' => $subscription, 'owner' => $owner, 'member' => $member];
        });
    }
}
