<?php

declare(strict_types=1);

namespace App\Services\Campaigns;

use App\Domain\Models\Campaign;
use App\Domain\Models\CampaignAlert;
use App\Domain\Models\CampaignMetricDaily;
use App\Domain\Models\TeamMember;
use App\Services\Mail\Mailer;
use App\Services\NotificationService;

/**
 * Evaluates campaign threshold rules (Master Spec §14): CPL over target for N
 * days, frequency > threshold, budget pacing off by > threshold. A newly
 * triggered rule notifies staff and (optionally) emails the client a digest.
 */
final class CampaignAlertService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly Mailer $mailer,
        private readonly array $config = [], // settings['campaigns'] (e.g. client_digest bool)
    ) {
    }

    /** @return CampaignAlert[] the rules that triggered this run */
    public function evaluate(Campaign $campaign): array
    {
        $triggered = [];

        foreach (CampaignAlert::where('campaign_id', $campaign->id)->where('enabled', true)->get() as $alert) {
            $message = match ($alert->rule_type) {
                'cpl_over_target' => $this->evalCpl($campaign, $alert),
                'frequency_high'  => $this->evalFrequency($campaign, $alert),
                'pacing_off'      => $this->evalPacing($campaign, $alert),
                default           => null,
            };

            $wasTriggered = $alert->status === 'triggered';
            if ($message !== null) {
                $alert->status = 'triggered';
                $alert->last_message = $message;
                $alert->last_triggered_at = date('Y-m-d H:i:s');
                $alert->save();
                $triggered[] = $alert;

                if (!$wasTriggered) { // notify once on transition into triggered
                    $this->notifyStaff($campaign, $message);
                    if (!empty($this->config['client_digest'])) {
                        $this->emailClient($campaign, $message);
                    }
                }
            } else {
                $alert->status = 'ok';
                $alert->save();
            }
        }

        return $triggered;
    }

    private function evalCpl(Campaign $campaign, CampaignAlert $alert): ?string
    {
        $window = (int) ($alert->window_days ?: 3);
        $rows = $this->campaignRows($campaign)->take($window);
        $spend = (float) $rows->sum('spend');
        $leads = (int) $rows->sum('leads');
        if ($leads === 0) {
            return null;
        }
        $cpl = $spend / $leads;
        return $cpl > (float) $alert->threshold
            ? sprintf('CPL $%.2f over the last %dd exceeds target $%.2f', $cpl, $window, (float) $alert->threshold)
            : null;
    }

    private function evalFrequency(Campaign $campaign, CampaignAlert $alert): ?string
    {
        $threshold = (float) ($alert->threshold ?: 3);
        $latest = $this->campaignRows($campaign)->first();
        if (!$latest) {
            return null;
        }
        return (float) $latest->frequency > $threshold
            ? sprintf('Ad frequency %.2f exceeds %.1f — creative fatigue risk', (float) $latest->frequency, $threshold)
            : null;
    }

    private function evalPacing(Campaign $campaign, CampaignAlert $alert): ?string
    {
        $budget = (float) $campaign->total_budget;
        if ($budget <= 0 || !$campaign->start_date || !$campaign->end_date) {
            return null;
        }
        $start = strtotime((string) $campaign->start_date);
        $end = strtotime((string) $campaign->end_date);
        $now = time();
        $duration = max(1, ($end - $start) / 86400);
        $elapsed = min($duration, max(0, ($now - $start) / 86400));
        if ($elapsed <= 0) {
            return null;
        }

        $expected = $budget * ($elapsed / $duration);
        // Collection sum (not the query aggregate) to avoid casting the SUM through decimal.
        $actual = (float) $this->campaignRows($campaign)->sum(fn ($r) => (float) $r->spend);
        $threshold = (float) ($alert->threshold ?: 0.15);
        $deviation = $expected > 0 ? abs($actual - $expected) / $expected : 0;

        return $deviation > $threshold
            ? sprintf('Budget pacing off by %.0f%% (spent $%.0f vs expected $%.0f)', $deviation * 100, $actual, $expected)
            : null;
    }

    private function campaignRows(Campaign $campaign)
    {
        return CampaignMetricDaily::where('campaign_id', $campaign->id)
            ->where('entity_type', 'campaign')
            ->orderByDesc('metric_date')->get();
    }

    private function notifyStaff(Campaign $campaign, string $message): void
    {
        $admins = TeamMember::where('is_admin', true)->where('status', 'active')->pluck('email')->filter()->all();
        $this->notifications->notifyMany($admins, 'campaign_alert', 'Campaign alert: ' . ($campaign->objective ?? 'campaign'), $message);
    }

    private function emailClient(Campaign $campaign, string $message): void
    {
        $this->mailer->send((string) $campaign->client_email, 'Your campaign needs attention', $message, 'campaign_digest');
    }
}
