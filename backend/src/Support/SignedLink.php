<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

/**
 * Stateless, signed, expiring links (Master Spec §17.3) — e.g. the invite
 * set-password link. Payload is HMAC-signed with the app secret and carries its
 * own expiry, so no server-side token store is needed.
 *
 * Token format: base64url(json_payload) . "." . base64url(hmac_sha256)
 */
final class SignedLink
{
    /** @param array<string,mixed> $payload */
    public static function sign(array $payload, int $ttlSeconds, string $secret): string
    {
        $payload['exp'] = time() + $ttlSeconds;
        $body = self::b64(json_encode($payload, JSON_UNESCAPED_SLASHES));
        return $body . '.' . self::b64(hash_hmac('sha256', $body, $secret, true));
    }

    /**
     * Verify + decode a token. Throws on tampering or expiry.
     *
     * @return array<string,mixed>
     */
    public static function verify(string $token, string $secret): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException('Malformed token');
        }
        [$body, $sig] = $parts;
        $expected = self::b64(hash_hmac('sha256', $body, $secret, true));
        if (!hash_equals($expected, $sig)) {
            throw new InvalidArgumentException('Invalid token signature');
        }
        $payload = json_decode(self::unb64($body), true);
        if (!is_array($payload)) {
            throw new RuntimeException('Corrupt token payload');
        }
        if (($payload['exp'] ?? 0) < time()) {
            throw new InvalidArgumentException('Token has expired');
        }
        return $payload;
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64(string $enc): string
    {
        return (string) base64_decode(strtr($enc, '-_', '+/'), true);
    }
}
