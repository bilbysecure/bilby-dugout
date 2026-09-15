<?php

declare(strict_types=1);

namespace App\Services\Media;

use RuntimeException;

/**
 * AWS S3 driver — the planned production destination (Master Spec §17.2).
 *
 * Skeleton only: wiring it up is a config swap (`MEDIA_DISK=s3`) plus adding the
 * `aws/aws-sdk-php` dependency (flagged — not added without approval). The
 * method bodies below document the intended implementation so no service or
 * controller changes are needed when it lands:
 *
 *   - put/get/delete/exists → S3Client putObject/getObject/deleteObject/doesObjectExist
 *   - signedUrl → a pre-signed GET URL (createPresignedRequest), or a
 *     CloudFront-signed URL when 'cloudfront_url' is set (private distribution).
 *
 * Because it satisfies the same MediaStorage contract, call sites are identical.
 */
final class S3MediaStorage implements MediaStorage
{
    /** @param array $config the settings['media'] block (incl. ['s3']) */
    public function __construct(private readonly array $config)
    {
    }

    public function name(): string
    {
        return 's3';
    }

    public function put(string $key, string $contents): void
    {
        throw $this->notImplemented();
    }

    public function get(string $key): string
    {
        throw $this->notImplemented();
    }

    public function delete(string $key): void
    {
        throw $this->notImplemented();
    }

    public function exists(string $key): bool
    {
        throw $this->notImplemented();
    }

    public function signedUrl(string $key, int $ttlSeconds): string
    {
        // return (string) $this->client()->createPresignedRequest(
        //     $this->client()->getCommand('GetObject', ['Bucket' => ..., 'Key' => $key]),
        //     "+{$ttlSeconds} seconds"
        // )->getUri();
        throw $this->notImplemented();
    }

    private function notImplemented(): RuntimeException
    {
        return new RuntimeException('S3 media driver not yet implemented (requires aws/aws-sdk-php).');
    }
}
