<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Admin\PlanService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PlanController extends Controller
{
    public function __construct(private readonly PlanService $plans)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->plans->list($this->principal($request))]);
    }

    public function store(Request $request, Response $response): Response
    {
        $plan = $this->plans->create($this->principal($request), $this->body($request));
        return $this->json($response, ['data' => $plan->toArray()], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $plan = $this->plans->update($this->principal($request), (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $plan->toArray()]);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $this->plans->delete($this->principal($request), (int) $args['id']);
        return $this->json($response, ['ok' => true]);
    }
}
