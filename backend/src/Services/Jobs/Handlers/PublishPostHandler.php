<?php

declare(strict_types=1);

namespace App\Services\Jobs\Handlers;

use App\Domain\Models\Job;
use App\Domain\Models\ScheduledPost;
use App\Services\Jobs\JobHandler;
use App\Services\Publishing\PublishDeliveryService;

/**
 * Delivers an approved post to its channels (Master Spec §13). Runs on the
 * worker so publishing is async with retry/backoff; individual target failures
 * are recorded (Needs attention) without failing the whole job, while an
 * unexpected error rethrows to trigger the queue's retry/backoff.
 */
final class PublishPostHandler implements JobHandler
{
    public function __construct(private readonly PublishDeliveryService $delivery)
    {
    }

    public function handle(Job $job): void
    {
        $postId = $job->payload['scheduled_post_id'] ?? null;
        if ($postId === null) {
            return;
        }
        $post = ScheduledPost::find($postId);
        if (!$post) {
            return;
        }
        $this->delivery->deliver($post);
    }
}
