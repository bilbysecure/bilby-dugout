<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\RevisionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class RevisionController extends Controller
{
    public function __construct(private readonly RevisionService $revisions)
    {
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $data = $this->revisions->list($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $data]);
    }

    public function store(Request $request, Response $response, array $args): Response
    {
        $revision = $this->revisions->create($this->principal($request), (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $revision->toArray()], 201);
    }

    public function updateStatus(Request $request, Response $response, array $args): Response
    {
        $revision = $this->revisions->updateStatus($this->principal($request), (int) $args['revisionId'], $this->body($request));
        return $this->json($response, ['data' => $revision->toArray()]);
    }
}
