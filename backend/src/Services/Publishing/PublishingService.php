<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Auth\Principal;
use App\Domain\Enums\Role;
use App\Domain\Models\PostApproval;
use App\Domain\Models\ScheduledPost;
use App\Domain\Models\ScheduledPostTarget;
use App\Domain\Models\SocialChannel;
use App\Policies\AuthorizationException;
use App\Services\Jobs\QueueService;
use App\Support\TenantScope;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/**
 * Social post planning + publishing across multiple brands/channels, with
 * content-tag automation, an approval gate, and a pluggable publish provider.
 */
final class PublishingService
{
    private const FIELDS = [
        'brand_kit_id', 'title', 'caption', 'media', 'link', 'first_comment',
        'location_name', 'product_tags', 'tags', 'scheduled_at', 'timezone',
    ];

    public function __construct(
        private readonly TagRuleEngine $tagRules,
        private readonly QueueService $queue,
    ) {
    }

    public function list(Principal $p, array $filters = []): array
    {
        $q = TenantScope::apply(ScheduledPost::query(), $p);
        if (!empty($filters['brand_kit_id'])) {
            $q->where('brand_kit_id', (int) $filters['brand_kit_id']);
        }
        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (!empty($filters['from'])) {
            $q->where('scheduled_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $q->where('scheduled_at', '<=', $filters['to']);
        }
        return $q->orderBy('scheduled_at')->limit(500)->get()->toArray();
    }

    public function get(Principal $p, int $id): ScheduledPost
    {
        $post = TenantScope::apply(ScheduledPost::query(), $p)->whereKey($id)->first();
        if (!$post) {
            throw new ModelNotFoundException('Post not found');
        }
        return $post;
    }

    public function create(Principal $p, array $data): ScheduledPost
    {
        $this->assertCanWrite($p);
        $tenant = TenantScope::tenantFor($p, $data['client_email'] ?? null);

        $payload = array_intersect_key($data, array_flip(self::FIELDS));
        $channelIds = array_map('intval', (array) ($data['channel_ids'] ?? []));

        // Content-tag automation: enrich tags + suggested channels from rules.
        $channels = $this->resolveChannels($p, $channelIds);
        $platforms = array_values(array_unique(array_map(fn ($c) => $c->platform, $channels)));
        $auto = $this->tagRules->apply($tenant, (string) ($payload['caption'] ?? ''), $platforms, (array) ($payload['tags'] ?? []));
        $payload['tags'] = $auto['tags'];
        foreach ($auto['channel_ids'] as $cid) {
            if (!in_array($cid, $channelIds, true)) {
                $channelIds[] = $cid;
            }
        }

        $payload['client_email']    = $tenant;
        $payload['created_by_email'] = $p->email;
        $payload['created_by_name']  = $p->name ?: $p->email;
        $payload['status']           = $this->validStatus($data['status'] ?? 'draft');

        return DB::connection()->transaction(function () use ($p, $payload, $channelIds) {
            $post = ScheduledPost::create($payload);
            $this->syncTargets($p, $post, $channelIds);
            return $post->fresh();
        });
    }

    public function update(Principal $p, int $id, array $data): ScheduledPost
    {
        $post = $this->get($p, $id);
        $this->assertCanWrite($p);

        $post->fill(array_intersect_key($data, array_flip(self::FIELDS)));
        if (array_key_exists('status', $data)) {
            $post->status = $this->validStatus($data['status']);
        }
        $post->save();

        if (array_key_exists('channel_ids', $data)) {
            $this->syncTargets($p, $post, array_map('intval', (array) $data['channel_ids']));
        }
        return $post->fresh();
    }

    /** Send for content approval. */
    public function submit(Principal $p, int $id): ScheduledPost
    {
        $post = $this->get($p, $id);
        $post->status = 'pending_approval';
        $post->save();
        return $post->fresh();
    }

    /** Approve or reject (manager or client owner of the tenant). */
    public function decide(Principal $p, int $id, string $decision, ?string $note): ScheduledPost
    {
        $post = $this->get($p, $id);
        $this->assertCanApprove($p, $post->client_email);
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('decision must be approved or rejected');
        }

        PostApproval::create([
            'scheduled_post_id' => $post->id,
            'decision'          => $decision,
            'note'              => $note,
            'signed_by_email'   => $p->email,
            'signed_by_name'    => $p->name ?: $p->email,
        ]);

        // Approved posts with a time become 'scheduled'; otherwise 'approved'. Rejected → back to draft.
        $post->status = $decision === 'rejected'
            ? 'draft'
            : ($post->scheduled_at ? 'scheduled' : 'approved');
        $post->save();
        return $post->fresh();
    }

