<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Principal;
use App\Services\RequestService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Requests API. All access is scoped/authorized in the service + repository layers. */
final class RequestController extends Controller
{
    public function __construct(private readonly RequestService $requests)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $p = $this->principal($request);
        $limit = (int) ($request->getQueryParams()['limit'] ?? 200);
        return $this->json($response, ['data' => $this->requests->list($p, min($limit, 500))]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $p = $this->principal($request);
        $model = $this->requests->get($p, (int) $args['id']);
        return $this->json($response, ['data' => $model->toArray()]);
    }

    public function store(Request $request, Response $response): Response
    {
        $p = $this->principal($request);
        $payload = (array) $request->getParsedBody();
        $created = $this->requests->create($p, $payload);
        return $this->json($response, ['data' => $created->toArray()], 201);
    }
}
