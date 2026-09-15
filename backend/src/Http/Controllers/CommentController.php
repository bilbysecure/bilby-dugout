<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CommentService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class CommentController extends Controller
{
    public function __construct(private readonly CommentService $comments)
    {
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $data = $this->comments->list($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $data]);
    }

    public function store(Request $request, Response $response, array $args): Response
    {
        $comment = $this->comments->create($this->principal($request), (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $comment->toArray()], 201);
    }
}
