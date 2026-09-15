<?php

declare(strict_types=1);

namespace App\Services\Jobs\Handlers;

use App\Domain\Models\Job;
use App\Domain\Models\Media;
use App\Services\Jobs\JobHandler;
use Psr\Log\LoggerInterface;

/**
 * Thumbnail / derivative generation for image media (Master Spec §17.2).
 *
 * Stub for now: the enqueue → handle plumbing is exercised, but derivative
 * rendering (e.g. Imagick/GD resize → a child `media` row attachable to the
 * original) lands when the image toolchain is chosen. Only runs once the
 * original is present; real work should also require scan_status = clean.
 */
final class MediaThumbnailHandler implements JobHandler
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function handle(Job $job): void
    {
        $mediaId = $job->payload['media_id'] ?? null;
        if ($mediaId === null || !Media::find($mediaId)) {
            return;
        }
        // TODO(image phase): generate + store derivatives, link via attachable.
        $this->logger->info("media.thumbnail queued for media #{$mediaId} (derivative generation stubbed)");
    }
}
