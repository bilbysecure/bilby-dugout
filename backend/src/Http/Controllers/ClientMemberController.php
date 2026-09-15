<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Admin\ClientMemberService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ClientMemberController extends Controller
{
    public function __construct(private readonly ClientMemberService $members)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $email = (string) ($request->getQueryParams()['client_email'] ?? '');
        return $this->json($response, ['data' => $this->members->list($this->principal($request), $email)]);
    }

    public function store(Request $request, Response $response): Response
    {
        $m = $this->members->create($this->principal($request), $this->body($request));
        return $this->json($response, ['data' => $m->toArray()], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $m = $this->members->update($this->principal($request), (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $m->toArray()]);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $this->members->delete($this->principal($request), (int) $args['id']);
        return $this->json($response, ['ok' => true]);
    }
}
