<?php

declare(strict_types=1);

namespace App\Services\Jobs\Handlers;

use App\Domain\Models\Campaign;
use App\Domain\Models\Job;
use App\Domain\Models\MetaConnection;
use App\Services\Campaigns\CampaignMetricsService;
use App\Services\Jobs\JobHandler;
use App\Services\Meta\MetaClient;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Scheduled insights ingestion (Master Spec §14). Pulls campaign/adset/ad-level
 * insights for active campaigns via the Phase 6 Meta client and upserts by
 * (entity, date). Raw responses are stored (Meta restates within the attribution
 * window). Read-only (ads_read). Per-campaign failures are logged, not fatal.
 */
final class CampaignInsightsHandler implements JobHandler
{
    /** entity level → id field returned by Meta. */
    private const LEVELS = ['campaign' => null, 'adset' => 'adset_id', 'ad' => 'ad_id'];

    public function __construct(
        private readonly MetaClient $client,
        private readonly CampaignMetricsService $metrics,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Job $job): void
    {
        $date = date('Y-m-d');

        $campaigns = Campaign::where('status', 'active')->whereNotNull('meta_campaign_id')->limit(100)->get();
        foreach ($campaigns as $campaign) {
            $conn = MetaConnection::where('client_email', $campaign->client_email)->first();
            if (!$conn || !$conn->isConnected()) {
                continue;
            }

            foreach (self::LEVELS as $entityType => $idField) {
                try {
                    $response = $this->client->get($conn, "/{$campaign->meta_campaign_id}/insights", [
                        'level'       => $entityType,
                        'fields'      => 'impressions,reach,clicks,spend,frequency,ctr,actions,' . ($idField ?? 'campaign_id'),
                        'date_preset' => 'today',
                    ]);
                    foreach ($response['data'] ?? [] as $row) {
                        $entityId = $idField === null ? (string) $campaign->meta_campaign_id : (string) ($row[$idField] ?? '');
                        if ($entityId === '') {
                            continue;
                        }
                        $this->metrics->upsert($campaign, $entityType, $entityId, $date, $this->metrics->parseInsightRow($row), $row);
                    }
                } catch (Throwable $e) {
                    $this->logger->warning("campaign.insights ({$entityType}) failed for campaign {$campaign->id}: " . $e->getMessage());
                }
            }
        }
    }
}
