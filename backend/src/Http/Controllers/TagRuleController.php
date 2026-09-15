<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Publishing\TagRuleService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class TagRuleController extends Controller
{
    public function __construct(private readonly TagRuleService $rules)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->rules->list($this->principal($request))]);
    }

    public function store(Request $request, Response $response): Response
    {
        $r = $this->rules->create($this->principal($request), $this->body($request));
        return $this->json($response, ['data' => $r->toArray()], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $r = $this->rules->update($this->principal($request), (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $r->toArray()]);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $this->rules->delete($this->principal($request), (int) $args['id']);
        return $this->json($response, ['ok' => true]);
    }
}
