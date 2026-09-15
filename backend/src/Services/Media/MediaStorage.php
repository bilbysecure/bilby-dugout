<?php

declare(strict_types=1);

namespace App\Services\Media;

/**
 * Storage driver contract (Master Spec §17.2). Controllers and services depend
 * ONLY on this interface — never on filesystem paths or the AWS SDK. Swapping
 * the local driver for S3 is a DI/config change with no call-site edits.
 *
 * `$key` is an opaque, driver-relative object key (e.g. "acme/uuid.png").
 */
interface MediaStorage
{
    public function name(): string;

    public function put(string $key, string $contents): void;

    public function get(string $key): string;

    public function delete(string $key): void;

    public function exists(string $key): bool;

    /**
     * A short-lived URL granting temporary read access to the object.
     * S3 returns a pre-signed (or CloudFront-signed) URL; the local driver
     * returns an HMAC-signed app URL to the streaming endpoint.
     */
    public function signedUrl(string $key, int $ttlSeconds): string;
}
