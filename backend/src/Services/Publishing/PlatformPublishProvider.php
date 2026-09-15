<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Domain\Models\ScheduledPost;
use App\Domain\Models\ScheduledPostTarget;

/**
 * Routes each target to the right provider by platform (Master Spec §13):
 * Instagram/Facebook → live Meta delivery; everything else → the stub until its
 * network provider lands. Bound as the app's PublishProvider so the existing
 * publish flow is unchanged.
 */
final class PlatformPublishProvider implements PublishProvider
{
    private const META_PLATFORMS = ['instagram', 'facebook'];

    public function __construct(
        private readonly MetaPublishProvider $meta,
        private readonly StubPublishProvider $stub,
    ) {
    }

    public function publish(ScheduledPost $post, ScheduledPostTarget $target): array
    {
        return in_array($target->platform, self::META_PLATFORMS, true)
            ? $this->meta->publish($post, $target)
            : $this->stub->publish($post, $target);
    }
}
