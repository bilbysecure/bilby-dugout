<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Domain\Models\Media;
use RuntimeException;

/**
 * Real ClamAV scanner — skeleton. Enable by binding VirusScanner => ClamAvScanner
 * in the container. Intended implementation: stream the bytes to clamd over its
 * INSTREAM TCP/unix-socket protocol and map the reply ("OK" / "FOUND") to a
 * status. Left unwired so the stack runs without a clamd daemon.
 */
final class ClamAvScanner implements VirusScanner
{
    /** @param array $config e.g. ['host' => '127.0.0.1', 'port' => 3310] */
    public function __construct(private readonly array $config = [])
    {
    }

    public function scan(Media $media, string $contents): string
    {
        // Connect to clamd, send zINSTREAM, read "stream: OK" | "stream: <sig> FOUND".
        throw new RuntimeException('ClamAvScanner not yet implemented (requires a clamd daemon).');
    }
}
