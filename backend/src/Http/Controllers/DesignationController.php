<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Admin\DesignationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class DesignationController extends Controller
{
    public function __construct(private readonly DesignationService $designations)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->designations->list($this->principal($request))]);
    }

    public function store(Request $request, Response $response): Response
    {
        $d = $this->designations->create($this->principal($request), $this->body($request));
        return $this->json($response, ['data' => $d->toArray()], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $d = $this->designations->update($this->principal($request), (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $d->toArray()]);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $this->designations->delete($this->principal($request), (int) $args['id']);
        return $this->json($response, ['ok' => true]);
    }
}
