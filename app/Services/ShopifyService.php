<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class ShopifyService
{
    private Client $httpClient;
    private string $shopDomain;
    private string $accessToken;
    private string $apiVersion;

    /**
     * @param Client|null $httpClient Optional HTTP client for dependency injection
     */
    public function __construct(?Client $httpClient = null)
    {
        $shopDomain = config('services.shopify.shop');
        $accessToken = config('services.shopify.access_token');
        $apiVersion = config('services.shopify.api_version', '2025-07');

        if (!$shopDomain || !$accessToken) {
            throw new \RuntimeException('Shopify credentials are required. Please set SHOPIFY_SHOP and SHOPIFY_ACCESS_TOKEN environment variables.');
        }

        $this->shopDomain = $shopDomain;
        $this->accessToken = $accessToken;
        $this->apiVersion = $apiVersion;

        $this->httpClient = $httpClient ?? new Client([
            'headers' => [
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        Log::info('ShopifyService initialized', [
            'shop' => $this->shopDomain,
            'api_version' => $this->apiVersion
        ]);
    }

    /**
     * Fetch products from Shopify Admin REST API
     *
     * @return array Array of product data from Shopify
     */
    public function fetchProducts(): array
    {
        try {
            $url = $this->buildProductsUrl();

            $response = $this->httpClient->get($url);

            $body = (string) $response->getBody();
            $data = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                Log::error('Invalid JSON from Shopify API', [
                    'shop' => $this->shopDomain,
                    'body_preview' => substr($body, 0, 200),
                ]);
                throw new \RuntimeException('Invalid JSON returned from Shopify');
            }
            
            $products = $data['products'] ?? [];

            return is_array($products) ? $products : [];

        } catch (RequestException $e) {
            Log::error('Failed to fetch products from Shopify API', [
                'error' => $e->getMessage(),
                'shop' => $this->shopDomain
            ]);

            throw new \Exception('Failed to fetch products from Shopify: ' . $e->getMessage());
        }
    }

    /**
     * Build the Shopify products API URL
     */
    private function buildProductsUrl(): string
    {
        return "https://{$this->shopDomain}.myshopify.com/admin/api/{$this->apiVersion}/products.json";
    }
}