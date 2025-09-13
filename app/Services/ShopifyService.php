<?php

namespace App\Services;

use App\Models\Product;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class ShopifyService
{
    private Client $httpClient;
    private string $shopDomain;
    private string $accessToken;
    private string $apiVersion;

    public function __construct()
    {
        $this->httpClient = new Client();
        
        // Get Shopify credentials from environment
        $this->shopDomain = env('SHOPIFY_SHOP');
        $this->accessToken = env('SHOPIFY_ACCESS_TOKEN');
        $this->apiVersion = env('SHOPIFY_API_VERSION', '2025-07');
        
        if (!$this->shopDomain || !$this->accessToken) {
            throw new \Exception('Shopify credentials are required. Please set SHOPIFY_SHOP and SHOPIFY_ACCESS_TOKEN environment variables.');
        }
        
        Log::info('ShopifyService initialized', [
            'shop' => $this->shopDomain,
            'api_version' => $this->apiVersion
        ]);
    }

    /**
     * Sync products from Shopify API
     * 
     * @return array JSON response with sync count and skip count
     */
    public function sync(): array
    {
        try {
            $products = $this->fetchProducts();
            $syncedCount = 0;
            $skippedCount = 0;

            foreach ($products as $productData) {
                $wasProcessed = $this->upsertProduct($productData);
                if ($wasProcessed) {
                    $syncedCount++;
                } else {
                    $skippedCount++;
                }
            }

            Log::info('Product sync completed', [
                'synced_count' => $syncedCount,
                'skipped_count' => $skippedCount,
                'total_processed' => count($products)
            ]);

            return [
                'synced' => $syncedCount,
                'skipped' => $skippedCount,
                'total' => count($products)
            ];

        } catch (\Exception $e) {
            Log::error('Product sync failed', [
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Product sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Fetch products from Shopify Admin REST API
     * 
     * @return array Array of product data from Shopify
     */
    private function fetchProducts(): array
    {
        try {
            $url = "https://{$this->shopDomain}.myshopify.com/admin/api/{$this->apiVersion}/products.json";
            
            $response = $this->httpClient->get($url, [
                'headers' => [
                    'X-Shopify-Access-Token' => $this->accessToken,
                    'Content-Type' => 'application/json',
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            return $data['products'] ?? [];

        } catch (RequestException $e) {
            Log::error('Failed to fetch products from Shopify API', [
                'error' => $e->getMessage(),
                'shop' => $this->shopDomain
            ]);
            
            throw new \Exception('Failed to fetch products from Shopify: ' . $e->getMessage());
        }
    }

    /**
     * Upsert product into database by shopify_id
     * 
     * @param array $productData Product data from Shopify or mock
     * @return bool True if product was successfully processed, false if skipped
     */
    private function upsertProduct(array $productData): bool
    {
        $shopifyId = $productData['id'] ?? null;
        
        if (!$shopifyId) {
            Log::warning('Product data missing ID, skipping', ['product' => $productData]);
            return false;
        }

        // Extract relevant data from Shopify product structure
        $title = $productData['title'] ?? 'Untitled Product';
        $price = 0;
        $stock = 0;

        // Get price from first variant if available
        if (isset($productData['variants']) && !empty($productData['variants'])) {
            $firstVariant = $productData['variants'][0];
            $price = (float) ($firstVariant['price'] ?? 0);
            $stock = (int) ($firstVariant['inventory_quantity'] ?? 0);
        }

        Product::updateOrCreate(
            ['shopify_id' => (string) $shopifyId],
            [
                'title' => $title,
                'price' => $price,
                'stock' => $stock,
            ]
        );

        Log::debug('Product upserted', [
            'shopify_id' => $shopifyId,
            'title' => $title,
            'price' => $price,
            'stock' => $stock
        ]);
        
        return true;
    }
}