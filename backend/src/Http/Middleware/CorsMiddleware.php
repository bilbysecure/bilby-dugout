<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

/** Locks CORS to the configured SPA origin(s). */
final class CorsMiddleware implements MiddlewareInterface
{
    /** @param string[] $allowedOrigins */
    public function __construct(private readonly array $allowedOrigins)
    {
    }

    public function process(Request $request, Handler $handler): Response
    {
        $origin = $request->getHeaderLine('Origin');
        $allowed = in_array($origin, $this->allowedOrigins, true) ? $origin : '';

        // Short-circuit preflight
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            $response = new SlimResponse();
            return $this->withCors($response, $allowed)->withStatus(204);
        }

        return $this->withCors($handler->handle($request), $allowed);
    }

    private function withCors(Response $response, string $allowed): Response
    {
        if ($allowed === '') {
            return $response;
        }
        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowed)
            ->withHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Vary', 'Origin');
    }
}
