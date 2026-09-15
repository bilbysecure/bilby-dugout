<?php

declare(strict_types=1);

namespace App\Services\Jobs\Handlers;

use App\Domain\Models\Job;
use App\Domain\Models\MetaConnection;
use App\Domain\Models\ScheduledPost;
use App\Domain\Models\ScheduledPostTarget;
use App\Services\Jobs\JobHandler;
use App\Services\Meta\MetaClient;
use App\Services\Publishing\MetricsService;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Scheduled pull of engagement from Meta for published posts (Master Spec §12).
 * Upserts each target's metrics for today; a per-target failure is logged and
 * skipped (never fails the whole run). No connection → nothing to pull.
 */
final class MetricsPullHandler implements JobHandler
{
    private const META_PLATFORMS = ['instagram', 'facebook'];

    public function __construct(
        private readonly MetaClient $client,
        private readonly MetricsService $metrics,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Job $job): void
    {
        $date = date('Y-m-d');

        $targets = ScheduledPostTarget::where('status', 'published')
            ->whereNotNull('external_post_id')
            ->whereIn('platform', self::META_PLATFORMS)
            ->limit(200)
            ->get();

        foreach ($targets as $target) {
            $post = ScheduledPost::find($target->scheduled_post_id);
            if (!$post) {
                continue;
            }
            $conn = MetaConnection::where('client_email', $post->client_email)->first();
            if (!$conn || !$conn->isConnected()) {
                continue; // no live connection for this tenant
            }

            try {
                $response = $this->client->get($conn, "/{$target->external_post_id}/insights", [
                    'metric' => $this->metrics->metricsQueryFor((string) $target->platform),
                ]);
                $parsed = $this->metrics->parseInsights($response);
                $this->metrics->upsert($target, $post->id, (string) $post->client_email, (string) $target->platform, $date, $parsed, $response);
            } catch (Throwable $e) {
                $this->logger->warning("metrics.pull failed for target {$target->id}: " . $e->getMessage());
            }
        }
    }
}
