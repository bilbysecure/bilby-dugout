<?php

declare(strict_types=1);

namespace Tests\Meta;

use App\Domain\Models\MetaConnection;
use App\Services\Meta\MetaTokenStore;
use App\Support\Encryptor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MetaTokenTest extends TestCase
{
    private Encryptor $enc;

    protected function setUp(): void
    {
        MetaConnection::query()->delete();
        $this->enc = new Encryptor(str_repeat('k', 32));
    }

    public function test_encrypt_decrypt_roundtrip(): void
    {
        $secret = 'EAAG_business_system_user_token_xyz';
        $cipher = $this->enc->encrypt($secret);

        self::assertStringStartsWith('v1.', $cipher);
        self::assertStringNotContainsString($secret, $cipher, 'plaintext never appears in ciphertext');
        self::assertSame($secret, $this->enc->decrypt($cipher));
    }

    public function test_tampered_ciphertext_fails_authentication(): void
    {
        $cipher = $this->enc->encrypt('token');
        $tampered = substr($cipher, 0, -2) . 'xx';
        $this->expectException(RuntimeException::class);
        $this->enc->decrypt($tampered);
    }

    public function test_wrong_key_cannot_decrypt(): void
    {
        $cipher = $this->enc->encrypt('token');
        $this->expectException(RuntimeException::class);
        (new Encryptor(str_repeat('z', 32)))->decrypt($cipher);
    }

    public function test_token_is_stored_encrypted_and_never_serialized(): void
    {
        $conn = MetaConnection::create(['client_email' => 'owner@acme.com', 'status' => 'disconnected']);
        (new MetaTokenStore($this->enc))->store($conn, 'super-secret-token');

        // Round-trips through decryption…
        self::assertSame('super-secret-token', (new MetaTokenStore($this->enc))->get($conn->fresh()));

        // …but the raw column is ciphertext and is hidden from API serialization.
        self::assertStringStartsWith('v1.', (string) $conn->fresh()->token_encrypted);
        self::assertArrayNotHasKey('token_encrypted', $conn->fresh()->toArray());
    }
}
