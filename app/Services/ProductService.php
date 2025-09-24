<?php

namespace App\Services;

use App\Repositories\ProductRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class ProductService
{
    public function __construct(
        private ProductRepository $productRepository,
        private ShopifyService $shopifyService,
    ) {
    }

    public function listPaginated(int $perPage = 10): LengthAwarePaginator
    {
        return $this->productRepository->listPaginated($perPage);
    }

    public function clear(): int
    {
        return $this->productRepository->clear();
    }

    public function syncFromShopify(): array
    {
        try {
            $products = $this->shopifyService->fetchProducts();
            $syncedCount = 0;
            $skippedCount = 0;

            foreach ($products as $productData) {
                try {
                    if ($this->processProduct($productData)) {
                        $syncedCount++;
                    } else {
                        $skippedCount++;
                    }
                } catch (\Exception $e) {
                    $skippedCount++;
                    Log::error('Failed to process product', [
                        'product' => $productData,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Product sync completed', [
                'synced_count' => $syncedCount,
                'skipped_count' => $skippedCount,
                'total_processed' => count($products),
            ]);

            return [
                'synced' => $syncedCount,
                'skipped' => $skippedCount,
                'total' => count($products),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to sync products', [
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to sync products: ' . $e->getMessage());
        }
    }

    private function processProduct(array $productData): bool
    {
        $shopifyId = $productData['id'] ?? null;

        if (!$shopifyId) {
            Log::warning('Product data missing ID, skipping', ['product' => $productData]);

            return false;
        }

        $title = $productData['title'] ?? 'Untitled Product';
        $variant = $this->extractVariantData($productData);

        $this->productRepository->upsert([
            'shopify_id' => (string) $shopifyId,
            'title' => $title,
            'price' => $variant['price'],
            'stock' => $variant['stock'],
        ]);

        Log::debug('Product upserted', [
            'shopify_id' => $shopifyId,
            'title' => $title,
            'price' => $variant['price'],
            'stock' => $variant['stock'],
        ]);

        return true;
    }

    /**
     * @return array{price: float, stock: int}
     */
    private function extractVariantData(array $product): array
    {
        $price = 0.0;
        $stock = 0;

        if (!empty($product['variants'])) {
            $firstVariant = $product['variants'][0];
            $price = (float) ($firstVariant['price'] ?? 0);
            $stock = (int) ($firstVariant['inventory_quantity'] ?? 0);
        }

        return ['price' => $price, 'stock' => $stock];
    }
}
