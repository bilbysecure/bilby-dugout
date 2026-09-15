<?php

declare(strict_types=1);

namespace Tests\Jobs;

use App\Domain\Models\Job;
use App\Services\Jobs\QueueService;
use PHPUnit\Framework\TestCase;

final class QueueServiceTest extends TestCase
{
    private QueueService $queue;

    protected function setUp(): void
    {
        Job::query()->delete();
        $this->queue = new QueueService();
    }

    public function test_enqueue_creates_a_pending_job(): void
    {
        $job = $this->queue->enqueue('demo.job', ['k' => 'v'], 0, 'owner@acme.com');

        self::assertSame('pending', $job->status);
        self::assertSame('default', $job->queue);
        self::assertSame(0, $job->attempts);
        self::assertSame(3, $job->max_attempts);
        self::assertSame('owner@acme.com', $job->client_email);
        self::assertSame(['k' => 'v'], $job->payload);      // JSON round-trips via cast
        self::assertSame(1, Job::where('status', 'pending')->count());
    }

    public function test_delay_pushes_available_at_into_the_future(): void
    {
        $job = $this->queue->enqueue('demo.job', [], 300);

        $delta = strtotime((string) $job->available_at) - time();
        self::assertGreaterThan(280, $delta);
        self::assertLessThanOrEqual(300, $delta);
    }

    public function test_custom_queue_and_max_attempts(): void
    {
        $job = $this->queue->enqueue('demo.job', [], 0, null, 'webhooks', 5);

        self::assertSame('webhooks', $job->queue);
        self::assertSame(5, $job->max_attempts);
        self::assertNull($job->client_email);
    }
}
