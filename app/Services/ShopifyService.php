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
                'Accept' => 'application/json',
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
     * This method fetches all products using pagination transparently
     *
     * @return array Array of product data from Shopify
     */
    public function fetchProducts(): array
    {
        try {
            return $this->fetchAllProducts();
        } catch (RequestException $e) {
            Log::error('Failed to fetch products from Shopify API', [
                'error' => $e->getMessage(),
                'shop' => $this->shopDomain
            ]);

            throw new \Exception('Failed to fetch products from Shopify: ' . $e->getMessage());
        }
    }

    /**
     * Fetch a single page of products from Shopify Admin REST API
     *
     * @param string|null $pageInfo Page info token for pagination
     * @param int $limit Number of products per page (max 250)
     * @return array [products array, next page info token or null]
     */
    public function fetchProductsPage(?string $pageInfo = null, int $limit = 250): array
    {
        $url = $this->buildProductsUrl();
        $limit = max(1, min(250, $limit));

        $options = [
            'query' => array_filter([
                'limit' => $limit,
                'page_info' => $pageInfo ? rawurldecode($pageInfo) : null,
            ]),
        ];

        $response = $this->httpClient->get($url, $options);
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            Log::error('Invalid JSON from Shopify API', [
                'shop' => $this->shopDomain,
                'body_preview' => substr($body, 0, 200),
            ]);
            throw new \RuntimeException('Invalid JSON returned from Shopify');
        }

        $products = is_array($data['products'] ?? null) ? $data['products'] : [];
        $next = null;
        $link = $response->getHeaderLine('Link');
        if ($link && preg_match('/<[^>]*[?&]page_info=([^&>]+)[^>]*>;\s*rel="next"/', $link, $m)) {
            $next = $m[1];
        }
        
        return [$products, $next];
    }

    /**
     * Fetch all products from Shopify using pagination
     *
     * @param int $limit Number of products per page (max 250)
     * @return array Array of all product data from Shopify
     */
    public function fetchAllProducts(int $limit = 250): array
    {
        $all = [];
        $next = null;
        do {
            [$batch, $next] = $this->fetchProductsPage($next, $limit);
            $all = array_merge($all, $batch);
        } while ($next);
        return $all;
    }

    /**
     * Build the Shopify products API URL
     */
    private function buildProductsUrl(): string
    {
        return "https://{$this->shopDomain}.myshopify.com/admin/api/{$this->apiVersion}/products.json";
    }
}