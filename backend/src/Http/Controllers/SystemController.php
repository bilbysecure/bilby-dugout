<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Jobs\SystemService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** System / Jobs admin surface (queue depth, failed jobs, replay). */
final class SystemController extends Controller
{
    public function __construct(private readonly SystemService $system)
    {
    }

    public function jobs(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->system->overview($this->principal($request))]);
    }

    public function failedJobs(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->system->failedJobs($this->principal($request))]);
    }

    public function replay(Request $request, Response $response, array $args): Response
    {
        $job = $this->system->replay($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $job->toArray()], 201);
    }
}
