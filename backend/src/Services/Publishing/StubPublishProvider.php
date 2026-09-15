<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Domain\Models\ScheduledPost;
use App\Domain\Models\ScheduledPostTarget;

/**
 * Default provider until Phase 3 wires the real networks. Simulates a successful
 * publish so the whole planning/approval/scheduling flow is testable end-to-end.
 */
final class StubPublishProvider implements PublishProvider
{
    public function publish(ScheduledPost $post, ScheduledPostTarget $target): array
    {
        return [
            'external_post_id' => 'stub_' . $target->platform . '_' . bin2hex(random_bytes(4)),
            'error'            => null,
        ];
    }
}
