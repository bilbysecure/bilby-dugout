<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Onboarding\OnboardingService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Invite-only onboarding endpoints (Master Spec §17.3). */
final class OnboardingController extends Controller
{
    private const REFRESH_COOKIE = 'bd_refresh';
    private const REFRESH_PATH   = '/api/v1/auth';

    public function __construct(
        private readonly OnboardingService $onboarding,
        private readonly array $settings,
    ) {
    }

    /** Manager invites a client + owner. */
    public function invite(Request $request, Response $response): Response
    {
        $result = $this->onboarding->invite($this->principal($request), $this->body($request));
        return $this->json($response, ['data' => $result], 201);
    }

    /** PUBLIC — owner sets a password via the signed link and gets a session. */
    public function accept(Request $request, Response $response): Response
    {
        $body = $this->body($request);
        $session = $this->onboarding->acceptInvite(
            (string) ($body['token'] ?? ''),
            (string) ($body['password'] ?? ''),
            $this->clientIp($request),
        );

        $response = $this->withRefreshCookie($response, $session['refresh_token'], $this->settings['jwt']['refresh_ttl']);
        return $this->json($response, [
            'access_token' => $session['access_token'],
            'token_type'   => 'Bearer',
            'expires_in'   => $this->settings['jwt']['access_ttl'],
            'user'         => $session['user'],
        ]);
    }

    public function preferences(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->onboarding->getPreferences($this->principal($request))]);
    }

    public function savePreferences(Request $request, Response $response): Response
    {
        $prefs = $this->onboarding->savePreferences($this->principal($request), $this->body($request));
        return $this->json($response, ['data' => $prefs->toArray()]);
    }

    public function complete(Request $request, Response $response): Response
    {
        $this->onboarding->complete($this->principal($request));
        return $this->json($response, ['ok' => true]);
    }

    private function clientIp(Request $request): ?string
    {
        $server = $request->getServerParams();
        return $server['REMOTE_ADDR'] ?? null;
    }

    private function withRefreshCookie(Response $response, string $token, int $ttl): Response
    {
        $secure = str_starts_with((string) ($this->settings['app']['url'] ?? ''), 'https');
        $cookie = self::REFRESH_COOKIE . '=' . $token
            . '; Path=' . self::REFRESH_PATH
            . '; Max-Age=' . $ttl
            . '; HttpOnly; SameSite=Strict' . ($secure ? '; Secure' : '');
        return $response->withHeader('Set-Cookie', $cookie);
    }
}
