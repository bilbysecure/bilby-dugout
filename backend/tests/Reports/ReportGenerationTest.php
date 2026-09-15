<?php

declare(strict_types=1);

namespace Tests\Reports;

use App\Domain\Models\BrandKit;
use App\Domain\Models\Job;
use App\Domain\Models\Media;
use App\Domain\Models\PostMetric;
use App\Domain\Models\Report;
use App\Domain\Models\ScheduledPost;
use App\Domain\Models\ScheduledPostTarget;
use App\Services\Jobs\Handlers\ReportGenerateHandler;
use App\Services\Mail\OutboxMailer;
use App\Services\Media\LocalMediaStorage;
use App\Services\Reports\HtmlReportRenderer;
use App\Services\Reports\ReportBrandingService;
use App\Services\Reports\ReportDataService;
use PHPUnit\Framework\TestCase;

/** The generation job: assemble → render → store media → mark ready. */
final class ReportGenerationTest extends TestCase
{
    private string $root;
    private LocalMediaStorage $storage;

    protected function setUp(): void
    {
        Report::query()->delete();
        Media::query()->delete();
        PostMetric::query()->delete();
        ScheduledPost::query()->delete();
        ScheduledPostTarget::query()->delete();
        BrandKit::query()->delete();

        $this->root = sys_get_temp_dir() . '/bilby_report_' . uniqid();
        $this->storage = new LocalMediaStorage(['root' => $this->root, 'url_secret' => 's', 'base_url' => 'http://x']);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            array_map('unlink', glob($this->root . '/*/*') ?: []);
            array_map('rmdir', glob($this->root . '/*') ?: []);
            @rmdir($this->root);
        }
    }

    private function handler(): ReportGenerateHandler
    {
        return new ReportGenerateHandler(new ReportDataService(), new ReportBrandingService(), new HtmlReportRenderer(), $this->storage, new OutboxMailer(), []);
    }

    private function generate(Report $report): void
    {
        $this->handler()->handle(new Job(['name' => 'report.generate', 'payload' => ['report_id' => $report->id]]));
    }

    public function test_generates_a_clean_media_record_and_marks_ready(): void
    {
        // Some source data.
        $post = ScheduledPost::create(['client_email' => 'owner@acme.com', 'caption' => 'x', 'status' => 'published']);
        $target = ScheduledPostTarget::create(['scheduled_post_id' => $post->id, 'social_channel_id' => 1, 'platform' => 'instagram', 'status' => 'published', 'external_post_id' => 'ig_1']);
        (new \App\Services\Publishing\MetricsService())->upsert($target, $post->id, 'owner@acme.com', 'instagram', '2026-07-15', ['impressions' => 1200, 'engagement' => 150]);

        $report = Report::create(['client_email' => 'owner@acme.com', 'type' => 'social_performance', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'status' => 'pending']);
        $this->generate($report);

        $report->refresh();
        self::assertSame('ready', $report->status);
        self::assertNotNull($report->media_id);
        self::assertNotNull($report->generated_at);

        $media = Media::find($report->media_id);
        self::assertSame('clean', $media->scan_status);
        self::assertSame('text/html', $media->mime);

        $html = $this->storage->get($media->path);
        self::assertStringContainsString('Social Performance Report', $html);
        self::assertStringContainsString('1200', $html, 'impressions rendered');
        self::assertStringContainsString('BilbyPixel', $html, 'default branding');
    }

    public function test_thin_data_still_renders_the_report_shell(): void
    {
        $report = Report::create(['client_email' => 'empty@acme.com', 'type' => 'campaign_wrapup', 'status' => 'pending']);
        $this->generate($report);

        self::assertSame('ready', $report->fresh()->status);
        $html = $this->storage->get(Media::find($report->fresh()->media_id)->path);
        self::assertStringContainsString('Ad Campaign Wrap-up', $html, 'shell renders even with no data');
    }

    public function test_white_label_report_uses_client_colour(): void
    {
        BrandKit::create(['client_email' => 'owner@acme.com', 'brand_name' => 'Acme Co', 'colors' => [['role' => 'primary', 'hex' => '#ff5722']]]);
        $report = Report::create(['client_email' => 'owner@acme.com', 'type' => 'delivery_summary', 'white_label' => true, 'status' => 'pending']);
        $this->generate($report);

        $html = $this->storage->get(Media::find($report->fresh()->media_id)->path);
        self::assertStringContainsString('#ff5722', $html, 'client primary colour applied');
        self::assertStringContainsString('Acme Co', $html);
        self::assertStringNotContainsString('Prepared by BilbyPixel', $html);
    }
}
