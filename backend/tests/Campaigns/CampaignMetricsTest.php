<?php

declare(strict_types=1);

namespace Tests\Campaigns;

use App\Domain\Models\Campaign;
use App\Domain\Models\CampaignMetricDaily;
use App\Services\Campaigns\CampaignMetricsService;
use PHPUnit\Framework\TestCase;

final class CampaignMetricsTest extends TestCase
{
    private CampaignMetricsService $svc;
    private Campaign $campaign;

    protected function setUp(): void
    {
        CampaignMetricDaily::query()->delete();
        Campaign::query()->delete();
        $this->svc = new CampaignMetricsService();
        $this->campaign = Campaign::create(['project_id' => 1, 'client_email' => 'owner@acme.com', 'objective' => 'lead_generation']);
    }

    public function test_upsert_is_idempotent_per_entity_and_date(): void
    {
        $this->svc->upsert($this->campaign, 'ad', 'ad_1', '2026-07-29', ['impressions' => 1000, 'clicks' => 50, 'leads' => 10, 'spend' => 100]);
        $this->svc->upsert($this->campaign, 'ad', 'ad_1', '2026-07-29', ['impressions' => 2000, 'clicks' => 90, 'leads' => 20, 'spend' => 180]); // restated

        self::assertSame(1, CampaignMetricDaily::where('entity_id', 'ad_1')->count());
        $row = CampaignMetricDaily::where('entity_id', 'ad_1')->first();
        self::assertSame(2000, $row->impressions, 'restatement overwrites in place');
        self::assertEqualsWithDelta(9.0, (float) $row->cpl, 0.001, 'CPL derived: 180 / 20');
        self::assertEqualsWithDelta(4.5, (float) $row->ctr, 0.001, 'CTR derived: 90/2000 * 100');
    }

    public function test_different_entities_and_dates_are_separate_rows(): void
    {
        $this->svc->upsert($this->campaign, 'campaign', 'c_1', '2026-07-29', ['impressions' => 1]);
        $this->svc->upsert($this->campaign, 'adset', 'as_1', '2026-07-29', ['impressions' => 1]);
        $this->svc->upsert($this->campaign, 'campaign', 'c_1', '2026-07-30', ['impressions' => 1]);
        self::assertSame(3, CampaignMetricDaily::count());
    }

    public function test_parse_insight_row_extracts_leads_from_actions(): void
    {
        $parsed = $this->svc->parseInsightRow([
            'impressions' => '5000', 'reach' => '4000', 'clicks' => '120', 'spend' => '250.50', 'frequency' => '1.25',
            'actions' => [
                ['action_type' => 'link_click', 'value' => '120'],
                ['action_type' => 'lead', 'value' => '18'],
                ['action_type' => 'onsite_conversion.lead_grouped', 'value' => '2'],
            ],
        ]);

        self::assertSame(5000, $parsed['impressions']);
        self::assertSame(20, $parsed['leads'], 'lead action types summed');
        self::assertSame(250.5, $parsed['spend']);
        self::assertSame(1.25, $parsed['frequency']);
    }
}
