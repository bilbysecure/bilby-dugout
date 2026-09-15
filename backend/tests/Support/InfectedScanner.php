<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Models\Media;
use App\Services\Media\VirusScanner;

/** Test fixture: a scanner that flags everything as infected. */
final class InfectedScanner implements VirusScanner
{
    public function scan(Media $media, string $contents): string
    {
        return self::INFECTED;
    }
}
