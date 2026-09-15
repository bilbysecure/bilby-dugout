<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\AuthException;
use App\Auth\AuthService;
use App\Auth\Principal;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Login form → Axios → Slim → Azure AD (ROPC) → app JWT (architecture §5.1).
 * The refresh token is delivered as an httpOnly cookie (decision #5).
 */
final class AuthController extends Controller
{
    private const REFRESH_COOKIE = 'bd_refresh';
    private const REFRESH_PATH   = '/api/v1/auth';

    public function __construct(
        private readonly AuthService $auth,
        private readonly array $settings,
    ) {
    }

    public function login(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($username === '' || $password === '') {
            throw new AuthException('Username and password are required', 422);
        }

        $result = $this->auth->login($username, $password);

        $response = $this->withRefreshCookie($response, $result['refresh_token'], $this->settings['jwt']['refresh_ttl']);

        return $this->json($response, [
            'access_token' => $result['access_token'],
            'token_type'   => 'Bearer',
            'expires_in'   => $this->settings['jwt']['access_ttl'],
            'user'         => $result['user'],
        ]);
    }

    /** Local password login for client users (staff use Azure AD via login()). */
    public function clientLogin(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        if ($email === '' || $password === '') {
            throw new AuthException('Email and password are required', 422);
        }

        $result = $this->auth->clientLogin($email, $password);
        $response = $this->withRefreshCookie($response, $result['refresh_token'], $this->settings['jwt']['refresh_ttl']);
        return $this->json($response, [
            'access_token' => $result['access_token'],
            'token_type'   => 'Bearer',
            'expires_in'   => $this->settings['jwt']['access_ttl'],
            'user'         => $result['user'],
        ]);
    }

    /** DEV ONLY (APP_ENV=local) — sign in as any seeded email, bypassing Azure AD. */
    public function devLogin(Request $request, Response $response): Response
    {
        if (($this->settings['app']['env'] ?? '') !== 'local') {
            throw new AuthException('Not found', 404);
        }
        $email = trim((string) ($this->body($request)['email'] ?? ''));
        if ($email === '') {
            throw new AuthException('email is required', 422);
        }
        $result = $this->auth->devLogin($email);
        $response = $this->withRefreshCookie($response, $result['refresh_token'], $this->settings['jwt']['refresh_ttl']);
        return $this->json($response, [
            'access_token' => $result['access_token'],
            'token_type'   => 'Bearer',
            'expires_in'   => $this->settings['jwt']['access_ttl'],
            'user'         => $result['user'],
        ]);
    }

    public function refresh(Request $request, Response $response): Response
    {
        $refresh = $this->readRefreshCookie($request);
        if (!$refresh) {
            throw new AuthException('No refresh token', 401);
        }

        $result = $this->auth->refresh($refresh);

        return $this->json($response, [
            'access_token' => $result['access_token'],
            'token_type'   => 'Bearer',
            'expires_in'   => $this->settings['jwt']['access_ttl'],
        ]);
    }

    public function logout(Request $request, Response $response): Response
    {
        $refresh = $this->readRefreshCookie($request);
        if ($refresh) {
            $this->auth->logout($refresh);
        }
        $response = $this->withRefreshCookie($response, '', 0); // expire
        return $this->json($response, ['ok' => true]);
    }

    public function me(Request $request, Response $response): Response
    {
        /** @var Principal $p */
        $p = $request->getAttribute('principal');
        return $this->json($response, [
            'id' => $p->userId, 'email' => $p->email, 'name' => $p->name,
            'role' => $p->role->value, 'designation' => $p->designation,
            'client_email' => $p->clientEmail, 'permissions' => $p->permissions,
        ]);
    }

    private function withRefreshCookie(Response $response, string $value, int $maxAge): Response
    {
        $secure = ($this->settings['app']['env'] ?? '') === 'production' ? '; Secure' : '';
        $cookie = sprintf(
            '%s=%s; Max-Age=%d; Path=%s; HttpOnly; SameSite=Strict%s',
            self::REFRESH_COOKIE,
            rawurlencode($value),
            $maxAge,
            self::REFRESH_PATH,
            $secure
        );
        return $response->withAddedHeader('Set-Cookie', $cookie);
    }

    private function readRefreshCookie(Request $request): ?string
    {
        $cookies = $request->getCookieParams();
        $value = $cookies[self::REFRESH_COOKIE] ?? null;
        return $value ? rawurldecode($value) : null;
    }
}
