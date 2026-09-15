<?php

declare(strict_types=1);

namespace Tests;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\Request;
use App\Domain\Models\RequestAssignee;
use App\Policies\RequestPolicy;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Pure-logic guard for tenant isolation (architecture §5.2 / §11.1).
 * No DB required — relations are set in-memory.
 */
final class RequestPolicyTest extends TestCase
{
    private RequestPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new RequestPolicy();
    }

    private function request(string $clientEmail, array $assigneeEmails = []): Request
    {
        $r = new Request(['client_email' => $clientEmail]);
        $r->setRelation('assignees', new Collection(array_map(
            fn (string $e) => new RequestAssignee(['team_member_email' => $e]),
            $assigneeEmails
        )));
        return $r;
    }

    private function principal(Role $role, ?string $email = null, ?string $clientEmail = null, ?string $designation = null): Principal
    {
        return new Principal(
            userId: 1,
            email: $email ?? 'user@example.com',
            name: 'Test',
            role: $role,
            designation: $designation,
            clientEmail: $clientEmail,
        );
    }

    public function test_client_cannot_view_another_tenants_request(): void
    {
        $p = $this->principal(Role::ClientOwner, 'owner@acme.com', 'owner@acme.com');
        self::assertFalse($this->policy->view($p, $this->request('other@beta.com')));
    }

    public function test_client_can_view_own_tenant_request(): void
    {
        $p = $this->principal(Role::ClientOwner, 'owner@acme.com', 'owner@acme.com');
        self::assertTrue($this->policy->view($p, $this->request('owner@acme.com')));
    }

    public function test_manager_sees_everything(): void
    {
        $p = $this->principal(Role::GlobalAdmin, 'admin@bilby.com');
        self::assertTrue($this->policy->view($p, $this->request('anyone@x.com')));
    }

    public function test_staff_only_sees_assigned(): void
    {
        $p = $this->principal(Role::AgencyStaff, 'designer@bilby.com', null, 'graphic_designer');
        self::assertFalse($this->policy->view($p, $this->request('c@acme.com', [])));
        self::assertTrue($this->policy->view($p, $this->request('c@acme.com', ['designer@bilby.com'])));
    }
}
