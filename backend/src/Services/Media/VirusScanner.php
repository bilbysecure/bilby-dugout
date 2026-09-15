<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Domain\Models\Media;

/** Pluggable malware scanner. Returns the resulting scan_status. */
interface VirusScanner
{
    public const CLEAN = 'clean';
    public const INFECTED = 'infected';

    /** @return self::CLEAN|self::INFECTED */
    public function scan(Media $media, string $contents): string;
}
