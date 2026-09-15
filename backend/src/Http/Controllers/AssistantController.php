<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Models\Subscription;
use App\Services\Ai\LlmService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** AI assistance (request drafting, plan recommendations). */
final class AssistantController extends Controller
{
    public function __construct(private readonly LlmService $llm)
    {
    }

    public function draftRequest(Request $request, Response $response): Response
    {
        $draft = $this->llm->draftRequest($this->body($request));
        return $this->json($response, ['data' => $draft]);
    }

    public function recommendations(Request $request, Response $response): Response
    {
        $p = $this->principal($request);
        $email = $p->clientEmail ?? $p->impersonatedClientEmail;
        $sub = $email
            ? Subscription::where('client_email', $email)->orderByDesc('created_at')->first()
            : Subscription::orderByDesc('created_at')->first();
        $recs = $this->llm->recommendations($sub?->toArray() ?? []);
        return $this->json($response, ['data' => $recs]);
    }
}
