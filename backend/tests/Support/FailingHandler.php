<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Models\Job;
use App\Services\Jobs\JobHandler;
use RuntimeException;

/** Test fixture: a handler that always throws, to exercise retry/backoff/dead-letter. */
final class FailingHandler implements JobHandler
{
    public function handle(Job $job): void
    {
        throw new RuntimeException('boom');
    }
}
