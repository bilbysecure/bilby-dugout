<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BrandService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class BrandController extends Controller
{
    public function __construct(private readonly BrandService $brand)
    {
    }

    public function indexKits(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->brand->listKits($this->principal($request))]);
    }

    public function showKit(Request $request, Response $response, array $args): Response
    {
        $kit = $this->brand->getKit($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $kit->toArray()]);
    }

    public function storeKit(Request $request, Response $response): Response
    {
        $kit = $this->brand->createKit($this->principal($request), $this->body($request));
        return $this->json($response, ['data' => $kit->toArray()], 201);
    }

    public function updateKit(Request $request, Response $response, array $args): Response
    {
        $kit = $this->brand->updateKit($this->principal($request), (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $kit->toArray()]);
    }

    public function indexAssets(Request $request, Response $response, array $args): Response
    {
        $data = $this->brand->listAssets($this->principal($request), (int) $args['id']);
        return $this->json($response, ['data' => $data]);
    }

    public function storeAsset(Request $request, Response $response, array $args): Response
    {
        $asset = $this->brand->createAsset($this->principal($request), (int) $args['id'], $this->body($request));
        return $this->json($response, ['data' => $asset->toArray()], 201);
    }

    public function destroyKit(Request $request, Response $response, array $args): Response
    {
        $this->brand->deleteKit($this->principal($request), (int) $args['id']);
        return $this->json($response, ['ok' => true]);
    }

    public function destroyAsset(Request $request, Response $response, array $args): Response
    {
        $this->brand->deleteAsset($this->principal($request), (int) $args['assetId']);
        return $this->json($response, ['ok' => true]);
    }

    public function reviewAsset(Request $request, Response $response, array $args): Response
    {
        $review = $this->brand->reviewAsset($this->principal($request), (int) $args['assetId']);
        return $this->json($response, ['data' => $review->toArray()]);
    }

    public function allowance(Request $request, Response $response): Response
    {
        $clientEmail = $request->getQueryParams()['client_email'] ?? null;
        return $this->json($response, ['data' => $this->brand->allowance($this->principal($request), $clientEmail)]);
    }
}
