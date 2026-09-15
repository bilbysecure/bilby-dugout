<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\InvoiceService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->invoices->list($this->principal($request))]);
    }
}
