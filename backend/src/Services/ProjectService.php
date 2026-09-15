<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Principal;
use App\Domain\Enums\ProjectStatus;
use App\Domain\Models\Invoice;
use App\Domain\Models\Project;
use App\Domain\Models\ProjectQuote;
use App\Policies\AuthorizationException;
use App\Repositories\ProjectRepository;
use App\Services\Payments\PaymentGateway;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/** One-off projects (Master Spec §10). Managers create/manage; clients view own. */
final class ProjectService
{
    private const FIELDS = ['title', 'type', 'scope', 'price', 'currency', 'client_email'];

    public function __construct(
        private readonly ProjectRepository $repo,
        private readonly PaymentGateway $payments,
        private readonly ActivityLogger $activity,
    ) {
    }

    public function list(Principal $p): array
    {
        return $this->repo->visibleTo($p)->orderByDesc('created_at')->get()->toArray();
    }

    public function get(Principal $p, int $id): Project
    {
        $project = $this->repo->find($p, $id);
        if (!$project) {
            throw new ModelNotFoundException('Project not found');
        }
        return $project;
    }

    public function create(Principal $p, array $data): Project
    {
        $this->assertManager($p);
        $payload = array_intersect_key($data, array_flip(self::FIELDS));
        if (empty($payload['title'])) {
            throw new InvalidArgumentException('title is required');
        }
        if (empty($payload['client_email'])) {
            throw new InvalidArgumentException('client_email is required');
        }
        $payload['scope'] = array_values((array) ($payload['scope'] ?? []));
        $payload['type'] = $payload['type'] ?? 'ad_campaign';
        $payload['currency'] = $payload['currency'] ?? 'AUD';
        $payload['status'] = ProjectStatus::Draft->value;
        $payload['created_by'] = $p->email;

        $project = Project::create($payload);
        $this->activity->log($p, 'project_created', null, null, $project->status, 'project', ['project_id' => $project->id]);
        return $project;
    }

    /** Manager marks a project complete and raises the balance invoice. */
    public function complete(Principal $p, int $id): Project
    {
        $this->assertManager($p);
        $project = $this->get($p, $id);
        if ($project->status !== ProjectStatus::Active->value) {
            throw new InvalidArgumentException('Only an active project can be completed');
        }

        $accepted = $this->acceptedQuote($project);
        $balance = $accepted
            ? max(0, (float) $accepted->subtotal - (float) $accepted->deposit_amount)
            : (float) ($project->price ?? 0);

        // Balance invoice via the existing billing table (draft; Stripe sync later).
        if ($accepted && $balance > 0) {
            $ref = $this->payments->createBalanceInvoice($accepted, $balance);
            Invoice::create([
                'client_email' => $project->client_email,
                'amount'       => $balance,
                'currency'     => $project->currency ?? 'AUD',
                'status'       => 'draft',
                'description'  => 'Balance for project: ' . $project->title,
                'notes'        => 'gateway_ref=' . $ref['id'],
            ]);
        }

        $from = $project->status;
        $project->status = ProjectStatus::Completed->value;
        $project->save();
        $this->activity->log($p, 'project_completed', null, $from, $project->status, 'project', ['project_id' => $project->id]);
        return $project;
    }

    /**
     * System-triggered activation from a paid deposit (Stripe webhook). No
     * Principal — the caller is the verified webhook. Idempotent.
     */
    public function activateFromDeposit(int $projectId): ?Project
    {
        $project = Project::find($projectId);
        if (!$project) {
            return null;
        }
        if ($project->status === ProjectStatus::Active->value || $project->status === ProjectStatus::Completed->value) {
            return $project; // already activated — idempotent
        }
        $project->status = ProjectStatus::Active->value;
        $project->save();
        return $project;
    }

    private function acceptedQuote(Project $project): ?ProjectQuote
    {
        return ProjectQuote::where('project_id', $project->id)
            ->where('status', 'accepted')->orderByDesc('accepted_at')->first();
    }

    private function assertManager(Principal $p): void
    {
        if (!$p->isManager()) {
            throw new AuthorizationException('Manager access required');
        }
    }
}
