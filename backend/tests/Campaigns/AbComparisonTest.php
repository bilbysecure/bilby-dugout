<?php

declare(strict_types=1);

namespace Tests\Campaigns;

use App\Domain\Models\AdVariant;
use App\Domain\Models\Campaign;
use App\Domain\Models\CampaignMetricDaily;
use App\Domain\Models\CampaignPhase;
use App\Services\Campaigns\AbComparisonService;
use App\Services\Campaigns\CampaignMetricsService;
use PHPUnit\Framework\TestCase;

final class AbComparisonTest extends TestCase
{
    private AbComparisonService $ab;
    private CampaignMetricsService $metrics;
    private Campaign $campaign;
    private CampaignPhase $phase;

    protected function setUp(): void
    {
        AdVariant::query()->delete();
        CampaignPhase::query()->delete();
        Campaign::query()->delete();
        CampaignMetricDaily::query()->delete();

        $this->ab = new AbComparisonService();
        $this->metrics = new CampaignMetricsService();
        $this->campaign = Campaign::create(['project_id' => 1, 'client_email' => 'owner@acme.com', 'objective' => 'lead_generation']);
        $this->phase = CampaignPhase::create(['campaign_id' => $this->campaign->id, 'phase_number' => 1, 'name' => 'Cold']);
    }

    private function variant(string $adId, bool $control): AdVariant
    {
        return AdVariant::create(['phase_id' => $this->phase->id, 'campaign_id' => $this->campaign->id, 'meta_ad_id' => $adId, 'headline' => $adId, 'is_control' => $control]);
    }

    private function metric(string $adId, int $impressions, int $leads): void
    {
        $this->metrics->upsert($this->campaign, 'ad', $adId, '2026-07-29', ['impressions' => $impressions, 'leads' => $leads, 'clicks' => (int) ($impressions * 0.02), 'spend' => 100]);
    }

    public function test_control_is_labelled_baseline(): void
    {
        $this->variant('ctrl', true);
        $this->metric('ctrl', 500, 10);

        $rows = $this->ab->compare($this->phase->id);
        self::assertSame('control', $rows[0]['status']);
        self::assertSame('baseline', $rows[0]['significance']);
    }

    public function test_small_sample_is_leading_not_a_winner(): void
    {
        $this->variant('ctrl', true);
        $this->variant('v1', false);
        $this->metric('ctrl', 200, 4);   // below MIN_IMPRESSIONS
        $this->metric('v1', 200, 8);      // higher rate but tiny sample

        $rows = $this->ab->compare($this->phase->id);
        $v1 = $this->row($rows, 'v1');
        self::assertSame('leading', $v1['status'], 'directional only — never a winner on a small sample');
        self::assertSame('insufficient_data', $v1['significance']);
    }

    public function test_large_significant_difference_is_flagged_winner(): void
    {
        $this->variant('ctrl', true);
        $this->variant('v1', false);
        // Big samples, clearly different conversion rates → significant.
        $this->metric('ctrl', 20000, 200); // 1.0%
        $this->metric('v1', 20000, 600);   // 3.0%

        $v1 = $this->row($this->ab->compare($this->phase->id), 'v1');
        self::assertSame('significant_winner', $v1['status']);
        self::assertSame('significant', $v1['significance']);
        self::assertGreaterThan(100, $v1['lift_pct'], 'roughly +200% lift');
    }

    public function test_large_sample_tiny_difference_is_not_significant(): void
    {
        $this->variant('ctrl', true);
        $this->variant('v1', false);
        $this->metric('ctrl', 20000, 300); // 1.50%
        $this->metric('v1', 20000, 306);   // 1.53% — negligible

        $v1 = $this->row($this->ab->compare($this->phase->id), 'v1');
        self::assertSame('not_significant', $v1['significance']);
        self::assertContains($v1['status'], ['leading', 'trailing'], 'directional, not a declared winner');
    }

    private function row(array $rows, string $headline): array
    {
        foreach ($rows as $r) {
            if ($r['headline'] === $headline) {
                return $r;
            }
        }
        self::fail("variant {$headline} not found");
    }
}
