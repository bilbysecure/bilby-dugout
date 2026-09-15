<?php

declare(strict_types=1);

namespace Tests\Publishing;

use App\Domain\Models\MetaConnection;
use App\Domain\Models\ScheduledPost;
use App\Domain\Models\ScheduledPostTarget;
use App\Domain\Models\SocialChannel;
use App\Services\Meta\MetaClient;
use App\Services\Meta\MetaTokenStore;
use App\Services\Publishing\MetaPublishProvider;
use App\Support\Encryptor;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/** Live Meta delivery via the mocked Phase 6 client. */
final class MetaPublishProviderTest extends TestCase
{
    protected function setUp(): void
    {
        MetaConnection::query()->delete();
        ScheduledPost::query()->delete();
        ScheduledPostTarget::query()->delete();
        SocialChannel::query()->delete();
    }

    private function provider(array $responses): MetaPublishProvider
    {
        $mock = new MockHandler($responses);
        $http = new HttpClient(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://graph.test/']);
        $tokens = new MetaTokenStore(new Encryptor(str_repeat('k', 32)));
        return new MetaPublishProvider(new MetaClient($http, $tokens, ['api_version' => 'v21.0'], fn () => null));
    }

    private function connect(string $client = 'owner@acme.com', string $status = 'connected'): void
    {
        $conn = MetaConnection::create(['client_email' => $client, 'status' => $status]);
        (new MetaTokenStore(new Encryptor(str_repeat('k', 32))))->store($conn, 'tok');
    }

    private function scaffold(string $platform, string $externalId): array
    {
        $channel = SocialChannel::create(['client_email' => 'owner@acme.com', 'platform' => $platform, 'external_account_id' => $externalId, 'status' => 'connected']);
        $post = ScheduledPost::create(['client_email' => 'owner@acme.com', 'caption' => 'Hello', 'media' => [['url' => 'https://img/1.jpg']], 'status' => 'approved']);
        $target = ScheduledPostTarget::create(['scheduled_post_id' => $post->id, 'social_channel_id' => $channel->id, 'platform' => $platform, 'status' => 'scheduled']);
        return [$post, $target];
    }

    public function test_instagram_two_step_publish(): void
    {
        $this->connect();
        [$post, $target] = $this->scaffold('instagram', 'ig_123');
        $provider = $this->provider([
            new Response(200, [], json_encode(['id' => 'creation_1'])),  // media container
            new Response(200, [], json_encode(['id' => 'ig_post_1'])),   // media_publish
        ]);

        $result = $provider->publish($post, $target);
        self::assertNull($result['error']);
        self::assertSame('ig_post_1', $result['external_post_id']);
    }

    public function test_facebook_feed_publish(): void
    {
        $this->connect();
        [$post, $target] = $this->scaffold('facebook', 'page_1');
        $result = $this->provider([new Response(200, [], json_encode(['id' => 'fb_9']))])->publish($post, $target);

        self::assertNull($result['error']);
        self::assertSame('fb_9', $result['external_post_id']);
    }

    public function test_not_connected_returns_error_not_exception(): void
    {
        $this->connect('owner@acme.com', 'disconnected');
        [$post, $target] = $this->scaffold('instagram', 'ig_123');
        $result = $this->provider([])->publish($post, $target);

        self::assertNull($result['external_post_id']);
        self::assertStringContainsString('not connected', $result['error']);
    }

    public function test_meta_api_error_is_returned_as_failure(): void
    {
        $this->connect();
        [$post, $target] = $this->scaffold('facebook', 'page_1');
        $result = $this->provider([new Response(400, [], json_encode(['error' => ['code' => 100, 'message' => 'Invalid page']]))])->publish($post, $target);

        self::assertNull($result['external_post_id']);
        self::assertStringContainsString('Invalid page', $result['error']);
    }
}
