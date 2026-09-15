<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SubscriptionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->subscriptions->list($this->principal($request))]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $sub = $this->subscriptions->update($this->principal($request), (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $sub->toArray()]);
    }
}
