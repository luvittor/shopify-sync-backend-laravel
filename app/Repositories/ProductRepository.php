<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Support\Collection;

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
     * List all products ordered by id desc with minimal fields
     *
     * @return Collection
     */
    public function list(): Collection
    {
        return Product::orderByDesc('id')
            ->select('id', 'shopify_id', 'title', 'price', 'stock', 'created_at', 'updated_at')
            ->get();
    }
}