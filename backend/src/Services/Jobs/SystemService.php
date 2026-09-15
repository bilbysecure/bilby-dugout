<?php

declare(strict_types=1);

namespace App\Services\Jobs;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\FailedJob;
use App\Domain\Models\Job;
use App\Policies\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Read/operate the queue for the System / Jobs screen. Global-admin only —
 * this is infrastructure, not tenant data, so it is not tenant-scoped.
 */
final class SystemService
{
    public function __construct(private readonly QueueService $queue)
    {
    }

    /** Queue depth + status/queue breakdown + a recent window. */
    public function overview(Principal $p): array
    {
        $this->assertAdmin($p);

        return [
            'queue_depth' => (int) Job::where('status', 'pending')->count(),
            'reserved'    => (int) Job::where('status', 'reserved')->count(),
            'failed'      => (int) FailedJob::count(),
            'by_status'   => Job::query()->selectRaw('status, COUNT(*) AS c')->groupBy('status')->pluck('c', 'status'),
            'by_queue'    => Job::query()->selectRaw('queue, COUNT(*) AS c')->groupBy('queue')->pluck('c', 'queue'),
            'recent'      => Job::orderByDesc('id')->limit(20)->get()->toArray(),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function failedJobs(Principal $p): array
    {
        $this->assertAdmin($p);
        return FailedJob::orderByDesc('failed_at')->limit(100)->get()->toArray();
    }

    /** Re-enqueue a dead-lettered job and remove it from failed_jobs. */
    public function replay(Principal $p, int $id): Job
    {
        $this->assertAdmin($p);

        $failed = FailedJob::find($id);
        if (!$failed) {
            throw new ModelNotFoundException('Failed job not found');
        }

        $job = $this->queue->enqueue(
            $failed->name,
            (array) ($failed->payload ?? []),
            0,
            $failed->client_email,
            $failed->queue,
        );
        $failed->delete();

        return $job;
    }

    private function assertAdmin(Principal $p): void
    {
        if ($p->role !== Role::GlobalAdmin) {
            throw new AuthorizationException('Global admin access required');
        }
    }
}
