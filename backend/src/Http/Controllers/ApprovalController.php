<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ApprovalService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalService $approvals)
    {
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $data = $this->approvals->list($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $data]);
    }

    public function store(Request $request, Response $response, array $args): Response
    {
        $approval = $this->approvals->create($this->principal($request), (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $approval->toArray()], 201);
    }
}
