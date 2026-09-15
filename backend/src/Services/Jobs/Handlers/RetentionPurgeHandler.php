<?php

declare(strict_types=1);

namespace App\Services\Jobs\Handlers;

use App\Domain\Models\ActivityLog;
use App\Domain\Models\Job;
use App\Domain\Models\Notification;
use App\Domain\Models\RefreshToken;
use App\Services\Jobs\JobHandler;
use Psr\Log\LoggerInterface;

/**
 * Retention purges (Master Spec §17.4). Deletes rows older than a configured
 * window from activity_logs / notifications / refresh_tokens. Each window
 * defaults to 0 = DISABLED, so nothing is deleted until an operator opts in.
 *
 * The job name selects the target: retention.activity_logs | retention.notifications
 * | retention.refresh_tokens.
 */
final class RetentionPurgeHandler implements JobHandler
{
    public function __construct(
        private readonly array $retention, // settings['governance']['retention']
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Job $job): void
    {
        [$days, $delete] = match ($job->name) {
            'retention.activity_logs'  => [(int) ($this->retention['activity_logs_days'] ?? 0), fn (string $c) => ActivityLog::where('created_at', '<', $c)->delete()],
            'retention.notifications'  => [(int) ($this->retention['notifications_days'] ?? 0), fn (string $c) => Notification::where('created_at', '<', $c)->delete()],
            'retention.refresh_tokens' => [(int) ($this->retention['refresh_tokens_days'] ?? 0), fn (string $c) => RefreshToken::where('created_at', '<', $c)->delete()],
            default                    => [0, null],
        };

        if ($days <= 0 || $delete === null) {
            return; // disabled or unknown target — no-op
        }

        $cutoff = date('Y-m-d H:i:s', time() - $days * 86400);
        $removed = (int) $delete($cutoff);
        $this->logger->info("{$job->name}: purged {$removed} rows older than {$days}d");
    }
}
