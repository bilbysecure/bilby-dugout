<?php

declare(strict_types=1);

namespace Tests\Tasks;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\Task;
use App\Domain\Models\TaskApproval;
use App\Domain\Models\TaskApprover;
use App\Repositories\TaskRepository;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use App\Services\TaskApprovalService;
use App\Services\TaskService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the dual-approval state machine (Master Spec §11 Layer B):
 * all-required gating, immediate kick-back, re-loop resets, internal-only
 * termination, client loop, and per-round history.
 */
final class TaskStateMachineTest extends TestCase
{
    private TaskService $tasks;
    private TaskApprovalService $approvals;

    protected function setUp(): void
    {
        Task::query()->delete();
        TaskApprover::query()->delete();
        TaskApproval::query()->delete();

        $this->tasks = new TaskService(new TaskRepository(), new ActivityLogger(), new NotificationService());
        $this->approvals = new TaskApprovalService(new TaskRepository(), new ActivityLogger(), new NotificationService());
    }

    private function manager(string $email = 'admin@bilbypixel.com'): Principal
    {
        return new Principal(userId: 1, email: $email, name: 'Mgr', role: Role::GlobalAdmin);
    }

    private function approver(string $email): Principal
    {
        return new Principal(userId: 9, email: $email, name: ucfirst(strtok($email, '@')), role: Role::AgencyStaff);
    }

    private function owner(string $email = 'owner@acme.com'): Principal
    {
        return new Principal(userId: 2, email: $email, name: 'Olivia', role: Role::ClientOwner, clientEmail: $email);
    }

    /** @param array $approvers list of [email, required] */
    private function makeTask(array $approvers, bool $requiresClient = true): Task
    {
        return $this->tasks->create($this->manager(), [
            'title' => 'Design task',
            'client_email' => 'owner@acme.com',
            'assigned_to' => 'designer@bilbypixel.com',
            'assigned_manager' => 'admin@bilbypixel.com',
            'requires_client_approval' => $requiresClient,
            'approvers' => array_map(fn ($a) => ['approver_id' => $a[0], 'is_required' => $a[1]], $approvers),
        ]);
    }

    private function sign(string $decision): array
    {
        return ['decision' => $decision, 'signature' => 'X', 'comment' => 'note'];
    }

    public function test_requires_at_least_one_required_approver(): void
    {
        $this->expectExceptionMessage('required internal approver');
        $this->makeTask([['a@x.com', false]]);
    }

    public function test_all_required_must_approve_before_client_gate(): void
    {
        $task = $this->makeTask([['a@x.com', true], ['b@x.com', true]]);
        $this->tasks->submit($this->manager(), $task->id);

        $this->approvals->decideInternal($this->approver('a@x.com'), $task->id, $this->sign('approve'));
        self::assertSame('pending_internal_approval', $task->fresh()->status, 'one of two — still waiting');

        $this->approvals->decideInternal($this->approver('b@x.com'), $task->id, $this->sign('approve'));
        self::assertSame('pending_client_approval', $task->fresh()->status, 'all required approved → client gate');
    }

    public function test_optional_approver_does_not_block_gate(): void
    {
        $task = $this->makeTask([['req@x.com', true], ['opt@x.com', false]]);
        $this->tasks->submit($this->manager(), $task->id);

        // Only the required approver approves; optional never acts.
        $this->approvals->decideInternal($this->approver('req@x.com'), $task->id, $this->sign('approve'));
        self::assertSame('pending_client_approval', $task->fresh()->status);
    }

    public function test_single_required_request_changes_kicks_back_immediately(): void
    {
        $task = $this->makeTask([['a@x.com', true], ['b@x.com', true]]);
        $this->tasks->submit($this->manager(), $task->id);

        $this->approvals->decideInternal($this->approver('a@x.com'), $task->id, $this->sign('approve'));
        $this->approvals->decideInternal($this->approver('b@x.com'), $task->id, $this->sign('request_changes'));

        self::assertSame('changes_requested', $task->fresh()->status);
    }

    public function test_reloop_resets_internal_approvals(): void
    {
        $task = $this->makeTask([['a@x.com', true], ['b@x.com', true]]);
        $this->tasks->submit($this->manager(), $task->id);                       // round 1
        $this->approvals->decideInternal($this->approver('a@x.com'), $task->id, $this->sign('approve'));
        $this->approvals->decideInternal($this->approver('b@x.com'), $task->id, $this->sign('request_changes')); // → changes_requested

        $this->tasks->submit($this->manager(), $task->id);                       // round 2 (reset)
        self::assertSame(2, $task->fresh()->current_round);

        // a@ approved in round 1 — that no longer counts; gate must NOT pass on b@ alone.
        $this->approvals->decideInternal($this->approver('b@x.com'), $task->id, $this->sign('approve'));
        self::assertSame('pending_internal_approval', $task->fresh()->status, 'round-1 approvals were reset');

        $this->approvals->decideInternal($this->approver('a@x.com'), $task->id, $this->sign('approve'));
        self::assertSame('pending_client_approval', $task->fresh()->status);
    }

