<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ProjectQuoteService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Project quotes: manager drafts/sends; client owner accepts + pays deposit. */
final class QuoteController extends Controller
{
    public function __construct(private readonly ProjectQuoteService $quotes)
    {
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $data = $this->quotes->listForProject($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $data]);
    }

    public function store(Request $request, Response $response, array $args): Response
    {
        $quote = $this->quotes->create($this->principal($request), (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $quote->toArray()], 201);
    }

    public function send(Request $request, Response $response, array $args): Response
    {
        $quote = $this->quotes->send($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $quote->toArray()]);
    }

    public function accept(Request $request, Response $response, array $args): Response
    {
        $quote = $this->quotes->accept($this->principal($request), (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $quote->toArray()]);
    }

    public function decline(Request $request, Response $response, array $args): Response
    {
        $quote = $this->quotes->decline($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $quote->toArray()]);
    }

    public function depositCheckout(Request $request, Response $response, array $args): Response
    {
        $result = $this->quotes->depositCheckout($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $result]);
    }
}
