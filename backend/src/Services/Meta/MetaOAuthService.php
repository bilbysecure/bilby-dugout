<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Auth\Principal;
use App\Domain\Models\MetaConnection;
use App\Repositories\MetaConnectionRepository;
use App\Services\Meta\Exceptions\MetaException;
use App\Support\SignedLink;
use GuzzleHttp\Client as HttpClient;

/**
 * OAuth connect scaffolding for linking a client's Meta assets (Master Spec §16).
 *
 * ⚠️ OPERATOR ACTIONS (surfaced, NOT performed here):
 *   1. Set META_APP_ID / META_APP_SECRET (the connect flow reports "not
 *      configured" until then).
 *   2. Register the redirect URI + complete Meta App Review for `ads_read`.
 *   3. Walk a client through the consent dialog (authorizeUrl) once — the token
 *      exchange in handleCallback then runs server-side with the app secret.
 *
 * Scope is `ads_read` (report-only). `ads_management` + audience creation are
 * FUTURE upgrades — do not add scopes here without a new review.
 */
final class MetaOAuthService
{
    private const STATE_PURPOSE = 'meta_oauth';

    public function __construct(
        private readonly HttpClient $http,
        private readonly MetaTokenStore $tokens,
        private readonly MetaClient $client,
        private readonly MetaConnectionRepository $repo,
        private readonly array $config,
        private readonly string $stateSecret,
    ) {
    }

    /** Build the Meta consent dialog URL, or report that credentials are missing. */
    public function authorizeUrl(Principal $p, ?string $clientEmail = null): array
    {
        if (!$this->isConfigured()) {
            return [
                'configured' => false,
                'message'    => 'Meta app credentials are not set. An operator must configure META_APP_ID and META_APP_SECRET and complete Meta App Review for ads_read.',
            ];
        }

        $client = $this->repo->resolveClientEmail($p, $clientEmail);
        $state = SignedLink::sign(['client_email' => $client, 'purpose' => self::STATE_PURPOSE], 600, $this->stateSecret);
        $scopes = implode(',', $this->config['scopes'] ?? ['ads_read']);

        $url = 'https://www.facebook.com/' . ($this->config['api_version'] ?? 'v21.0') . '/dialog/oauth?'
            . http_build_query([
                'client_id'     => $this->config['app_id'],
                'redirect_uri'  => $this->config['redirect_uri'],
                'scope'         => $scopes,
                'response_type' => 'code',
                'state'         => $state,
            ]);

        return ['configured' => true, 'url' => $url, 'scopes' => $this->config['scopes'] ?? ['ads_read']];
    }

    /**
     * Handle the OAuth redirect: exchange code → token, discover assets, store the
     * connection. Runs only once credentials are configured (operator step).
     */
    public function handleCallback(Principal $p, string $code, string $state): MetaConnection
    {
        if (!$this->isConfigured()) {
            throw new MetaException('Meta app credentials are not configured');
        }
        $payload = SignedLink::verify($state, $this->stateSecret); // throws on tamper/expiry
        if (($payload['purpose'] ?? null) !== self::STATE_PURPOSE) {
            throw new MetaException('Invalid OAuth state');
        }
        $client = (string) $payload['client_email'];

        // Exchange the authorization code for a token (server-side, with app secret).
        $token = $this->exchangeCodeForToken($code);

        $conn = MetaConnection::firstOrNew(['client_email' => $client]);
        $conn->scopes_granted = $this->config['scopes'] ?? ['ads_read'];
        $conn->status = 'connected';
        $conn->save();
        $this->tokens->store($conn, $token['access_token'], $token['expires_at'] ?? null);

        // Discover linked ad accounts (report-only).
        try {
            $accounts = $this->client->get($conn, '/me/adaccounts', ['fields' => 'account_id,name']);
            $conn->ad_account_ids = array_map(fn ($a) => $a['account_id'] ?? $a['id'] ?? null, $accounts['data'] ?? []);
            $conn->save();
        } catch (\Throwable) {
            // Non-fatal: the connection is stored; discovery can be retried.
        }

        return $conn;
    }

    private function exchangeCodeForToken(string $code): array
    {
        $res = $this->http->request('GET', ($this->config['api_version'] ?? 'v21.0') . '/oauth/access_token', [
            'query' => [
                'client_id'     => $this->config['app_id'],
                'client_secret' => $this->config['app_secret'],
                'redirect_uri'  => $this->config['redirect_uri'],
                'code'          => $code,
            ],
            'http_errors' => false,
        ]);
        $body = json_decode((string) $res->getBody(), true);
        if (!is_array($body) || empty($body['access_token'])) {
            throw new MetaException('Token exchange failed: ' . (($body['error']['message'] ?? null) ?: 'unknown error'));
        }
        $expiresAt = isset($body['expires_in']) ? date('Y-m-d H:i:s', time() + (int) $body['expires_in']) : null;
        return ['access_token' => (string) $body['access_token'], 'expires_at' => $expiresAt];
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['app_id']) && !empty($this->config['app_secret']);
    }
}
