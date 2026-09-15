<?php

declare(strict_types=1);

namespace Tests\Projects;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\Project;
use App\Domain\Models\Request;
use App\Domain\Models\Subscription;
use App\Policies\RequestPolicy;
use App\Repositories\ProjectRepository;
use App\Repositories\RequestRepository;
use App\Services\RequestService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Every request belongs to exactly one of {subscription, project}. */
final class RequestOwnershipTest extends TestCase
{
    private RequestService $svc;
    private Subscription $sub;
    private Project $project;

    protected function setUp(): void
    {
        Request::query()->delete();
        Subscription::query()->delete();
        Project::query()->delete();

        $this->svc = new RequestService(new RequestRepository(), new RequestPolicy(), new ProjectRepository());
        $this->sub = Subscription::create(['client_email' => 'owner@acme.com', 'status' => 'active', 'plan_name' => 'Burrow']);
        $this->project = Project::create(['client_email' => 'owner@acme.com', 'title' => 'Campaign', 'type' => 'ad_campaign', 'status' => 'active']);
    }

    private function owner(string $email = 'owner@acme.com'): Principal
    {
        return new Principal(userId: 1, email: $email, name: 'Olivia', role: Role::ClientOwner, clientEmail: $email);
    }

    public function test_subscription_request_gets_subscription_id_only(): void
    {
        $req = $this->svc->create($this->owner(), ['title' => 'Logo', 'type' => 'graphic_design']);

        self::assertSame($this->sub->id, $req->subscription_id);
        self::assertNull($req->project_id);
    }

    public function test_project_request_gets_project_id_only(): void
    {
        $req = $this->svc->create($this->owner(), ['title' => 'Ad', 'type' => 'ads_campaign', 'project_id' => $this->project->id]);

        self::assertSame($this->project->id, $req->project_id);
        self::assertNull($req->subscription_id);
    }

    public function test_never_both_owners(): void
    {
        $req = $this->svc->create($this->owner(), ['title' => 'Ad', 'type' => 'ads_campaign', 'project_id' => $this->project->id]);
        self::assertTrue(($req->subscription_id === null) xor ($req->project_id === null), 'exactly one owner set');
    }

    public function test_project_from_another_tenant_is_rejected(): void
    {
        $other = Project::create(['client_email' => 'other@x.com', 'title' => 'X', 'type' => 'ad_campaign', 'status' => 'active']);

        $this->expectExceptionMessage('Invalid project for this client');
        $this->svc->create($this->owner(), ['title' => 'Ad', 'type' => 'ads_campaign', 'project_id' => $other->id]);
    }

    public function test_no_subscription_and_no_project_is_rejected(): void
    {
        $this->sub->delete();

        $this->expectException(InvalidArgumentException::class);
        $this->svc->create($this->owner(), ['title' => 'Orphan', 'type' => 'graphic_design']);
    }
}
