<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Reports\ReportService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Client-facing reports (Master Spec §15). */
final class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->reports->list($this->principal($request))]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $report = $this->reports->get($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $report->toArray()]);
    }

    public function store(Request $request, Response $response): Response
    {
        $report = $this->reports->request($this->principal($request), $this->body($request));
        return $this->json($response, ['data' => $report->toArray()], 201);
    }
}
