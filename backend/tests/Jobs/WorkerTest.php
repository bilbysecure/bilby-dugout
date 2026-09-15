<?php

declare(strict_types=1);

namespace Tests\Jobs;

use App\Domain\Models\FailedJob;
use App\Domain\Models\Job;
use App\Domain\Models\Notification;
use App\Domain\Models\TeamMember;
use App\Services\Jobs\JobRegistry;
use App\Services\Jobs\QueueService;
use App\Services\Jobs\Worker;
use App\Services\NotificationService;
use Illuminate\Database\Capsule\Manager as DB;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\Support\FailingHandler;
use Tests\Support\SimpleContainer;

final class WorkerTest extends TestCase
{
    protected function setUp(): void
    {
        Job::query()->delete();
        FailedJob::query()->delete();
        Notification::query()->delete();
        TeamMember::query()->delete();
    }

    private function worker(): Worker
    {
        return new Worker(
            new SimpleContainer(),
            new JobRegistry(['fail' => FailingHandler::class]),
            new NotificationService(),
            new NullLogger(),
        );
    }

    private function reservedJob(int $attempts, int $maxAttempts): Job
    {
        // Simulate a job the worker has already reserved (attempts incremented at reserve).
        return Job::create([
            'queue' => 'default', 'name' => 'fail', 'payload' => [],
            'available_at' => date('Y-m-d H:i:s'), 'attempts' => $attempts,
            'max_attempts' => $maxAttempts, 'status' => 'reserved',
            'reserved_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function test_failure_applies_exponential_backoff(): void
    {
        $cases = [1 => 60, 2 => 300, 3 => 1800, 4 => 1800]; // clamped to last step
        foreach ($cases as $attempts => $expectedDelay) {
            $job = $this->reservedJob($attempts, 10);
            $this->worker()->process($job);

            $job->refresh();
            self::assertSame('pending', $job->status);
            self::assertNull($job->reserved_at);
            self::assertNotNull($job->last_error);

            $delta = strtotime((string) $job->available_at) - time();
            self::assertGreaterThan($expectedDelay - 5, $delta, "attempt {$attempts}");
            self::assertLessThanOrEqual($expectedDelay + 1, $delta, "attempt {$attempts}");
        }
    }

    public function test_dead_letters_after_max_attempts_and_notifies_admins(): void
    {
        TeamMember::create([
            'full_name' => 'Ava Admin', 'email' => 'admin@bilbypixel.com',
            'designation' => 'admin', 'is_admin' => true, 'status' => 'active',
        ]);
        // Non-admin staff should NOT be notified.
        TeamMember::create([
            'full_name' => 'Dana', 'email' => 'designer@bilbypixel.com',
            'designation' => 'graphic_designer', 'is_admin' => false, 'status' => 'active',
        ]);

        $job = $this->reservedJob(3, 3); // attempts == max
        $jobId = $job->id;

        $this->worker()->process($job);

        self::assertNull(Job::find($jobId), 'job removed from queue');
        self::assertSame(1, FailedJob::count(), 'moved to dead-letter');

        $failed = FailedJob::first();
        self::assertSame('fail', $failed->name);
        self::assertSame(3, $failed->attempts);
        self::assertStringContainsString('boom', (string) $failed->error);

        $notes = Notification::where('type', 'job_failed')->get();
        self::assertCount(1, $notes, 'only the admin is notified');
        self::assertSame('admin@bilbypixel.com', $notes->first()->recipient_email);
    }

    public function test_reserve_uses_skip_locked_mysql_only(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            self::markTestSkipped('FOR UPDATE SKIP LOCKED requires MariaDB/MySQL; not exercisable on '
                . DB::connection()->getDriverName() . '.');
        }

        (new QueueService())->enqueue('fail', [], 0);
        $reserved = $this->worker()->reserve('default');

        self::assertNotNull($reserved);
        self::assertSame('reserved', $reserved->status);
        self::assertSame(1, $reserved->attempts, 'attempts incremented at reservation');
        self::assertNull($this->worker()->reserve('default'), 'no second due job');
    }
}
