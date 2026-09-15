<?php

declare(strict_types=1);

namespace App\Services\Jobs;

use App\Domain\Models\FailedJob;
use App\Domain\Models\Job;
use App\Domain\Models\TeamMember;
use App\Services\NotificationService;
use Illuminate\Database\Capsule\Manager as DB;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Reserves and runs queued jobs (Master Spec §17.1).
 *
 * Reservation uses `SELECT … FOR UPDATE SKIP LOCKED` inside a transaction, so
 * many concurrent workers can pull from the same queue without double-running a
 * job. This requires MariaDB/MySQL — SQLite has no SKIP LOCKED (the CLI guards
 * against it, and the concurrency test is skipped off MySQL).
 *
 * On failure a job is retried with exponential backoff up to `max_attempts`,
 * then dead-lettered to `failed_jobs` and reported to global admins.
 */
final class Worker
{
    /** Backoff before the Nth retry, in seconds: 1m, 5m, 30m. */
    private const BACKOFF = [60, 300, 1800];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly JobRegistry $registry,
        private readonly NotificationService $notifications,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** Reserve + run one due job. Returns true if a job was processed. */
    public function runOnce(string $queue = 'default'): bool
    {
        $job = $this->reserve($queue);
        if (!$job) {
            return false;
        }
        $this->process($job);
        return true;
    }

    /**
     * Atomically claim the next due job. MariaDB only (FOR UPDATE SKIP LOCKED).
     * Increments attempts at reservation so a mid-run crash still counts.
     */
    public function reserve(string $queue = 'default'): ?Job
    {
        return DB::connection()->transaction(function () use ($queue) {
            /** @var Job|null $job */
            $job = Job::query()
                ->where('queue', $queue)
                ->where('status', 'pending')
                ->where('available_at', '<=', date('Y-m-d H:i:s'))
                ->orderBy('available_at')
                ->orderBy('id')
                ->limit(1)
                ->lock('for update skip locked')
                ->first();

            if (!$job) {
                return null;
            }

            $job->status = 'reserved';
            $job->reserved_at = date('Y-m-d H:i:s');
            $job->attempts = $job->attempts + 1;
            $job->save();

            return $job;
        });
    }

    /** Run a reserved job's handler, then ack or fail it. */
    public function process(Job $job): void
    {
        try {
            $class = $this->registry->handlerClass($job->name);
            if ($class === null) {
                throw new \RuntimeException("No handler registered for job '{$job->name}'");
            }
            /** @var JobHandler $handler */
            $handler = $this->container->get($class);
            $handler->handle($job);
            $job->delete(); // completed
        } catch (Throwable $e) {
            $this->fail($job, $e);
        }
    }

    private function fail(Job $job, Throwable $e): void
    {
        $this->logger->error("Job {$job->name}#{$job->id} attempt {$job->attempts} failed: {$e->getMessage()}");

        if ($job->attempts >= $job->max_attempts) {
            $this->deadLetter($job, $e);
            return;
        }

        // Backoff by attempt number, clamped to the last defined step.
        $delay = self::BACKOFF[min($job->attempts, count(self::BACKOFF)) - 1];
        $job->status = 'pending';
        $job->reserved_at = null;
        $job->available_at = date('Y-m-d H:i:s', time() + $delay);
        $job->last_error = $this->errorText($e);
        $job->save();
    }

    private function deadLetter(Job $job, Throwable $e): void
    {
        FailedJob::create([
            'queue'        => $job->queue,
            'name'         => $job->name,
            'payload'      => $job->payload,
            'client_email' => $job->client_email,
            'attempts'     => $job->attempts,
            'error'        => $this->errorText($e),
            'failed_at'    => date('Y-m-d H:i:s'),
        ]);
        $this->logger->error("Job {$job->name}#{$job->id} dead-lettered after {$job->attempts} attempts");
        $this->notifyAdmins($job, $e);
        $job->delete();
    }

    private function notifyAdmins(Job $job, Throwable $e): void
    {
        $emails = TeamMember::query()
            ->where('is_admin', true)
            ->where('status', 'active')
            ->pluck('email')
            ->filter()
            ->values()
            ->all();

        $this->notifications->notifyMany(
            $emails,
            'job_failed',
            'Background job failed',
            "Job '{$job->name}' failed after {$job->attempts} attempts: " . $this->shortError($e),
        );
    }

    private function errorText(Throwable $e): string
    {
        return substr(get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 0, 5000);
    }

    private function shortError(Throwable $e): string
    {
        return substr($e->getMessage(), 0, 200);
    }
}
