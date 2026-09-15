<?php

declare(strict_types=1);

namespace App\Services\Jobs\Handlers;

use App\Domain\Models\Job;
use App\Domain\Models\Media;
use App\Services\Jobs\JobHandler;
use App\Services\Media\MediaStorage;
use App\Services\Media\VirusScanner;

/**
 * Async virus scan (Master Spec §17.2). Reads the stored bytes, runs the
 * pluggable scanner, and flips scan_status to clean/infected. Until this runs,
 * the file stays quarantined (pending) and unservable.
 */
final class MediaScanHandler implements JobHandler
{
    public function __construct(
        private readonly MediaStorage $storage,
        private readonly VirusScanner $scanner,
    ) {
    }

    public function handle(Job $job): void
    {
        $mediaId = $job->payload['media_id'] ?? null;
        if ($mediaId === null) {
            return;
        }

        /** @var Media|null $media */
        $media = Media::find($mediaId);
        if (!$media) {
            return;
        }

        $contents = $this->storage->get($media->path);
        $status = $this->scanner->scan($media, $contents);

        $media->scan_status = $status === VirusScanner::INFECTED ? 'infected' : 'clean';
        $media->save();
    }
}
