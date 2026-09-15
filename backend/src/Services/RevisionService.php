<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Principal;
use App\Domain\Models\RevisionRequest;
use App\Policies\AuthorizationException;
use App\Support\RequestGuard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/** Formal revision requests against a specific deliverable. */
final class RevisionService
{
    public function __construct(
        private readonly RequestGuard $guard,
        private readonly NotificationService $notifications,
        private readonly ActivityLogger $activity,
    ) {
    }

    public function list(Principal $p, int $requestId): array
    {
        $request = $this->guard->visibleOrFail($p, $requestId);
        return RevisionRequest::where('request_id', $request->id)->orderByDesc('created_at')->get()->toArray();
    }

    public function create(Principal $p, int $requestId, array $data): RevisionRequest
    {
        $request = $this->guard->visibleOrFail($p, $requestId);
        $this->guard->assertParticipant($p, $request, 'You cannot request a revision here');

        $deliverableUrl = trim((string) ($data['deliverable_url'] ?? ''));
        $summary = trim((string) ($data['summary'] ?? ''));
        if ($deliverableUrl === '' || $summary === '') {
            throw new InvalidArgumentException('deliverable_url and summary are required');
        }

        $assignedTeam = is_array($data['assigned_team_emails'] ?? null)
            ? $data['assigned_team_emails']
            : $request->assigned_to;

        $revision = RevisionRequest::create([
            'request_id'           => $request->id,
            'deliverable_url'      => $deliverableUrl,
            'deliverable_name'     => $data['deliverable_name'] ?? null,
            'requested_by_email'   => $p->email,
            'requested_by_name'    => $p->name ?: $p->email,
            'assigned_team_emails' => $assignedTeam,
            'summary'              => $summary,
            'status'               => 'requested',
        ]);

        $this->notifications->notifyMany(
            $assignedTeam,
            'status_change',
            'Revision requested: ' . ($request->title ?? ''),
            mb_substr($summary, 0, 140),
            $request->id,
        );
        $this->activity->log($p, 'revision_requested', $request);

        return $revision;
    }

    /** Assigned team / managers progress the revision. */
    public function updateStatus(Principal $p, int $revisionId, array $data): RevisionRequest
    {
        $revision = RevisionRequest::find($revisionId);
        if (!$revision) {
            throw new ModelNotFoundException('Revision not found');
        }
        $request = $this->guard->visibleOrFail($p, (int) $revision->request_id);
        $this->guard->assertAgencyWorkerOn($p, $request, 'Only assigned staff can update a revision');

        $status = (string) ($data['status'] ?? '');
        if (!in_array($status, ['requested', 'in_progress', 'implemented', 'approved'], true)) {
            throw new InvalidArgumentException('invalid status');
        }
        $revision->status = $status;
        if (isset($data['response_note'])) {
            $revision->response_note = $data['response_note'];
        }
        $revision->save();

        $this->activity->log($p, 'revision_' . $status, $request);
        return $revision;
    }
}
