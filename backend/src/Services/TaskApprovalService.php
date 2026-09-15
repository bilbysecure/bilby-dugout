<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Enums\TaskStatus;
use App\Domain\Models\Task;
use App\Domain\Models\TaskApproval;
use App\Domain\Models\TaskApprover;
use App\Policies\AuthorizationException;
use App\Repositories\TaskRepository;
use App\Services\Approvals\SignedDecision;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/**
 * Task dual-approval decisions (Master Spec §11 Layer B).
 *
 * Internal gate: ALL required approvers must approve (optional approvers comment
 * but don't block). Any single required "request changes" kicks back to
 * changes_requested immediately. When the internal gate passes: → client gate if
 * requires_client_approval, else → internal_approved.
 *
 * Client gate: the single client owner approves (→ client_approved) or requests
 * changes (→ changes_requested, which re-enters the internal gate on re-submit).
 *
 * Reset-on-re-loop is round-based: TaskService::submit increments current_round,
 * so previous-round approvals (internal AND client) no longer count.
 */
final class TaskApprovalService
{
    private const DECISIONS = ['approve', 'request_changes'];

    public function __construct(
        private readonly TaskRepository $repo,
        private readonly ActivityLogger $activity,
        private readonly NotificationService $notifications,
    ) {
    }

    public function history(Principal $p, int $taskId): array
    {
        $task = $this->taskOrFail($p, $taskId);
        return TaskApproval::where('task_id', $task->id)
            ->orderBy('round')->orderBy('id')->get()->toArray();
    }

    /** An internal approver approves or requests changes. */
    public function decideInternal(Principal $p, int $taskId, array $data): Task
    {
        $task = $this->taskOrFail($p, $taskId);
        if ($task->status !== TaskStatus::PendingInternalApproval->value) {
            throw new InvalidArgumentException('Task is not awaiting internal approval');
        }

        $approver = TaskApprover::where('task_id', $task->id)->where('approver_id', $p->email)->first();
        if (!$approver) {
            throw new AuthorizationException('You are not an approver for this task');
        }

        $decision = SignedDecision::capture($p, $data, self::DECISIONS);

        return DB::connection()->transaction(function () use ($p, $task, $approver, $decision) {
            $this->record($task, $p->email, 'internal_manager', $decision);

            if ($decision->decision === 'request_changes' && $approver->is_required) {
                // Any single required "request changes" kicks back immediately.
                return $this->moveTo($p, $task, TaskStatus::ChangesRequested, 'task_internal_changes_requested', $this->reworkAudience($task));
            }

            if ($decision->decision === 'approve' && $this->internalGatePassed($task)) {
                if ($task->requires_client_approval) {
                    return $this->moveTo($p, $task, TaskStatus::PendingClientApproval, 'task_internal_approved', [(string) $task->client_email]);
                }
                return $this->moveTo($p, $task, TaskStatus::InternalApproved, 'task_internal_approved', $this->reworkAudience($task));
            }

            return $task; // recorded, but the gate isn't satisfied yet (or optional decision)
        });
    }

    /** The single client owner approves or requests changes. */
    public function decideClient(Principal $p, int $taskId, array $data): Task
    {
        $task = $this->taskOrFail($p, $taskId);
        if ($task->status !== TaskStatus::PendingClientApproval->value) {
            throw new InvalidArgumentException('Task is not awaiting client approval');
        }
        if (!($p->role === Role::ClientOwner && $task->client_email === $p->clientEmail)) {
            throw new AuthorizationException('Only the client owner can approve this task');
        }

        $decision = SignedDecision::capture($p, $data, self::DECISIONS);

        return DB::connection()->transaction(function () use ($p, $task, $decision) {
            $this->record($task, $p->email, 'client_owner', $decision);

            if ($decision->decision === 'request_changes') {
                // Re-enters the internal gate on re-submit; the round bump then resets everyone.
                return $this->moveTo($p, $task, TaskStatus::ChangesRequested, 'task_client_changes_requested', $this->reworkAudience($task));
            }
            return $this->moveTo($p, $task, TaskStatus::ClientApproved, 'task_client_approved', $this->reworkAudience($task));
        });
    }

    // ── internals ────────────────────────────────────────────────

    /** The internal gate passes when every required approver has an approve THIS round. */
    private function internalGatePassed(Task $task): bool
    {
        $required = TaskApprover::where('task_id', $task->id)->where('is_required', true)
            ->pluck('approver_id')->unique()->all();

        $approved = TaskApproval::where('task_id', $task->id)
            ->where('round', $task->current_round)
            ->where('approver_type', 'internal_manager')
            ->where('decision', 'approve')
            ->pluck('approver_id')->unique()->all();

        return $required !== [] && array_diff($required, $approved) === [];
    }

    private function record(Task $task, string $approverId, string $type, SignedDecision $d): void
    {
        TaskApproval::create([
            'task_id'       => $task->id,
            'approver_id'   => $approverId,
            'approver_type' => $type,
            'decision'      => $d->decision,
            'comment'       => $d->comment,
            'signature'     => $d->signature,
            'round'         => $task->current_round,
        ]);
    }

    /** @param string[] $notify */
    private function moveTo(Principal $p, Task $task, TaskStatus $to, string $action, array $notify): Task
    {
        $from = $task->status;
        $task->status = $to->value;
        $task->save();

        $this->activity->log($p, $action, null, $from, $task->status, 'task', ['task_id' => $task->id, 'round' => $task->current_round]);
        $this->notifications->notifyMany(
            $notify,
            'status_change',
            'Task ' . str_replace('_', ' ', $to->value) . ': ' . $task->title,
            "{$p->name} recorded a decision.",
        );
        return $task;
    }

    /** @return string[] the people who act on the reworked task (assignee + manager). */
    private function reworkAudience(Task $task): array
    {
        return array_values(array_filter([(string) $task->assigned_to, (string) $task->assigned_manager]));
    }

    private function taskOrFail(Principal $p, int $id): Task
    {
        $task = $this->repo->find($p, $id);
        if (!$task) {
            throw new ModelNotFoundException('Task not found');
        }
        return $task;
    }
}
