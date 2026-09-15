<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\TaskApprovalService;
use App\Services\TaskService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Tasks + the dual-approval flow. */
final class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly TaskApprovalService $approvals,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->tasks->list($this->principal($request))]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $task = $this->tasks->get($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $task->load(['approvers', 'approvals'])->toArray()]);
    }

    public function store(Request $request, Response $response): Response
    {
        $task = $this->tasks->create($this->principal($request), $this->body($request));
        return $this->json($response, ['data' => $task->toArray()], 201);
    }

    public function submit(Request $request, Response $response, array $args): Response
    {
        $task = $this->tasks->submit($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $task->toArray()]);
    }

    public function internalDecision(Request $request, Response $response, array $args): Response
    {
        $task = $this->approvals->decideInternal($this->principal($request), (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $task->toArray()]);
    }

    public function clientDecision(Request $request, Response $response, array $args): Response
    {
        $task = $this->approvals->decideClient($this->principal($request), (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $task->toArray()]);
    }

    public function start(Request $request, Response $response, array $args): Response
    {
        $task = $this->tasks->start($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $task->toArray()]);
    }

    public function complete(Request $request, Response $response, array $args): Response
    {
        $task = $this->tasks->complete($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $task->toArray()]);
    }

    public function cancel(Request $request, Response $response, array $args): Response
    {
        $task = $this->tasks->cancel($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $task->toArray()]);
    }

    public function approvals(Request $request, Response $response, array $args): Response
    {
        return $this->json($response, ['data' => $this->approvals->history($this->principal($request), (int) $args['id'])]);
    }
}
