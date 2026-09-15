<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\Request;
use App\Domain\Models\RequestApproval;
use App\Policies\AuthorizationException;
use App\Services\Approvals\SignedDecision;
use App\Support\RequestGuard;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Client sign-off on deliverables. Creating an approval transitions the request
 * (approved → 'approved', revision_requested → 'revision') and notifies the team.
 */
final class ApprovalService
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
        return RequestApproval::where('request_id', $request->id)->orderByDesc('created_at')->get()->toArray();
    }

    public function create(Principal $p, int $requestId, array $data): RequestApproval
    {
        $request = $this->guard->visibleOrFail($p, $requestId);
        $this->assertCanApprove($p, $request);

        // Shared signed-decision capture (validates decision + required signature).
        $sd = SignedDecision::capture($p, $data, ['approved', 'revision_requested'], requireSignature: true);
        $decision = $sd->decision;

        return DB::connection()->transaction(function () use ($p, $request, $sd, $decision) {
            $approval = RequestApproval::create([
                'request_id'        => $request->id,
                'request_title'     => $request->title,
                'client_email'      => $request->client_email,
                'decision'          => $decision,
                'signed_by_name'    => $sd->actorName,
                'signed_by_email'   => $sd->actorEmail,
                'approval_note'     => $sd->comment,
                'digital_signature' => $sd->signature,
                'deliverable_count' => is_array($request->deliverables) ? count($request->deliverables) : null,
            ]);

            $from = $request->status;
            $request->status = $decision === 'approved' ? 'approved' : 'revision';
            $request->save();

            $this->notifications->notifyMany(
                $request->assigned_to,
                'status_change',
                ($decision === 'approved' ? 'Approved: ' : 'Revision requested: ') . ($request->title ?? ''),
                "{$p->name} submitted a decision.",
                $request->id,
            );

            $this->activity->log($p, 'client_' . $decision, $request, $from, $request->status);

            return $approval;
        });
    }

    private function assertCanApprove(Principal $p, Request $r): void
    {
        $ok = $p->role === Role::GlobalAdmin
            || ($p->role === Role::ClientOwner && $r->client_email === $p->clientEmail);
        if (!$ok) {
            throw new AuthorizationException('Only the client owner can approve this request');
        }
    }
}
