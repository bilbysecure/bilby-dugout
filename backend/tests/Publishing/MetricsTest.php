<?php

declare(strict_types=1);

namespace Tests\Publishing;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\PostMetric;
use App\Domain\Models\ScheduledPost;
use App\Domain\Models\ScheduledPostTarget;
use App\Services\Publishing\ContentPerformanceService;
use App\Services\Publishing\MetricsService;
use PHPUnit\Framework\TestCase;

final class MetricsTest extends TestCase
{
    private MetricsService $svc;

    protected function setUp(): void
    {
        PostMetric::query()->delete();
        ScheduledPostTarget::query()->delete();
        ScheduledPost::query()->delete();
        $this->svc = new MetricsService();
    }

    /** @return array{0:ScheduledPost,1:ScheduledPostTarget} */
    private function postTarget(string $client = 'owner@acme.com', string $platform = 'instagram'): array
    {
        $post = ScheduledPost::create(['client_email' => $client, 'caption' => 'x', 'status' => 'published']);
        $target = ScheduledPostTarget::create(['scheduled_post_id' => $post->id, 'social_channel_id' => 1, 'platform' => $platform, 'status' => 'published', 'external_post_id' => 'ext_' . uniqid()]);
        return [$post, $target];
    }

    public function test_upsert_is_idempotent_per_target_and_date(): void
    {
        [$post, $t] = $this->postTarget();
        $this->svc->upsert($t, $post->id, 'owner@acme.com', 'instagram', '2026-07-26', ['impressions' => 100, 'engagement' => 10]);
        $this->svc->upsert($t, $post->id, 'owner@acme.com', 'instagram', '2026-07-26', ['impressions' => 250, 'engagement' => 40]);

        self::assertSame(1, PostMetric::where('scheduled_post_target_id', $t->id)->count(), 'one row per (target, date)');
        $row = PostMetric::where('scheduled_post_target_id', $t->id)->first();
        self::assertSame(250, $row->impressions, 'updated in place');
        self::assertSame(40, $row->engagement);
    }

    public function test_different_date_creates_a_new_row(): void
    {
        [$post, $t] = $this->postTarget();
        $this->svc->upsert($t, $post->id, 'owner@acme.com', 'instagram', '2026-07-26', ['reach' => 1]);
        $this->svc->upsert($t, $post->id, 'owner@acme.com', 'instagram', '2026-07-27', ['reach' => 2]);
        self::assertSame(2, PostMetric::where('scheduled_post_target_id', $t->id)->count());
    }

    public function test_parse_insights_maps_ig_and_fb_metric_names(): void
    {
        $ig = $this->svc->parseInsights(['data' => [
            ['name' => 'impressions', 'values' => [['value' => 500]]],
            ['name' => 'reach', 'values' => [['value' => 300]]],
            ['name' => 'saved', 'values' => [['value' => 12]]],
        ]]);
        self::assertSame(['impressions' => 500, 'reach' => 300, 'saves' => 12], $ig);

        $fb = $this->svc->parseInsights(['data' => [
            ['name' => 'post_impressions', 'values' => [['value' => 80]]],
            ['name' => 'post_impressions_unique', 'values' => [['value' => 60]]],
            ['name' => 'post_clicks', 'values' => [['value' => 7]]],
        ]]);
        self::assertSame(['impressions' => 80, 'reach' => 60, 'clicks' => 7], $fb);
    }

    public function test_content_performance_aggregates_and_scopes_per_role(): void
    {
        [$p1, $t1] = $this->postTarget('owner@acme.com', 'instagram');
        [$p2, $t2] = $this->postTarget('owner@acme.com', 'facebook');
        [$p3, $t3] = $this->postTarget('other@beta.com', 'instagram'); // other tenant — must not leak

        $this->svc->upsert($t1, $p1->id, 'owner@acme.com', 'instagram', '2026-07-26', ['impressions' => 100, 'engagement' => 20]);
        $this->svc->upsert($t2, $p2->id, 'owner@acme.com', 'facebook', '2026-07-26', ['impressions' => 50, 'engagement' => 5]);
        $this->svc->upsert($t3, $p3->id, 'other@beta.com', 'instagram', '2026-07-26', ['impressions' => 9999, 'engagement' => 9999]);

        $svc = new ContentPerformanceService();
        $client = new Principal(userId: 1, email: 'owner@acme.com', name: 'O', role: Role::ClientOwner, clientEmail: 'owner@acme.com');
        $perf = $svc->performance($client);

        self::assertSame(150, $perf['totals']['impressions'], 'own tenant only (100 + 50)');
        self::assertSame(25, $perf['totals']['engagement']);
        self::assertSame(2, $perf['tracked_targets']);
        self::assertSame($p1->id, $perf['top_posts'][0]['scheduled_post_id'], 'highest-engagement post first');
    }
}
