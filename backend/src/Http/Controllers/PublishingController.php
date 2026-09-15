<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Publishing\BestTimeService;
use App\Services\Publishing\PublishingService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PublishingController extends Controller
{
    public function __construct(
        private readonly PublishingService $posts,
        private readonly BestTimeService $bestTime,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $data = $this->posts->list($this->principal($request), $request->getQueryParams());
        return $this->json($response, ['data' => $data]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $post = $this->posts->get($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $post->toArray()]);
    }

    public function store(Request $request, Response $response): Response
    {
        $post = $this->posts->create($this->principal($request), $this->body($request));
        return $this->json($response, ['data' => $post->toArray()], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $post = $this->posts->update($this->principal($request), (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $post->toArray()]);
    }

    public function submit(Request $request, Response $response, array $args): Response
    {
        $post = $this->posts->submit($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $post->toArray()]);
    }

    public function decide(Request $request, Response $response, array $args): Response
    {
        $body = $this->body($request);
        $post = $this->posts->decide($this->principal($request), (int) $args['id'], (string) ($body['decision'] ?? ''), $body['note'] ?? null);
        return $this->json($response, ['data' => $post->toArray()]);
    }

    public function schedule(Request $request, Response $response, array $args): Response
    {
        $post = $this->posts->schedule($this->principal($request), (int) $args['id'], (string) ($this->body($request)['scheduled_at'] ?? ''));
        return $this->json($response, ['data' => $post->toArray()]);
    }

    public function publish(Request $request, Response $response, array $args): Response
    {
        $post = $this->posts->publishNow($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $post->toArray()]);
    }

    public function cancel(Request $request, Response $response, array $args): Response
    {
        $post = $this->posts->cancel($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $post->toArray()]);
    }

    public function bestTimes(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        $slots = $this->bestTime->suggest($q['platform'] ?? 'instagram', $q['timezone'] ?? 'UTC', (int) ($q['count'] ?? 5));
        return $this->json($response, ['data' => $slots]);
    }
}
