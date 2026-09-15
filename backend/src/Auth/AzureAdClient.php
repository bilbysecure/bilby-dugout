<?php

declare(strict_types=1);

namespace App\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use RuntimeException;

/**
 * Talks to Azure AD. Implements the ROPC (password) grant used by the
 * Login form → Axios → Slim → SSO flow (architecture §5.1), and validates
 * the returned id_token against the tenant's published JWKS.
 */
final class AzureAdClient
{
    private Client $http;

    public function __construct(private readonly array $cfg)
    {
        $this->http = new Client(['timeout' => 15]);
    }

    private function base(): string
    {
        return "https://login.microsoftonline.com/{$this->cfg['tenant_id']}";
    }

    /**
     * Exchange username/password for tokens via the ROPC grant.
     *
     * @return array{id_token:string,access_token:string,expires_in:int}
     * @throws AuthException on invalid credentials / MFA-required / policy errors
     */
    public function passwordGrant(string $username, string $password): array
    {
        try {
            $res = $this->http->post($this->base() . '/oauth2/v2.0/token', [
                'form_params' => [
                    'grant_type'    => 'password',
                    'client_id'     => $this->cfg['client_id'],
                    'client_secret' => $this->cfg['client_secret'],
                    'scope'         => 'openid profile email',
                    'username'      => $username,
                    'password'      => $password,
                ],
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Azure returns 400 with an AADSTS error code (bad creds, MFA required, etc.)
            $body = json_decode((string) $e->getResponse()->getBody(), true) ?: [];
            throw new AuthException($body['error_description'] ?? 'Authentication failed', 401, $e);
        }

        $data = json_decode((string) $res->getBody(), true);
        if (empty($data['id_token'])) {
            throw new AuthException('Azure AD did not return an id_token', 502);
        }

        return [
            'id_token'     => $data['id_token'],
            'access_token' => $data['access_token'] ?? '',
            'expires_in'   => (int) ($data['expires_in'] ?? 3600),
        ];
    }

    /**
     * Validate an Azure id_token (signature via JWKS + iss/aud) and return its claims.
     *
     * @return array<string,mixed> e.g. oid, preferred_username/email, name
     */
    public function validateIdToken(string $idToken): array
    {
        $jwks = json_decode((string) $this->http->get(
            "https://login.microsoftonline.com/{$this->cfg['tenant_id']}/discovery/v2.0/keys"
        )->getBody(), true);

        if (empty($jwks['keys'])) {
            throw new RuntimeException('Unable to load Azure AD signing keys');
        }

        $keys = JWK::parseKeySet($jwks);
        $decoded = (array) JWT::decode($idToken, $keys);

        // Verify audience is our app
        if (($decoded['aud'] ?? null) !== $this->cfg['client_id']) {
            throw new AuthException('Token audience mismatch', 401);
        }

        return $decoded;
    }
}
