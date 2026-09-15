<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\TeamService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class TeamController extends Controller
{
    public function __construct(private readonly TeamService $team)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->team->list($this->principal($request))]);
    }

    public function store(Request $request, Response $response): Response
    {
        $m = $this->team->create($this->principal($request), $this->body($request));
        return $this->json($response, ['data' => $m->toArray()], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $m = $this->team->update($this->principal($request), (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $m->toArray()]);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $this->team->delete($this->principal($request), (int) $args['id']);
        return $this->json($response, ['ok' => true]);
    }
}
