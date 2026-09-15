<?php

declare(strict_types=1);

namespace App\Services\Jobs;

use App\Services\Jobs\Handlers\AccountPurgeHandler;
use App\Services\Jobs\Handlers\CampaignAlertHandler;
use App\Services\Jobs\Handlers\CampaignInsightsHandler;
use App\Services\Jobs\Handlers\DataExportHandler;
use App\Services\Jobs\Handlers\MediaScanHandler;
use App\Services\Jobs\Handlers\MediaThumbnailHandler;
use App\Services\Jobs\Handlers\MetricsPullHandler;
use App\Services\Jobs\Handlers\MonthlyReportsHandler;
use App\Services\Jobs\Handlers\ReportGenerateHandler;
use App\Services\Jobs\Handlers\PublishPostHandler;
use App\Services\Jobs\Handlers\NoopScheduledHandler;
use App\Services\Jobs\Handlers\ProcessStripeWebhookHandler;
use App\Services\Jobs\Handlers\RetentionPurgeHandler;

/**
 * Maps a job name (stored on the row) to its handler class. Jobs never store a
 * class name in the DB — the worker resolves handlers only through this map, so
 * an attacker who wrote to `jobs` still cannot execute an arbitrary class.
 *
 * The map is injectable so tests can register fixture handlers.
 */
final class JobRegistry
{
    /** @var array<string,class-string<JobHandler>> */
    public const DEFAULTS = [
        // Inbound webhook processing
        'stripe.webhook'   => ProcessStripeWebhookHandler::class,

        // Media pipeline (Master Spec §17.2)
        'media.scan'       => MediaScanHandler::class,
        'media.thumbnail'  => MediaThumbnailHandler::class,

        // Publishing + metrics (Master Spec §13 / §12)
        'publish.post'     => PublishPostHandler::class,
        'metrics.pull'     => MetricsPullHandler::class,

        // Ad campaign module (Master Spec §14)
        'campaign.insights' => CampaignInsightsHandler::class,
        'campaign.alerts'   => CampaignAlertHandler::class,

        // Client-facing reports (Master Spec §15)
        'report.generate'  => ReportGenerateHandler::class,
        'reports.monthly'  => MonthlyReportsHandler::class,

        // Governance (Master Spec §17.4)
        'data.export'              => DataExportHandler::class,
        'account.purge'            => AccountPurgeHandler::class,      // guarded (purge_enabled)
        'retention.activity_logs'  => RetentionPurgeHandler::class,   // window default 0 = disabled
        'retention.notifications'  => RetentionPurgeHandler::class,
        'retention.refresh_tokens' => RetentionPurgeHandler::class,

        // Recurring dispatcher stubs (real jobs land in later phases)
        'scheduled.noop'   => NoopScheduledHandler::class,
    ];

    /** @param array<string,class-string<JobHandler>> $map */
    public function __construct(private readonly array $map = self::DEFAULTS)
    {
    }

    /** @return class-string<JobHandler>|null */
    public function handlerClass(string $name): ?string
    {
        return $this->map[$name] ?? null;
    }

    /** @return string[] */
    public function names(): array
    {
        return array_keys($this->map);
    }
}
