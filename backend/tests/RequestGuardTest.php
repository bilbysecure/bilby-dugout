<?php

declare(strict_types=1);

namespace Tests;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\Request;
use App\Domain\Models\RequestAssignee;
use App\Repositories\RequestRepository;
use App\Support\RequestGuard;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Pure-logic checks for the Phase 2 sub-resource guards (comments/approvals/etc).
 * The boolean helpers don't touch the DB, so the repo dependency is inert here.
 */
final class RequestGuardTest extends TestCase
{
    private RequestGuard $guard;

    protected function setUp(): void
    {
        $this->guard = new RequestGuard(new RequestRepository());
    }

    private function request(string $clientEmail, array $assignees = []): Request
    {
        $r = new Request(['client_email' => $clientEmail]);
        $r->setRelation('assignees', new Collection(array_map(
            fn (string $e) => new RequestAssignee(['team_member_email' => $e]),
            $assignees
        )));
        return $r;
    }

    private function p(Role $role, ?string $email = null, ?string $clientEmail = null, ?string $designation = null): Principal
    {
        return new Principal(1, $email ?? 'u@x.com', 'U', $role, $designation, $clientEmail);
    }

    public function test_assigned_staff_is_worker(): void
    {
        $r = $this->request('c@acme.com', ['designer@bilby.com']);
        self::assertTrue($this->guard->isAgencyWorkerOn($this->p(Role::AgencyStaff, 'designer@bilby.com', null, 'graphic_designer'), $r));
    }

    public function test_unassigned_staff_is_not_worker(): void
    {
        $r = $this->request('c@acme.com', []);
        self::assertFalse($this->guard->isAgencyWorkerOn($this->p(Role::AgencyStaff, 'other@bilby.com', null, 'graphic_designer'), $r));
    }

    public function test_manager_is_always_worker(): void
    {
        $r = $this->request('c@acme.com', []);
        self::assertTrue($this->guard->isAgencyWorkerOn($this->p(Role::GlobalAdmin, 'admin@bilby.com'), $r));
    }

    public function test_tenant_client_match(): void
    {
        $r = $this->request('owner@acme.com');
        self::assertTrue($this->guard->isTenantClientOf($this->p(Role::ClientMember, 'm@acme.com', 'owner@acme.com'), $r));
        self::assertFalse($this->guard->isTenantClientOf($this->p(Role::ClientMember, 'm@beta.com', 'owner@beta.com'), $r));
    }
}
