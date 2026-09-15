<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Domain\Models\ScheduledPost;
use App\Domain\Models\ScheduledPostTarget;

/**
 * Boundary for actually pushing a post to an external network. Phase 3 provides
 * real implementations (Meta Graph for Instagram/Facebook incl. product tagging,
 * LinkedIn, X, etc.). The stub marks targets published without a network call.
 */
interface PublishProvider
{
    /** @return array{external_post_id:?string,error:?string} */
    public function publish(ScheduledPost $post, ScheduledPostTarget $target): array;
}
