<?php

declare(strict_types=1);

namespace App\Services\Jobs\Handlers;

use App\Domain\Models\Job;
use App\Services\Jobs\JobHandler;
use Psr\Log\LoggerInterface;

/**
 * Placeholder for recurring work dispatched by bin/scheduler. Registered so the
 * scheduler → queue → worker plumbing is exercisable; real recurring jobs
 * (publishing dispatch, insights refresh, …) replace/join this in later phases.
 */
final class NoopScheduledHandler implements JobHandler
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function handle(Job $job): void
    {
        $this->logger->info("scheduled.noop ran (job #{$job->id})");
    }
}
