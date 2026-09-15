<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Domain\Models\MetaConnection;
use App\Domain\Models\ScheduledPost;
use App\Domain\Models\ScheduledPostTarget;
use App\Domain\Models\SocialChannel;
use App\Services\Meta\MetaClient;
use Throwable;

/**
 * Live delivery to Instagram/Facebook via the Phase 6 Meta client (Master Spec
 * §13). Implements the EXISTING PublishProvider interface so it drops into the
 * current plan→approve→schedule→publish flow with no call-site changes.
 *
 * Failures are returned (never thrown) so the caller records a per-target
 * 'failed' status that surfaces in the "Needs attention" rail.
 */
final class MetaPublishProvider implements PublishProvider
{
    public function __construct(private readonly MetaClient $client)
    {
    }

    public function publish(ScheduledPost $post, ScheduledPostTarget $target): array
    {
        $conn = MetaConnection::where('client_email', $post->client_email)->first();
        if (!$conn || !$conn->isConnected()) {
            return $this->fail('Meta account is not connected for this client');
        }

        $channel = SocialChannel::find($target->social_channel_id);
        $externalId = $channel->external_account_id ?? null;
        if (!$externalId) {
            return $this->fail('Channel has no linked Meta account id');
        }

        try {
            return match ($target->platform) {
                'instagram' => $this->publishInstagram($conn, $externalId, $post),
                'facebook'  => $this->publishFacebook($conn, $externalId, $post),
                default     => $this->fail("Unsupported Meta platform: {$target->platform}"),
            };
        } catch (Throwable $e) {
            // Includes MetaThrottleException/MetaAuthException — surfaced as a target failure.
            return $this->fail(substr($e->getMessage(), 0, 255));
        }
    }

    /** Instagram: create a media container, then publish it (two-step Graph flow). */
    private function publishInstagram(MetaConnection $conn, string $igUserId, ScheduledPost $post): array
    {
        $mediaUrl = $this->firstMediaUrl($post);
        if ($mediaUrl === null) {
            return $this->fail('Instagram posts require media');
        }
        $container = $this->client->post($conn, "/{$igUserId}/media", [
            'caption'   => (string) $post->caption,
            'image_url' => $mediaUrl,
        ]);
        $creationId = $container['id'] ?? null;
        if ($creationId === null) {
            return $this->fail('Instagram media container was not created');
        }
        $published = $this->client->post($conn, "/{$igUserId}/media_publish", ['creation_id' => $creationId]);
        return ['external_post_id' => $published['id'] ?? null, 'error' => null];
    }

    private function publishFacebook(MetaConnection $conn, string $pageId, ScheduledPost $post): array
    {
        $params = ['message' => (string) $post->caption];
        if (!empty($post->link)) {
            $params['link'] = (string) $post->link;
        }
        $res = $this->client->post($conn, "/{$pageId}/feed", $params);
        return ['external_post_id' => $res['id'] ?? null, 'error' => null];
    }

    private function firstMediaUrl(ScheduledPost $post): ?string
    {
        foreach ((array) $post->media as $item) {
            $url = is_array($item) ? ($item['url'] ?? null) : (is_string($item) ? $item : null);
            if ($url) {
                return $url;
            }
        }
        return null;
    }

    /** @return array{external_post_id:null,error:string} */
    private function fail(string $message): array
    {
        return ['external_post_id' => null, 'error' => $message];
    }
}
