<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\JwtService;
use App\Auth\Principal;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Verifies the app access JWT, builds the Principal, and attaches it to the
 * request as the `principal` attribute. Also honors an admin "impersonate"
 * header to set the client scope (validated downstream in policies/repos).
 */
final class JwtAuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly JwtService $jwt)
    {
    }

    public function process(Request $request, Handler $handler): Response
    {
        $header = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return $this->unauthorized('Missing bearer token');
        }

        try {
            $claims = $this->jwt->verify($m[1]);
        } catch (\Throwable $e) {
            return $this->unauthorized('Invalid or expired token');
        }

        if (($claims['typ'] ?? null) !== 'access') {
            return $this->unauthorized('Wrong token type');
        }

        // Admin impersonation ("view as client") — only meaningful for agency principals;
        // the repositories/policies enforce that only managers may use it.
        $impersonate = $request->getHeaderLine('X-Impersonate-Client') ?: null;
        if ($impersonate) {
            $claims['impersonated_client_email'] = $impersonate;
        }

        $principal = Principal::fromClaims($claims);

        return $handler->handle($request->withAttribute('principal', $principal));
    }

    private function unauthorized(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode(['error' => ['code' => 'unauthorized', 'message' => $message]]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }
}
