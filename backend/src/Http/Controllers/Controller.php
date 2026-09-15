<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Principal;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class Controller
{
    protected function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    /** The authenticated caller, attached by JwtAuthMiddleware. */
    protected function principal(Request $request): Principal
    {
        /** @var Principal $p */
        $p = $request->getAttribute('principal');
        return $p;
    }

    /** @return array<string,mixed> */
    protected function body(Request $request): array
    {
        return (array) $request->getParsedBody();
    }
}
