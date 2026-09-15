<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Domain\Models\MetaConnection;
use App\Support\Encryptor;

/**
 * Reads/writes the encrypted Meta token (Master Spec §16). Plaintext tokens live
 * only transiently in memory during an API call; at rest they are AES-GCM
 * ciphertext and are hidden from serialization at the model level.
 */
final class MetaTokenStore
{
    public function __construct(private readonly Encryptor $encryptor)
    {
    }

    public function store(MetaConnection $conn, string $token, ?string $expiresAt = null): void
    {
        $conn->token_encrypted = $this->encryptor->encrypt($token);
        $conn->token_expires_at = $expiresAt;
        $conn->save();
    }

    /** Decrypt the stored token, or null if none set. */
    public function get(MetaConnection $conn): ?string
    {
        if (empty($conn->token_encrypted)) {
            return null;
        }
        return $this->encryptor->decrypt((string) $conn->token_encrypted);
    }

    public function clear(MetaConnection $conn): void
    {
        $conn->token_encrypted = null;
        $conn->token_expires_at = null;
        $conn->save();
    }
}
