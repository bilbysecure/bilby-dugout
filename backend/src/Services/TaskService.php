<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Principal;
use App\Domain\Enums\TaskStatus;
use App\Domain\Models\Task;
use App\Domain\Models\TaskApprover;
use App\Policies\AuthorizationException;
use App\Repositories\TaskRepository;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/**
 * Task lifecycle (Master Spec §11 Layer B) — creation and the manual transitions
 * (submit, start, complete, cancel). The approval-driven transitions live in
 * TaskApprovalService. Managers manage; the assignee may submit/complete.
 */
final class TaskService
{
    private const FIELDS = ['project_id', 'client_email', 'title', 'description', 'assigned_to', 'assigned_manager', 'requires_client_approval', 'due_date'];

    public function __construct(
        private readonly TaskRepository $repo,
        private readonly ActivityLogger $activity,
        private readonly NotificationService $notifications,
    ) {
    }

    public function list(Principal $p): array
    {
        return $this->repo->visibleTo($p)->with('approvers')->orderByDesc('created_at')->get()->toArray();
    }

    public function get(Principal $p, int $id): Task
    {
        $task = $this->repo->find($p, $id);
        if (!$task) {
            throw new ModelNotFoundException('Task not found');
        }
        return $task;
    }

    public function create(Principal $p, array $data): Task
    {
        $this->assertManager($p);
        $payload = array_intersect_key($data, array_flip(self::FIELDS));
        if (empty($payload['title'])) {
            throw new InvalidArgumentException('title is required');
        }
        if (empty($payload['client_email'])) {
            throw new InvalidArgumentException('client_email is required');
        }
        $payload['requires_client_approval'] = (bool) ($data['requires_client_approval'] ?? true);
        $payload['status'] = TaskStatus::Draft->value;
        $payload['current_round'] = 0;
        $payload['created_by'] = $p->email;

        $approvers = $this->normalizeApprovers($data['approvers'] ?? []);
        if (!$this->hasRequired($approvers)) {
            throw new InvalidArgumentException('At least one required internal approver is needed');
        }

        return DB::connection()->transaction(function () use ($p, $payload, $approvers) {
            $task = Task::create($payload);
            foreach ($approvers as $a) {
                TaskApprover::create([
                    'task_id'       => $task->id,
                    'approver_id'   => $a['approver_id'],
                    'approver_type' => 'internal_manager',
                    'is_required'   => $a['is_required'],
                ]);
            }
            $this->activity->log($p, 'task_created', null, null, $task->status, 'task', ['task_id' => $task->id]);
            return $task->load('approvers');
        });
    }

    /** draft|changes_requested → pending_internal_approval. Increments the round (resets approvals). */
    public function submit(Principal $p, int $id): Task
    {
        $task = $this->get($p, $id);
        $this->assertCanSubmit($p, $task);
        if (!in_array($task->status, [TaskStatus::Draft->value, TaskStatus::ChangesRequested->value], true)) {
            throw new InvalidArgumentException('Only a draft or changes-requested task can be submitted');
        }

        $from = $task->status;
        $task->current_round = (int) $task->current_round + 1; // new round → previous approvals no longer count
        $task->status = TaskStatus::PendingInternalApproval->value;
        $task->save();

        $this->activity->log($p, 'task_submitted', null, $from, $task->status, 'task', ['task_id' => $task->id, 'round' => $task->current_round]);
        $this->notifications->notifyMany(
            $this->approverEmails($task),
            'status_change',
            'Task needs your approval: ' . $task->title,
            "{$p->name} submitted a task for internal approval.",
        );
        return $task;
    }

    /** internal_approved|client_approved → in_progress. */
    public function start(Principal $p, int $id): Task
    {
        $this->assertManager($p);
        $task = $this->get($p, $id);
        if (!in_array($task->status, [TaskStatus::InternalApproved->value, TaskStatus::ClientApproved->value], true)) {
            throw new InvalidArgumentException('Task must be approved before it can start');
        }
        $from = $task->status;
        $task->status = TaskStatus::InProgress->value;
        $task->save();
        $this->activity->log($p, 'task_started', null, $from, $task->status, 'task', ['task_id' => $task->id]);
        $this->notifications->notify((string) $task->assigned_to, 'status_change', 'Task started: ' . $task->title, 'Work has begun.');
        return $task;
    }

    /** in_progress → done. */
    public function complete(Principal $p, int $id): Task
    {
        $task = $this->get($p, $id);
        if (!$p->isManager() && $p->email !== $task->assigned_to) {
            throw new AuthorizationException('Only a manager or the assignee can complete this task');
        }
        if ($task->status !== TaskStatus::InProgress->value) {
            throw new InvalidArgumentException('Only an in-progress task can be completed');
        }
        $from = $task->status;
        $task->status = TaskStatus::Done->value;
        $task->save();
        $this->activity->log($p, 'task_completed', null, $from, $task->status, 'task', ['task_id' => $task->id]);
        return $task;
    }

    /** Any non-terminal state → cancelled. */
    public function cancel(Principal $p, int $id): Task
    {
        $this->assertManager($p);
        $task = $this->get($p, $id);
        if (TaskStatus::from($task->status)->isTerminal()) {
            throw new InvalidArgumentException('A finished task cannot be cancelled');
        }
        $from = $task->status;
        $task->status = TaskStatus::Cancelled->value;
        $task->save();
        $this->activity->log($p, 'task_cancelled', null, $from, $task->status, 'task', ['task_id' => $task->id]);
        return $task;
    }

    // ── helpers ──────────────────────────────────────────────────

    private function normalizeApprovers(mixed $items): array
    {
        $out = [];
        foreach ((array) $items as $a) {
            $email = trim((string) ($a['approver_id'] ?? $a['email'] ?? (is_string($a) ? $a : '')));
            if ($email === '') {
                continue;
            }
            $out[] = ['approver_id' => $email, 'is_required' => (bool) ($a['is_required'] ?? true)];
        }
        return $out;
    }

    private function hasRequired(array $approvers): bool
    {
        foreach ($approvers as $a) {
            if ($a['is_required']) {
                return true;
            }
        }
        return false;
    }

    private function approverEmails(Task $task): array
    {
        return TaskApprover::where('task_id', $task->id)->pluck('approver_id')->all();
    }

    private function assertManager(Principal $p): void
    {
        if (!$p->isManager()) {
            throw new AuthorizationException('Manager access required');
        }
    }

    private function assertCanSubmit(Principal $p, Task $task): void
    {
        if (!$p->isManager() && $p->email !== $task->assigned_to && $p->email !== $task->created_by) {
            throw new AuthorizationException('Not allowed to submit this task');
        }
    }
}
