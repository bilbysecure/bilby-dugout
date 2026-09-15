<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $data = $this->notifications->listFor($this->principal($request));
        return $this->json($response, ['data' => $data]);
    }

    public function markRead(Request $request, Response $response, array $args): Response
    {
        $n = $this->notifications->markRead($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $n->toArray()]);
    }

    public function markAllRead(Request $request, Response $response): Response
    {
        $count = $this->notifications->markAllRead($this->principal($request));
        return $this->json($response, ['updated' => $count]);
    }
}
