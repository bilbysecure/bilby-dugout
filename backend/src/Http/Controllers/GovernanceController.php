<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Governance\AccountDeletionService;
use App\Services\Governance\DataExportService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Data governance endpoints (Master Spec §17.4). */
final class GovernanceController extends Controller
{
    public function __construct(
        private readonly DataExportService $exports,
        private readonly AccountDeletionService $deletions,
    ) {
    }

    public function listExports(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->exports->list($this->principal($request))]);
    }

    public function requestExport(Request $request, Response $response): Response
    {
        $export = $this->exports->request($this->principal($request));
        return $this->json($response, ['data' => $export->toArray()], 201);
    }

    public function listDeletions(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->deletions->list($this->principal($request))]);
    }

    public function requestDeletion(Request $request, Response $response): Response
    {
        $clientEmail = (string) ($this->body($request)['client_email'] ?? '');
        $deletion = $this->deletions->requestDeletion($this->principal($request), $clientEmail);
        return $this->json($response, ['data' => $deletion->toArray()], 201);
    }

    public function cancelDeletion(Request $request, Response $response, array $args): Response
    {
        $deletion = $this->deletions->cancel($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $deletion->toArray()]);
    }
}
