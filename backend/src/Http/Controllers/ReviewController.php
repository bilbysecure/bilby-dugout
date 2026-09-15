<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ReviewService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews)
    {
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $data = $this->reviews->list($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $data]);
    }

    public function store(Request $request, Response $response, array $args): Response
    {
        $review = $this->reviews->create($this->principal($request), (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $review->toArray()], 201);
    }
}
