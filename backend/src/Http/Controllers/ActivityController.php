<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ActivityService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ActivityController extends Controller
{
    public function __construct(private readonly ActivityService $activity)
    {
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $data = $this->activity->listForRequest($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $data]);
    }
}
