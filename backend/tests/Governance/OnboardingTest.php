<?php

declare(strict_types=1);

namespace Tests\Governance;

use App\Domain\Models\ClientMember;
use App\Domain\Models\ConsentRecord;
use App\Domain\Models\Subscription;
use App\Domain\Models\User;
use App\Services\Onboarding\TenantProvisioningService;
use PHPUnit\Framework\TestCase;

/** Reusable tenant provisioning (Master Spec §17.3). */
final class OnboardingTest extends TestCase
{
    protected function setUp(): void
    {
        User::query()->where('email', 'like', '%@newco.com')->delete();
        Subscription::query()->where('client_email', 'like', '%@newco.com')->delete();
        ClientMember::query()->where('email', 'like', '%@newco.com')->delete();
    }

    public function test_provision_creates_account_owner_and_member(): void
    {
        $svc = new TenantProvisioningService();
        $result = $svc->provision(['owner_email' => 'Owner@NewCo.com', 'company_name' => 'NewCo', 'owner_name' => 'Nadia']);

        // Email normalized to lowercase; tenant key is the owner email.
        self::assertSame('owner@newco.com', $result['owner']->email);
        self::assertSame('client_owner', $result['owner']->role);
        self::assertSame('owner@newco.com', $result['subscription']->client_email);
        self::assertSame('NewCo', $result['subscription']->company_name);

        $member = ClientMember::where('email', 'owner@newco.com')->first();
        self::assertNotNull($member);
        self::assertSame('Owner', $member->role);
    }

    public function test_provision_is_idempotent(): void
    {
        $svc = new TenantProvisioningService();
        $svc->provision(['owner_email' => 'dup@newco.com', 'company_name' => 'Dup']);
        $svc->provision(['owner_email' => 'dup@newco.com', 'company_name' => 'Dup']);

        self::assertSame(1, User::where('email', 'dup@newco.com')->count());
        self::assertSame(1, Subscription::where('client_email', 'dup@newco.com')->count());
    }

    public function test_owner_has_no_password_until_invite_accepted(): void
    {
        (new TenantProvisioningService())->provision(['owner_email' => 'nopass@newco.com']);
        self::assertNull(User::where('email', 'nopass@newco.com')->first()->password_hash);
    }
}
