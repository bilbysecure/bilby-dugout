<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Admin\FormConfigService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class FormConfigController extends Controller
{
    public function __construct(private readonly FormConfigService $config)
    {
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        return $this->json($response, ['data' => $this->config->get($this->principal($request), $args['key'])]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $items = (array) ($this->body($request)['items'] ?? []);
        return $this->json($response, ['data' => $this->config->set($this->principal($request), $args['key'], $items)]);
    }

    public function reset(Request $request, Response $response, array $args): Response
    {
        return $this->json($response, ['data' => $this->config->reset($this->principal($request), $args['key'])]);
    }
}
