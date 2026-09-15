<?php

declare(strict_types=1);

namespace App\Services\Jobs;

/**
 * Recurring-job dispatcher (Master Spec §17.1). The external timer
 * (cron / systemd timer) invokes bin/scheduler on a cadence; this enqueues the
 * registered recurring jobs. Cadence is owned by the timer, not here.
 *
 * The registry is intentionally a set of STUBS for now — real recurring jobs
 * are registered as later phases land.
 */
final class Scheduler
{
    /** @var string[] job names (must exist in JobRegistry) to enqueue each tick. */
    public const RECURRING = [
        'scheduled.noop',
        // Pull social metrics back from Meta for published posts (§12).
        'metrics.pull',
        // Ad campaign insights ingestion + alert evaluation (§14).
        'campaign.insights',
        'campaign.alerts',
        // Monthly client report generation (§15) — idempotent per period.
        'reports.monthly',
        // Governance maintenance — each no-ops unless configured/enabled.
        'retention.activity_logs',
        'retention.notifications',
        'retention.refresh_tokens',
        'account.purge',
    ];

    public function __construct(private readonly QueueService $queue)
    {
    }

    /** Enqueue all registered recurring jobs. Returns the names dispatched. */
    public function dispatch(): array
    {
        foreach (self::RECURRING as $name) {
            $this->queue->enqueue($name, [], 0, null, 'scheduled');
        }
        return self::RECURRING;
    }
}
