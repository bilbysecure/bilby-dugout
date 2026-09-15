<?php

declare(strict_types=1);

namespace App\Services\Jobs;

use App\Domain\Models\Job;

/**
 * A unit of background work. Handlers read `$job->payload` (array) and
 * `$job->client_email` (tenant context) and MUST route any tenant data access
 * through the scoped repositories — never an unscoped query.
 * Throwing from handle() triggers retry/backoff in the Worker.
 */
interface JobHandler
{
    public function handle(Job $job): void;
}
