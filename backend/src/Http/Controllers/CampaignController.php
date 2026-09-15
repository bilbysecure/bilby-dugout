<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Campaigns\CampaignService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Ad Campaign module (Master Spec §14). Managers configure; clients view own. */
final class CampaignController extends Controller
{
    public function __construct(private readonly CampaignService $campaigns)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->campaigns->list($this->principal($request))]);
    }

    public function store(Request $request, Response $response): Response
    {
        $campaign = $this->campaigns->create($this->principal($request), $this->body($request));
        return $this->json($response, ['data' => $campaign->toArray()], 201);
    }

    /** Client-facing campaign dashboard payload. */
    public function dashboard(Request $request, Response $response, array $args): Response
    {
        return $this->json($response, ['data' => $this->campaigns->dashboard($this->principal($request), (int) $args['id'])]);
    }
}
