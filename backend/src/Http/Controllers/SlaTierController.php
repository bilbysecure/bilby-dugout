<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Admin\SlaTierService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SlaTierController extends Controller
{
    public function __construct(private readonly SlaTierService $tiers)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->tiers->list($this->principal($request))]);
    }

    public function store(Request $request, Response $response): Response
    {
        $tier = $this->tiers->create($this->principal($request), $this->body($request));
        return $this->json($response, ['data' => $tier->toArray()], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $tier = $this->tiers->update($this->principal($request), (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $tier->toArray()]);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $this->tiers->delete($this->principal($request), (int) $args['id']);
        return $this->json($response, ['ok' => true]);
    }
}
