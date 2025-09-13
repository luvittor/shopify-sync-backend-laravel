<?php

namespace App\Services;

use App\Repositories\ProductRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class ShopifyService
{
    private Client $httpClient;
    private ProductRepository $productRepository;
    private string $shopDomain;
    private string $accessToken;
    private string $apiVersion;

    /**
     * @param Client|null $httpClient Optional HTTP client for dependency injection
     * @param ProductRepository|null $productRepository Optional product repository for dependency injection
     */
    public function __construct(?Client $httpClient = null, ?ProductRepository $productRepository = null)
    {
        // Get Shopify credentials from environment
        $this->shopDomain = env('SHOPIFY_SHOP');
        $this->accessToken = env('SHOPIFY_ACCESS_TOKEN');
        $this->apiVersion = env('SHOPIFY_API_VERSION', '2025-07');
        
        if (!$this->shopDomain || !$this->accessToken) {
            throw new \RuntimeException('Shopify credentials are required. Please set SHOPIFY_SHOP and SHOPIFY_ACCESS_TOKEN environment variables.');
        }

        // Initialize HTTP client with default headers and timeout if not provided
        $this->httpClient = $httpClient ?? new Client([
            'headers' => [
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        $this->productRepository = $productRepository ?? new ProductRepository();
        
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
     * Build the Shopify products API URL
     *
     * @return string
     */
    private function buildProductsUrl(): string
    {
        return "https://{$this->shopDomain}.myshopify.com/admin/api/{$this->apiVersion}/products.json";
    }

    /**
     * Fetch products from Shopify Admin REST API
     * 
     * @return array Array of product data from Shopify
     */
    private function fetchProducts(): array
    {
        try {
            $url = $this->buildProductsUrl();
            
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
     * Parse variant data from a product
     *
     * @param array $product
     * @return array{price: float, stock: int}
     */
    private function parseVariant(array $product): array
    {
        $price = 0.0;
        $stock = 0;

        // Get price from first variant if available
        if (isset($product['variants']) && !empty($product['variants'])) {
            $firstVariant = $product['variants'][0];
            $price = (float) ($firstVariant['price'] ?? 0);
            $stock = (int) ($firstVariant['inventory_quantity'] ?? 0);
        }

        return ['price' => $price, 'stock' => $stock];
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
        $variant = $this->parseVariant($productData);

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
            'stock' => $variant['stock']
        ]);
        
        return true;
    }
}