    public function schedule(Principal $p, int $id, string $scheduledAt): ScheduledPost
    {
        $post = $this->get($p, $id);
        $this->assertCanWrite($p);
        $post->scheduled_at = $scheduledAt;
        $post->status = 'scheduled';
        $post->save();
        ScheduledPostTarget::where('scheduled_post_id', $post->id)->update(['status' => 'scheduled']);
        return $post->fresh();
    }

    /**
     * Enqueue delivery (Master Spec §13). Publishing runs on the worker so it is
     * async with retry/backoff. APPROVAL GATE: only an approved/scheduled post
     * may be published — a draft or pending-approval post is rejected here.
     */
    public function publishNow(Principal $p, int $id): ScheduledPost
    {
        $post = $this->get($p, $id);
        $this->assertCanWrite($p);

        if (!in_array($post->status, ['approved', 'scheduled'], true)) {
            throw new InvalidArgumentException('This post has not cleared approval and cannot be published');
        }
        if (ScheduledPostTarget::where('scheduled_post_id', $post->id)->count() === 0) {
            throw new InvalidArgumentException('Post has no channels to publish to');
        }

        $post->status = 'publishing';
        $post->save();
        $this->queue->enqueue('publish.post', ['scheduled_post_id' => $post->id], 0, (string) $post->client_email, 'publishing');

        return $post->fresh();
    }

    public function cancel(Principal $p, int $id): ScheduledPost
    {
        $post = $this->get($p, $id);
        $this->assertCanWrite($p);
        $post->status = 'cancelled';
        $post->save();
        return $post->fresh();
    }

    // ── helpers ──────────────────────────────────────────────

    /** @return \Illuminate\Support\Collection<int,SocialChannel> */
    private function resolveChannels(Principal $p, array $channelIds)
    {
        if (!$channelIds) {
            return collect();
        }
        return TenantScope::apply(SocialChannel::query(), $p)->whereIn('id', $channelIds)->get();
    }

    private function syncTargets(Principal $p, ScheduledPost $post, array $channelIds): void
    {
        ScheduledPostTarget::where('scheduled_post_id', $post->id)->delete();
        foreach ($this->resolveChannels($p, $channelIds) as $channel) {
            ScheduledPostTarget::create([
                'scheduled_post_id' => $post->id,
                'social_channel_id' => $channel->id,
                'platform'          => $channel->platform,
                'status'            => $post->status === 'scheduled' ? 'scheduled' : 'pending',
            ]);
        }
    }

    private function validStatus(string $status): string
    {
        $allowed = ['draft', 'pending_approval', 'approved', 'scheduled', 'publishing', 'published', 'failed', 'cancelled'];
        return in_array($status, $allowed, true) ? $status : 'draft';
    }

    private function assertCanWrite(Principal $p): void
    {
        if (!$p->isAgency() && !$p->isClient()) {
            throw new AuthorizationException('You cannot manage posts');
        }
    }

    private function assertCanApprove(Principal $p, string $clientEmail): void
    {
        $ok = $p->isManager()
            || ($p->role === Role::ClientOwner && $p->clientEmail === $clientEmail);
        if (!$ok) {
            throw new AuthorizationException('Only a manager or client owner can approve posts');
        }
    }
}
