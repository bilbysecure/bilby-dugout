<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Domain\Models\Media;

/**
 * Credential-free scanner used for the current run: treats everything as clean.
 * Swap for ClamAvScanner (DI binding) to enable real scanning.
 */
final class StubVirusScanner implements VirusScanner
{
    public function scan(Media $media, string $contents): string
    {
        return self::CLEAN;
    }
}
