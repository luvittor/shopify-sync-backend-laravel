<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository
{
    /**
     * Upsert a product by shopify_id
     *
     * @param array $productData
     * @return void
     */
    public function upsert(array $productData): void
    {
        Product::updateOrCreate(
            ['shopify_id' => $productData['shopify_id']],
            [
                'title' => $productData['title'],
                'price' => $productData['price'],
                'stock' => $productData['stock'],
            ]
        );
    }

    /**
     * List paginated products ordered by id desc with minimal fields with pagination
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function listPaginated(int $perPage = 10): LengthAwarePaginator
    {
        return Product::orderByDesc('id')
            ->select('id', 'shopify_id', 'title', 'price', 'stock', 'created_at', 'updated_at')
            ->paginate($perPage);
    }

    /**
     * Clear all products
     *
     * @return int Number of cleared products
     */
    public function clear(): int
    {
        return Product::query()->delete();
    }
}