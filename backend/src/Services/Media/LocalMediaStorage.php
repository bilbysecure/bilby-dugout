<?php

declare(strict_types=1);

namespace App\Services\Media;

use RuntimeException;

/**
 * Local filesystem driver. Files live OUTSIDE the web root, so they are never
 * statically served. A "signed URL" is an HMAC-signed link to the app's
 * streaming endpoint (see MediaController::blob) — the on-disk path is never
 * exposed. All path/fs specifics stay inside this class.
 */
final class LocalMediaStorage implements MediaStorage
{
    private string $root;
    private string $secret;
    private string $baseUrl;

    /** @param array $config the settings['media'] block */
    public function __construct(array $config)
    {
        $this->root = rtrim((string) $config['root'], '/\\');
        $this->secret = (string) ($config['url_secret'] ?? '');
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
    }

    public function name(): string
    {
        return 'local';
    }

    public function put(string $key, string $contents): void
    {
        $full = $this->absolute($key);
        $dir = dirname($full);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Unable to create media directory: {$dir}");
        }
        if (file_put_contents($full, $contents) === false) {
            throw new RuntimeException("Unable to write media object: {$key}");
        }
    }

    public function get(string $key): string
    {
        $full = $this->absolute($key);
        if (!is_file($full)) {
            throw new RuntimeException("Media object not found: {$key}");
        }
        return (string) file_get_contents($full);
    }

    public function delete(string $key): void
    {
        $full = $this->absolute($key);
        if (is_file($full)) {
            @unlink($full);
        }
    }

    public function exists(string $key): bool
    {
        return is_file($this->absolute($key));
    }

    public function signedUrl(string $key, int $ttlSeconds): string
    {
        $expires = time() + max(1, $ttlSeconds);
        $sig = $this->sign($key, $expires);
        return $this->baseUrl . '/api/v1/media/blob?p=' . rawurlencode($key)
            . '&e=' . $expires . '&s=' . $sig;
    }

    /** Verify a signed-blob request (used by the streaming endpoint). */
    public function verify(string $key, int $expires, string $sig): bool
    {
        if ($expires < time()) {
            return false;
        }
        return hash_equals($this->sign($key, $expires), $sig);
    }

    private function sign(string $key, int $expires): string
    {
        return hash_hmac('sha256', $key . '|' . $expires, $this->secret);
    }

    /** Resolve + contain the key within the storage root (no traversal). */
    private function absolute(string $key): string
    {
        $key = str_replace('\\', '/', $key);
        $key = ltrim($key, '/');
        if (str_contains($key, '..')) {
            throw new RuntimeException('Invalid media key');
        }
        return $this->root . '/' . $key;
    }
}
