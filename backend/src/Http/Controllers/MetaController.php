<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Meta\MetaHealthService;
use App\Services\Meta\MetaOAuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Meta integration: health + OAuth connect scaffolding (Master Spec §16). */
final class MetaController extends Controller
{
    public function __construct(
        private readonly MetaHealthService $health,
        private readonly MetaOAuthService $oauth,
    ) {
    }

    /** Per-client integration health. Agency passes ?client_email=. */
    public function health(Request $request, Response $response): Response
    {
        $clientEmail = $request->getQueryParams()['client_email'] ?? null;
        return $this->json($response, ['data' => $this->health->health($this->principal($request), $clientEmail)]);
    }

    /** Begin the connect flow — returns the consent URL or a "not configured" notice. */
    public function connect(Request $request, Response $response): Response
    {
        $clientEmail = $this->body($request)['client_email'] ?? null;
        return $this->json($response, ['data' => $this->oauth->authorizeUrl($this->principal($request), $clientEmail)]);
    }

    /** OAuth redirect handler (SPA posts the code + state here). */
    public function callback(Request $request, Response $response): Response
    {
        $body = $this->body($request);
        $conn = $this->oauth->handleCallback(
            $this->principal($request),
            (string) ($body['code'] ?? ''),
            (string) ($body['state'] ?? ''),
        );
        return $this->json($response, ['data' => $conn->toArray()]);
    }
}
