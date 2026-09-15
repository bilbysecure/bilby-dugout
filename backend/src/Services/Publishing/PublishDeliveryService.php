<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Domain\Models\ScheduledPost;
use App\Domain\Models\ScheduledPostTarget;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Executes the actual per-target delivery (Master Spec §13). Shared by the
 * publish job. Enforces the approval gate — nothing here publishes a post that
 * hasn't cleared approval — and records per-target success/failure so failures
 * surface in the "Needs attention" rail.
 */
final class PublishDeliveryService
{
    /**
     * A post may only be delivered from these states (approval already cleared).
     * 'failed'/'publishing' are included so worker retries can re-attempt a post
     * that already passed approval — draft/pending_approval never reach them.
     */
    private const PUBLISHABLE = ['approved', 'scheduled', 'publishing', 'failed'];

    public function __construct(private readonly PublishProvider $publisher)
    {
    }

    public function deliver(ScheduledPost $post): ScheduledPost
    {
        if (!in_array($post->status, self::PUBLISHABLE, true)) {
            throw new InvalidArgumentException("Post {$post->id} is not cleared for publishing (status: {$post->status})");
        }

        $targets = ScheduledPostTarget::where('scheduled_post_id', $post->id)->get();
        if ($targets->isEmpty()) {
            throw new InvalidArgumentException('Post has no channels to publish to');
        }

        $post->status = 'publishing';
        $post->save();

        $allOk = true;
        foreach ($targets as $target) {
            // Skip targets already delivered (idempotent on job retry).
            if ($target->status === 'published' && $target->external_post_id) {
                continue;
            }
            $result = $this->publisher->publish($post, $target);
            $target->external_post_id = $result['external_post_id'];
            $target->error = $result['error'];
            $target->status = $result['error'] ? 'failed' : 'published';
            $target->published_at = $result['error'] ? null : Carbon::now();
            $target->save();
            $allOk = $allOk && !$result['error'];
        }

        $post->status = $allOk ? 'published' : 'failed';
        if ($allOk) {
            $post->published_at = Carbon::now();
        }
        $post->save();

        return $post->fresh();
    }
}
