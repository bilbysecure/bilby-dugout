<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Publishing\RssService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class RssController extends Controller
{
    public function __construct(private readonly RssService $rss)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->rss->list($this->principal($request))]);
    }

    public function store(Request $request, Response $response): Response
    {
        $s = $this->rss->create($this->principal($request), $this->body($request));
        return $this->json($response, ['data' => $s->toArray()], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $s = $this->rss->update($this->principal($request), (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $s->toArray()]);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $this->rss->delete($this->principal($request), (int) $args['id']);
        return $this->json($response, ['ok' => true]);
    }

    public function ingest(Request $request, Response $response, array $args): Response
    {
        $result = $this->rss->ingest($this->principal($request), (int) $args['id']);
        return $this->json($response, $result);
    }
}