    public function test_internal_only_task_never_reaches_client(): void
    {
        $task = $this->makeTask([['a@x.com', true]], requiresClient: false);
        $this->tasks->submit($this->manager(), $task->id);
        $this->approvals->decideInternal($this->approver('a@x.com'), $task->id, $this->sign('approve'));

        self::assertSame('internal_approved', $task->fresh()->status);

        $this->tasks->start($this->manager(), $task->id);
        self::assertSame('in_progress', $task->fresh()->status);
        $this->tasks->complete($this->manager(), $task->id);
        self::assertSame('done', $task->fresh()->status);
    }

    public function test_client_request_changes_reenters_internal_gate_and_resets(): void
    {
        $task = $this->makeTask([['a@x.com', true]]);
        $this->tasks->submit($this->manager(), $task->id);                       // round 1
        $this->approvals->decideInternal($this->approver('a@x.com'), $task->id, $this->sign('approve'));
        self::assertSame('pending_client_approval', $task->fresh()->status);

        $this->approvals->decideClient($this->owner(), $task->id, $this->sign('request_changes'));
        self::assertSame('changes_requested', $task->fresh()->status);

        $this->tasks->submit($this->manager(), $task->id);                       // round 2 → internal reset
        self::assertSame('pending_internal_approval', $task->fresh()->status);
        // Internal must re-approve (round 2); only then does it return to the client.
        $this->approvals->decideInternal($this->approver('a@x.com'), $task->id, $this->sign('approve'));
        self::assertSame('pending_client_approval', $task->fresh()->status);

        $this->approvals->decideClient($this->owner(), $task->id, $this->sign('approve'));
        self::assertSame('client_approved', $task->fresh()->status);
    }

    public function test_full_happy_path_to_done(): void
    {
        $task = $this->makeTask([['a@x.com', true]]);
        $this->tasks->submit($this->manager(), $task->id);
        $this->approvals->decideInternal($this->approver('a@x.com'), $task->id, $this->sign('approve'));
        $this->approvals->decideClient($this->owner(), $task->id, $this->sign('approve'));
        $this->tasks->start($this->manager(), $task->id);
        $this->tasks->complete($this->manager(), $task->id);
        self::assertSame('done', $task->fresh()->status);
    }

    public function test_per_round_history_is_preserved_with_incrementing_round(): void
    {
        $task = $this->makeTask([['a@x.com', true]]);
        $this->tasks->submit($this->manager(), $task->id);                       // round 1
        $this->approvals->decideInternal($this->approver('a@x.com'), $task->id, $this->sign('request_changes'));
        $this->tasks->submit($this->manager(), $task->id);                       // round 2
        $this->approvals->decideInternal($this->approver('a@x.com'), $task->id, $this->sign('approve'));

        $rows = TaskApproval::where('task_id', $task->id)->orderBy('id')->get();
        self::assertCount(2, $rows);
        self::assertSame(1, $rows[0]->round);
        self::assertSame('request_changes', $rows[0]->decision);
        self::assertSame(2, $rows[1]->round);
        self::assertSame('approve', $rows[1]->decision);
    }

    public function test_non_approver_cannot_decide(): void
    {
        $task = $this->makeTask([['a@x.com', true]]);
        $this->tasks->submit($this->manager(), $task->id);

        $this->expectExceptionMessage('not an approver');
        $this->approvals->decideInternal($this->approver('stranger@x.com'), $task->id, $this->sign('approve'));
    }

    public function test_client_cannot_decide_before_internal_gate(): void
    {
        $task = $this->makeTask([['a@x.com', true]]);
        $this->tasks->submit($this->manager(), $task->id);

        $this->expectExceptionMessage('not awaiting client approval');
        $this->approvals->decideClient($this->owner(), $task->id, $this->sign('approve'));
    }

    public function test_signature_is_required(): void
    {
        $task = $this->makeTask([['a@x.com', true]]);
        $this->tasks->submit($this->manager(), $task->id);

        $this->expectExceptionMessage('signature is required');
        $this->approvals->decideInternal($this->approver('a@x.com'), $task->id, ['decision' => 'approve']);
    }

    public function test_cancel_from_any_state(): void
    {
        $task = $this->makeTask([['a@x.com', true]]);
        $this->tasks->submit($this->manager(), $task->id);
        $this->tasks->cancel($this->manager(), $task->id);
        self::assertSame('cancelled', $task->fresh()->status);
    }
}
