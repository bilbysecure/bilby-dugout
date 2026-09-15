<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Principal;
use App\Domain\Models\ClientMember;
use App\Domain\Models\Request;
use App\Domain\Models\RequestAssignee;
use App\Domain\Models\Subscription;
use App\Policies\AuthorizationException;
use App\Policies\RequestPolicy;
use App\Repositories\ProjectRepository;
use App\Repositories\RequestRepository;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;

/**
 * Business logic for requests. Ports the Base44 `createRequest`/`listVisibleRequests`/
 * `getVisibleRequest` functions, with authorization enforced server-side.
 */
final class RequestService
{
    /** Client-settable fields only (server stamps the rest). Mirrors Base44 REQUEST_FIELDS. */
    private const FILLABLE = [
        'title', 'description', 'type', 'sub_type', 'platform', 'objective',
        'target_audience', 'key_messaging', 'tone', 'priority', 'due_date',
        'publish_date', 'attachments', 'deliverables', 'notes',
        'brand_kit_id', 'brand_kit_name',
    ];

    public function __construct(
        private readonly RequestRepository $repo,
        private readonly RequestPolicy $policy,
        private readonly ProjectRepository $projects,
    ) {
    }

    public function list(Principal $p, int $limit = 200): array
    {
        return $this->repo->list($p, $limit)->toArray();
    }

    public function get(Principal $p, int $id): Request
    {
        $request = $this->repo->find($p, $id);
        if (!$request) {
            // Indistinguishable from "not found" to avoid leaking existence across tenants.
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Request not found');
        }
        return $request;
    }

    public function create(Principal $p, array $payload): Request
    {
        $this->policy->authorizeCreate($p);

        $data = [];
        foreach (self::FILLABLE as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field];
            }
        }

        // Normalize array fields
        foreach (['attachments', 'deliverables', 'sub_type', 'platform'] as $arr) {
            $data[$arr] = $this->toArray($data[$arr] ?? []);
        }

        if (empty($data['title']) || empty($data['type'])) {
            throw new InvalidArgumentException('Title and type are required');
        }

        // Server-stamped ownership (never trusted from client)
        $data['submitted_by_email'] = $p->email;
        $data['submitted_by_name']  = $p->name ?: $p->email;

        $assignees = [];

        if ($p->isAgency()) {
            // Manager-tier create on behalf of a client
            $clientEmail = $payload['client_email'] ?? null;
            if (!$clientEmail) {
                throw new InvalidArgumentException('client_email is required');
            }
            $data['status'] = $payload['status'] ?? 'submitted';
            $assignees = $this->toArray($payload['assigned_to'] ?? []);
        } else {
            // Client / member create for their own company
            $member = ClientMember::where('email', $p->email)->first();
            $clientEmail = $member->client_email ?? $p->clientEmail ?? $p->email;

            $needsOwnerApproval = $member
                && strcasecmp((string) $member->role, 'Owner') !== 0
                && (bool) ($this->activeSubscription($clientEmail)?->require_owner_approval ?? false);

            $data['status'] = $needsOwnerApproval ? 'pending_owner_approval' : 'submitted';
        }

        $data['client_email'] = $clientEmail;

        // Every request belongs to EXACTLY ONE owner: a one-off project OR the
        // client's active subscription (Master Spec §11 Layer A).
        $this->resolveOwnership($p, $data, (string) $clientEmail, isset($payload['project_id']) ? (int) $payload['project_id'] : null);

        return DB::connection()->transaction(function () use ($data, $assignees) {
            /** @var Request $request */
            $request = Request::create($data);
            foreach (array_unique($assignees) as $email) {
                RequestAssignee::create(['request_id' => $request->id, 'team_member_email' => $email]);
            }
            return $request->load('assignees');
        });
    }

    /**
     * Resolve the request's single owner. A `project_id` (validated against the
     * tenant) wins; otherwise the client's active subscription is required.
     * Sets subscription_id XOR project_id — never both, never neither.
     */
    private function resolveOwnership(Principal $p, array &$data, string $clientEmail, ?int $projectId): void
    {
        if ($projectId !== null && $projectId > 0) {
            $project = $this->projects->find($p, $projectId);
            if (!$project || $project->client_email !== $clientEmail) {
                throw new InvalidArgumentException('Invalid project for this client');
            }
            $data['project_id'] = $project->id;
            $data['subscription_id'] = null;
            return;
        }

        $subscription = $this->activeSubscription($clientEmail);
        if (!$subscription) {
            throw new InvalidArgumentException('No active subscription for this client; attach the request to a project (project_id) instead');
        }
        $data['subscription_id'] = $subscription->id;
        $data['project_id'] = null;
    }

    private function activeSubscription(string $clientEmail): ?Subscription
    {
        return Subscription::where('client_email', $clientEmail)
            ->where('status', 'active')->orderByDesc('created_at')->first();
    }

    private function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        return ($value === null || $value === '') ? [] : [$value];
    }
}
