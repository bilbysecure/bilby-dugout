<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Models\ClientRole;
use App\Services\Admin\RoleService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ClientRoleController extends Controller
{
    private const MODEL = ClientRole::class;

    public function __construct(private readonly RoleService $roles)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->roles->list($this->principal($request), self::MODEL)]);
    }

    public function store(Request $request, Response $response): Response
    {
        $r = $this->roles->create($this->principal($request), self::MODEL, $this->body($request));
        return $this->json($response, ['data' => $r->toArray()], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $r = $this->roles->update($this->principal($request), self::MODEL, (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $r->toArray()]);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $this->roles->delete($this->principal($request), self::MODEL, (int) $args['id']);
        return $this->json($response, ['ok' => true]);
    }
}
