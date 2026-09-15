<?php

declare(strict_types=1);

namespace Tests\Publishing;

use App\Domain\Models\Job;
use App\Domain\Models\ScheduledPost;
use App\Domain\Models\ScheduledPostTarget;
use App\Services\Jobs\Handlers\PublishPostHandler;
use App\Services\Publishing\PublishDeliveryService;
use App\Services\Publishing\PublishProvider;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Approval gate + per-target failure handling for the async publish flow. */
final class PublishDeliveryTest extends TestCase
{
    protected function setUp(): void
    {
        ScheduledPost::query()->delete();
        ScheduledPostTarget::query()->delete();
    }

    /** A provider whose result is chosen per platform (fixture double). */
    private function provider(array $byPlatform): PublishProvider
    {
        return new class ($byPlatform) implements PublishProvider {
            public function __construct(private array $byPlatform)
            {
            }

            public function publish(ScheduledPost $post, ScheduledPostTarget $target): array
            {
                return $this->byPlatform[$target->platform] ?? ['external_post_id' => 'ok_' . $target->platform, 'error' => null];
            }
        };
    }

    private function postWithTargets(string $status, array $platforms): ScheduledPost
    {
        $post = ScheduledPost::create(['client_email' => 'owner@acme.com', 'caption' => 'Hi', 'status' => $status]);
        foreach ($platforms as $i => $plat) {
            ScheduledPostTarget::create(['scheduled_post_id' => $post->id, 'social_channel_id' => $i + 1, 'platform' => $plat, 'status' => 'scheduled']);
        }
        return $post;
    }

    public function test_unapproved_post_cannot_be_delivered(): void
    {
        $post = $this->postWithTargets('pending_approval', ['facebook']);
        $delivery = new PublishDeliveryService($this->provider([]));

        $this->expectExceptionMessage('not cleared for publishing');
        $delivery->deliver($post);
    }

    public function test_all_targets_ok_marks_post_published(): void
    {
        $post = $this->postWithTargets('approved', ['instagram', 'facebook']);
        (new PublishDeliveryService($this->provider([])))->deliver($post);

        self::assertSame('published', $post->fresh()->status);
        foreach (ScheduledPostTarget::where('scheduled_post_id', $post->id)->get() as $t) {
            self::assertSame('published', $t->status);
            self::assertNotNull($t->external_post_id);
        }
    }

    public function test_one_failed_target_marks_post_failed_and_records_error(): void
    {
        $post = $this->postWithTargets('approved', ['instagram', 'facebook']);
        $delivery = new PublishDeliveryService($this->provider([
            'facebook' => ['external_post_id' => null, 'error' => 'Page token expired'],
        ]));

        $delivery->deliver($post);

        self::assertSame('failed', $post->fresh()->status, 'a single failure fails the post');
        $fb = ScheduledPostTarget::where('scheduled_post_id', $post->id)->where('platform', 'facebook')->first();
        $ig = ScheduledPostTarget::where('scheduled_post_id', $post->id)->where('platform', 'instagram')->first();
        self::assertSame('failed', $fb->status);
        self::assertSame('Page token expired', $fb->error, 'surfaces in Needs attention');
        self::assertSame('published', $ig->status, 'the healthy target still delivered');
    }

    public function test_retry_is_idempotent_for_already_published_targets(): void
    {
        $post = $this->postWithTargets('approved', ['instagram', 'facebook']);
        // First run: facebook fails.
        (new PublishDeliveryService($this->provider(['facebook' => ['external_post_id' => null, 'error' => 'blip']])))->deliver($post);
        $igId = ScheduledPostTarget::where('scheduled_post_id', $post->id)->where('platform', 'instagram')->first()->external_post_id;

        // Retry: now everything succeeds; instagram must NOT be re-posted.
        (new PublishDeliveryService($this->provider([])))->deliver($post->fresh());

        self::assertSame('published', $post->fresh()->status);
        self::assertSame($igId, ScheduledPostTarget::where('scheduled_post_id', $post->id)->where('platform', 'instagram')->first()->external_post_id, 'no duplicate IG post');
    }

    public function test_handler_delivers_the_post(): void
    {
        $post = $this->postWithTargets('approved', ['facebook']);
        (new PublishPostHandler(new PublishDeliveryService($this->provider([]))))
            ->handle(new Job(['name' => 'publish.post', 'payload' => ['scheduled_post_id' => $post->id]]));

        self::assertSame('published', $post->fresh()->status);
    }
}
