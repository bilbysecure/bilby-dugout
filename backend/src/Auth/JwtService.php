<?php

declare(strict_types=1);

namespace App\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Ramsey\Uuid\Uuid;

/**
 * Issues and verifies the application's own JWTs (HS256).
 * The Azure AD token is validated separately (AzureAdClient) — these are OUR tokens.
 */
final class JwtService
{
    public function __construct(private readonly array $cfg)
    {
    }

    /** @param array<string,mixed> $claims */
    public function issueAccessToken(array $claims): string
    {
        $now = time();
        $payload = array_merge($claims, [
            'iss'  => $this->cfg['issuer'],
            'iat'  => $now,
            'nbf'  => $now,
            'exp'  => $now + $this->cfg['access_ttl'],
            'typ'  => 'access',
            'jti'  => Uuid::uuid4()->toString(),
        ]);

        return JWT::encode($payload, $this->cfg['secret'], $this->cfg['algo']);
    }

    /** Returns [token, jti, expiresAt] for the refresh token. */
    public function issueRefreshToken(int $userId): array
    {
        $now = time();
        $jti = Uuid::uuid4()->toString();
        $exp = $now + $this->cfg['refresh_ttl'];

        $token = JWT::encode([
            'iss' => $this->cfg['issuer'],
            'sub' => (string) $userId,
            'iat' => $now,
            'exp' => $exp,
            'typ' => 'refresh',
            'jti' => $jti,
        ], $this->cfg['secret'], $this->cfg['algo']);

        return [$token, $jti, $exp];
    }

    /** @return array<string,mixed> decoded claims */
    public function verify(string $token): array
    {
        $decoded = JWT::decode($token, new Key($this->cfg['secret'], $this->cfg['algo']));
        return (array) $decoded;
    }
}
