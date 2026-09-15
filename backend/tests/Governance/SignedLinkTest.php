<?php

declare(strict_types=1);

namespace Tests\Governance;

use App\Support\SignedLink;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SignedLinkTest extends TestCase
{
    private const SECRET = 'test-secret-key';

    public function test_sign_then_verify_roundtrips_payload(): void
    {
        $token = SignedLink::sign(['email' => 'owner@acme.com', 'purpose' => 'set_password'], 3600, self::SECRET);
        $payload = SignedLink::verify($token, self::SECRET);

        self::assertSame('owner@acme.com', $payload['email']);
        self::assertSame('set_password', $payload['purpose']);
        self::assertGreaterThan(time(), $payload['exp']);
    }

    public function test_expired_token_is_rejected(): void
    {
        $token = SignedLink::sign(['email' => 'x@y.com'], -1, self::SECRET); // already expired
        $this->expectExceptionMessage('expired');
        SignedLink::verify($token, self::SECRET);
    }

    public function test_tampered_payload_is_rejected(): void
    {
        $token = SignedLink::sign(['email' => 'a@b.com'], 3600, self::SECRET);
        [$body, $sig] = explode('.', $token);
        $forged = rtrim(strtr(base64_encode('{"email":"attacker@evil.com","exp":9999999999}'), '+/', '-_'), '=') . '.' . $sig;

        $this->expectExceptionMessage('signature');
        SignedLink::verify($forged, self::SECRET);
    }

    public function test_wrong_secret_is_rejected(): void
    {
        $token = SignedLink::sign(['email' => 'a@b.com'], 3600, self::SECRET);
        $this->expectException(InvalidArgumentException::class);
        SignedLink::verify($token, 'different-secret');
    }
}
