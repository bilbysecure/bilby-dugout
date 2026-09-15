<?php

declare(strict_types=1);

namespace App\Services\Jobs;

use App\Domain\Models\Job;

/** Enqueues durable jobs. Reservation/execution lives in Worker. */
final class QueueService
{
    /**
     * Enqueue a job.
     *
     * @param string      $name         handler key registered in JobRegistry
     * @param array       $payload      JSON-serializable handler input
     * @param int         $delaySeconds delay before the job becomes runnable
     * @param string|null $clientEmail  tenant context carried to the handler
     */
    public function enqueue(
        string $name,
        array $payload = [],
        int $delaySeconds = 0,
        ?string $clientEmail = null,
        string $queue = 'default',
        int $maxAttempts = 3,
    ): Job {
        return Job::create([
            'queue'        => $queue,
            'name'         => $name,
            'payload'      => $payload,
            'client_email' => $clientEmail,
            'available_at' => date('Y-m-d H:i:s', time() + max(0, $delaySeconds)),
            'attempts'     => 0,
            'max_attempts' => $maxAttempts,
            'status'       => 'pending',
        ]);
    }
}
