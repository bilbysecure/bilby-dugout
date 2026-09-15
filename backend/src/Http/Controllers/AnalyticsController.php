<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Publishing\ContentPerformanceService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Analytics (content performance from post_metrics). */
final class AnalyticsController extends Controller
{
    public function __construct(private readonly ContentPerformanceService $performance)
    {
    }

    public function contentPerformance(Request $request, Response $response): Response
    {
        $clientEmail = $request->getQueryParams()['client_email'] ?? null;
        return $this->json($response, ['data' => $this->performance->performance($this->principal($request), $clientEmail)]);
    }
}
