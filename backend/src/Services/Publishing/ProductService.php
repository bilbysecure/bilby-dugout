<?php

declare(strict_types=1);

namespace App\Services\Publishing;

use App\Auth\Principal;
use App\Domain\Models\Product;
use App\Support\TenantScope;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/** Product catalog used for Instagram product tagging. */
final class ProductService
{
    private const FIELDS = ['brand_kit_id', 'name', 'description', 'price', 'currency', 'image_url', 'external_product_id', 'retailer_id', 'status'];

    public function list(Principal $p): array
    {
        return TenantScope::apply(Product::query(), $p)->orderBy('name')->get()->toArray();
    }

    public function create(Principal $p, array $data): Product
    {
        $payload = array_intersect_key($data, array_flip(self::FIELDS));
        if (empty($payload['name'])) {
            throw new InvalidArgumentException('name is required');
        }
        $payload['client_email'] = TenantScope::tenantFor($p, $data['client_email'] ?? null);
        return Product::create($payload);
    }

    public function update(Principal $p, int $id, array $data): Product
    {
        $product = TenantScope::apply(Product::query(), $p)->whereKey($id)->first();
        if (!$product) {
            throw new ModelNotFoundException('Product not found');
        }
        $product->fill(array_intersect_key($data, array_flip(self::FIELDS)))->save();
        return $product;
    }
}
