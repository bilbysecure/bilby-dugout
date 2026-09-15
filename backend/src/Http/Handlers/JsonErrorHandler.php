<?php

declare(strict_types=1);

namespace App\Http\Handlers;

use App\Auth\AuthException;
use App\Policies\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Respect\Validation\Exceptions\ValidationException;
use Slim\Exception\HttpNotFoundException;
use Slim\Handlers\ErrorHandler;
use Psr\Http\Message\ResponseInterface;

/**
 * Renders all uncaught exceptions as a consistent JSON envelope:
 *   { "error": { "code": "...", "message": "..." } }
 */
final class JsonErrorHandler extends ErrorHandler
{
    protected function respond(): ResponseInterface
    {
        $exception = $this->exception;
        [$status, $code] = $this->classify($exception);

        $message = ($status >= 500 && !$this->displayErrorDetails)
            ? 'Internal server error'
            : $exception->getMessage();

        $payload = ['error' => ['code' => $code, 'message' => $message]];

        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /** @return array{0:int,1:string} */
    private function classify(\Throwable $e): array
    {
        return match (true) {
            $e instanceof AuthException          => [$e->statusCode(), 'unauthorized'],
            $e instanceof AuthorizationException => [403, 'forbidden'],
            $e instanceof ModelNotFoundException,
            $e instanceof HttpNotFoundException  => [404, 'not_found'],
            $e instanceof ValidationException,
            $e instanceof \InvalidArgumentException => [422, 'validation_failed'],
            default                              => [500, 'server_error'],
        };
    }
}
