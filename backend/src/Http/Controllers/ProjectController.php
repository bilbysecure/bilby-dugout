<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ProjectService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** One-off projects. Managers create/manage; clients view their own. */
final class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $projects)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->projects->list($this->principal($request))]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $project = $this->projects->get($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $project->load('quotes')->toArray()]);
    }

    public function store(Request $request, Response $response): Response
    {
        $project = $this->projects->create($this->principal($request), $this->body($request));
        return $this->json($response, ['data' => $project->toArray()], 201);
    }

    public function complete(Request $request, Response $response, array $args): Response
    {
        $project = $this->projects->complete($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $project->toArray()]);
    }
}
