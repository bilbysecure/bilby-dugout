<?php

declare(strict_types=1);

namespace App\Services\Jobs\Handlers;

use App\Domain\Models\Campaign;
use App\Domain\Models\Job;
use App\Services\Campaigns\CampaignAlertService;
use App\Services\Jobs\JobHandler;
use Psr\Log\LoggerInterface;
use Throwable;

/** Scheduled campaign-alert evaluation (Master Spec §14). */
final class CampaignAlertHandler implements JobHandler
{
    public function __construct(
        private readonly CampaignAlertService $alerts,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Job $job): void
    {
        foreach (Campaign::where('status', 'active')->limit(200)->get() as $campaign) {
            try {
                $this->alerts->evaluate($campaign);
            } catch (Throwable $e) {
                $this->logger->warning("campaign.alerts failed for campaign {$campaign->id}: " . $e->getMessage());
            }
        }
    }
}
