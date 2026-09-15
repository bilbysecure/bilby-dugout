<?php

declare(strict_types=1);

namespace Tests\Campaigns;

use App\Domain\Models\Campaign;
use App\Domain\Models\CampaignAlert;
use App\Domain\Models\CampaignMetricDaily;
use App\Domain\Models\Notification;
use App\Domain\Models\TeamMember;
use App\Services\Campaigns\CampaignAlertService;
use App\Services\Mail\OutboxMailer;
use App\Services\NotificationService;
use PHPUnit\Framework\TestCase;

final class CampaignAlertTest extends TestCase
{
    private CampaignAlertService $svc;
    private Campaign $campaign;

    protected function setUp(): void
    {
        Campaign::query()->delete();
        CampaignAlert::query()->delete();
        CampaignMetricDaily::query()->delete();
        Notification::query()->delete();
        TeamMember::query()->delete();

        TeamMember::create(['full_name' => 'Ava', 'email' => 'admin@bilbypixel.com', 'is_admin' => true, 'status' => 'active']);
        $this->svc = new CampaignAlertService(new NotificationService(), new OutboxMailer(), []);
        $this->campaign = Campaign::create(['project_id' => 1, 'client_email' => 'owner@acme.com', 'objective' => 'lead_generation', 'total_budget' => 3000, 'start_date' => date('Y-m-d', strtotime('-10 days')), 'end_date' => date('Y-m-d', strtotime('+20 days')), 'status' => 'active']);
    }

    private function metric(string $date, array $m): void
    {
        (new \App\Services\Campaigns\CampaignMetricsService())->upsert($this->campaign, 'campaign', 'c_1', $date, $m);
    }

    public function test_cpl_over_target_triggers_and_notifies_staff(): void
    {
        CampaignAlert::create(['campaign_id' => $this->campaign->id, 'rule_type' => 'cpl_over_target', 'threshold' => 40, 'window_days' => 3]);
        // 3 days: $300 spend / 5 leads = $60 CPL > $40 target.
        $this->metric(date('Y-m-d', strtotime('-2 days')), ['spend' => 100, 'leads' => 2]);
        $this->metric(date('Y-m-d', strtotime('-1 days')), ['spend' => 100, 'leads' => 2]);
        $this->metric(date('Y-m-d'), ['spend' => 100, 'leads' => 1]);

        $triggered = $this->svc->evaluate($this->campaign);

        self::assertCount(1, $triggered);
        self::assertSame('triggered', CampaignAlert::first()->status);
        self::assertSame(1, Notification::where('type', 'campaign_alert')->where('recipient_email', 'admin@bilbypixel.com')->count());
    }

    public function test_cpl_under_target_stays_ok(): void
    {
        CampaignAlert::create(['campaign_id' => $this->campaign->id, 'rule_type' => 'cpl_over_target', 'threshold' => 40, 'window_days' => 3]);
        $this->metric(date('Y-m-d'), ['spend' => 100, 'leads' => 10]); // $10 CPL

        self::assertCount(0, $this->svc->evaluate($this->campaign));
        self::assertSame('ok', CampaignAlert::first()->status);
    }

    public function test_frequency_high_triggers(): void
    {
        CampaignAlert::create(['campaign_id' => $this->campaign->id, 'rule_type' => 'frequency_high', 'threshold' => 3]);
        $this->metric(date('Y-m-d'), ['impressions' => 10000, 'reach' => 2000]); // frequency 5 > 3

        self::assertCount(1, $this->svc->evaluate($this->campaign));
    }

    public function test_notification_fires_once_not_on_every_run(): void
    {
        CampaignAlert::create(['campaign_id' => $this->campaign->id, 'rule_type' => 'frequency_high', 'threshold' => 3]);
        $this->metric(date('Y-m-d'), ['impressions' => 10000, 'reach' => 1000]); // freq 10

        $this->svc->evaluate($this->campaign);
        $this->svc->evaluate($this->campaign); // still triggered — must NOT re-notify

        self::assertSame(1, Notification::where('type', 'campaign_alert')->count());
    }

    public function test_pacing_off_triggers_when_overspending(): void
    {
        CampaignAlert::create(['campaign_id' => $this->campaign->id, 'rule_type' => 'pacing_off', 'threshold' => 0.15]);
        // 10 of 30 days elapsed → expected ~$1000; actual $2000 → ~100% off.
        $this->metric(date('Y-m-d'), ['spend' => 2000, 'leads' => 5]);

        self::assertCount(1, $this->svc->evaluate($this->campaign));
    }
}
