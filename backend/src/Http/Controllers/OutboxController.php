<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Enums\Role;
use App\Domain\Models\EmailOutbox;
use App\Policies\AuthorizationException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Read the simulated email/Teams outbox (global admin). */
final class OutboxController extends Controller
{
    public function index(Request $request, Response $response): Response
    {
        if ($this->principal($request)->role !== Role::GlobalAdmin) {
            throw new AuthorizationException('Global admin access required');
        }
        $rows = EmailOutbox::orderByDesc('id')->limit(200)->get()->toArray();
        return $this->json($response, ['data' => $rows]);
    }
}
