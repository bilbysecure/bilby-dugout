<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Authenticated symmetric encryption (AES-256-GCM) for secrets at rest — e.g.
 * Meta tokens (Master Spec §16). Ciphertext is versioned so the scheme can
 * evolve. The key is 32 raw bytes, supplied by Bootstrap.
 */
final class Encryptor
{
    private const CIPHER = 'aes-256-gcm';
    private const VERSION = 'v1';

    public function __construct(private readonly string $key)
    {
        if (strlen($this->key) !== 32) {
            throw new RuntimeException('Encryption key must be exactly 32 bytes');
        }
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed');
        }
        return self::VERSION . '.' . base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $payload): string
    {
        $prefix = self::VERSION . '.';
        if (!str_starts_with($payload, $prefix)) {
            throw new RuntimeException('Unrecognized ciphertext');
        }
        $raw = base64_decode(substr($payload, strlen($prefix)), true);
        if ($raw === false || strlen($raw) < 29) {
            throw new RuntimeException('Corrupt ciphertext');
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);
        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false) {
            throw new RuntimeException('Decryption failed (bad key or tampered data)');
        }
        return $plaintext;
    }

    /** Derive a 32-byte key from a configured base64 key, or fall back to a secret. */
    public static function resolveKey(string $configuredBase64, string $fallbackSecret): string
    {
        if ($configuredBase64 !== '') {
            $decoded = base64_decode($configuredBase64, true);
            if ($decoded !== false && strlen($decoded) === 32) {
                return $decoded;
            }
        }
        // Deterministic 32-byte fallback (dev). Set APP_ENCRYPTION_KEY in production.
        return substr(hash('sha256', 'bilbyhub-meta:' . $fallbackSecret, true), 0, 32);
    }
}
