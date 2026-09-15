<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\TimeService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class TimeController extends Controller
{
    public function __construct(private readonly TimeService $time)
    {
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $data = $this->time->list($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $data]);
    }

    public function store(Request $request, Response $response, array $args): Response
    {
        $entry = $this->time->create($this->principal($request), (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $entry->toArray()], 201);
    }
}
