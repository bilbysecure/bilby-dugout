<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Publishing\ChannelService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ChannelController extends Controller
{
    public function __construct(private readonly ChannelService $channels)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->channels->list($this->principal($request))]);
    }

    public function store(Request $request, Response $response): Response
    {
        $c = $this->channels->create($this->principal($request), $this->body($request));
        return $this->json($response, ['data' => $c->toArray()], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $c = $this->channels->update($this->principal($request), (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $c->toArray()]);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $this->channels->delete($this->principal($request), (int) $args['id']);
        return $this->json($response, ['ok' => true]);
    }
}
