<?php

namespace Tests\Unit;

use App\Services\ShopifyService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShopifyServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
    }

    #[Test]
    public function it_can_be_instantiated()
    {
        $service = new ShopifyService();

        $this->assertInstanceOf(ShopifyService::class, $service);
    }

    #[Test]
    public function it_fetches_products_successfully()
    {
        $mockResponse = json_encode([
            'products' => [
                ['id' => '1', 'title' => 'Test Product'],
            ],
        ]);

        $mock = new MockHandler([
            new Response(200, [], $mockResponse),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new ShopifyService($client);

        $products = $service->fetchProducts();

        $this->assertCount(1, $products);
        $this->assertSame('Test Product', $products[0]['title']);
    }

    #[Test]
    public function it_handles_api_request_exceptions()
    {
        $mock = new MockHandler([
            new RequestException('Error Communicating with Server', new Request('GET', 'test')),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new ShopifyService($client);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to fetch products from Shopify: Error Communicating with Server');

        $service->fetchProducts();
    }

    #[Test]
    public function it_throws_exception_when_shop_domain_is_missing()
    {
        config(['services.shopify.shop' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Shopify credentials are required. Please set SHOPIFY_SHOP and SHOPIFY_ACCESS_TOKEN environment variables.');

        new ShopifyService();
    }

    #[Test]
    public function it_throws_exception_when_access_token_is_missing()
    {
        config(['services.shopify.access_token' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Shopify credentials are required. Please set SHOPIFY_SHOP and SHOPIFY_ACCESS_TOKEN environment variables.');

        new ShopifyService();
    }

    #[Test]
    public function it_throws_exception_when_both_credentials_are_missing()
    {
        config([
            'services.shopify.shop' => null,
            'services.shopify.access_token' => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Shopify credentials are required. Please set SHOPIFY_SHOP and SHOPIFY_ACCESS_TOKEN environment variables.');

        new ShopifyService();
    }

    #[Test]
    public function it_allows_custom_http_client_configuration()
    {
        config([
            'services.shopify.shop' => 'test-shop',
            'services.shopify.access_token' => 'test-access-token',
        ]);

        $mock = new MockHandler([
            new Response(200, [], json_encode(['products' => []])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new ShopifyService($client);

        $products = $service->fetchProducts();

        $this->assertSame([], $products);
    }

    #[Test]
    public function it_throws_exception_for_invalid_json_response()
    {
        $mockResponse = 'Invalid JSON String';

        $mock = new MockHandler([
            new Response(200, [], $mockResponse),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new ShopifyService($client);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON returned from Shopify');

        $service->fetchProducts();
    }

    #[Test]
    public function it_fetches_products_page_successfully()
    {
        $mockResponse = json_encode([
            'products' => [
                ['id' => '1', 'title' => 'Test Product 1'],
                ['id' => '2', 'title' => 'Test Product 2'],
            ],
        ]);

        $mock = new MockHandler([
            new Response(200, ['Link' => '<https://test-shop.myshopify.com/admin/api/2025-07/products.json?page_info=next_token>; rel="next"'], $mockResponse),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new ShopifyService($client);

        [$products, $nextPageInfo] = $service->fetchProductsPage();

        $this->assertCount(2, $products);
        $this->assertSame('Test Product 1', $products[0]['title']);
        $this->assertSame('Test Product 2', $products[1]['title']);
        $this->assertSame('next_token', $nextPageInfo);
    }

    #[Test]
    public function it_fetches_products_page_without_next_link()
    {
        $mockResponse = json_encode([
            'products' => [
                ['id' => '1', 'title' => 'Test Product 1'],
            ],
        ]);

        $mock = new MockHandler([
            new Response(200, [], $mockResponse),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new ShopifyService($client);

        [$products, $nextPageInfo] = $service->fetchProductsPage();

        $this->assertCount(1, $products);
        $this->assertSame('Test Product 1', $products[0]['title']);
        $this->assertNull($nextPageInfo);
    }

    #[Test]
    public function it_fetches_products_page_with_custom_parameters()
    {
        $mockResponse = json_encode([
            'products' => [
                ['id' => '1', 'title' => 'Test Product 1'],
            ],
        ]);

        $mock = new MockHandler([
            new Response(200, [], $mockResponse),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new ShopifyService($client);

        [$products, $nextPageInfo] = $service->fetchProductsPage('page_token_123', 10);

        $this->assertCount(1, $products);
        $this->assertSame('Test Product 1', $products[0]['title']);
        $this->assertNull($nextPageInfo);
    }

    #[Test]
    public function it_fetches_all_products_with_pagination()
    {
        $mockResponse1 = json_encode([
            'products' => [
                ['id' => '1', 'title' => 'Product 1'],
                ['id' => '2', 'title' => 'Product 2'],
            ],
        ]);

        $mockResponse2 = json_encode([
            'products' => [
                ['id' => '3', 'title' => 'Product 3'],
            ],
        ]);

        $mock = new MockHandler([
            new Response(200, ['Link' => '<https://test-shop.myshopify.com/admin/api/2025-07/products.json?page_info=next_token>; rel="next"'], $mockResponse1),
            new Response(200, [], $mockResponse2),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new ShopifyService($client);

        $allProducts = $service->fetchAllProducts();

        $this->assertCount(3, $allProducts);
        $this->assertSame('Product 1', $allProducts[0]['title']);
        $this->assertSame('Product 2', $allProducts[1]['title']);
        $this->assertSame('Product 3', $allProducts[2]['title']);
    }

    #[Test]
    public function it_fetches_all_products_with_single_page()
    {
        $mockResponse = json_encode([
            'products' => [
                ['id' => '1', 'title' => 'Product 1'],
            ],
        ]);

        $mock = new MockHandler([
            new Response(200, [], $mockResponse),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new ShopifyService($client);

        $allProducts = $service->fetchAllProducts();

        $this->assertCount(1, $allProducts);
        $this->assertSame('Product 1', $allProducts[0]['title']);
    }

    #[Test]
    public function it_handles_empty_products_response_in_pagination()
    {
        $mockResponse = json_encode(['products' => []]);

        $mock = new MockHandler([
            new Response(200, [], $mockResponse),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new ShopifyService($client);

        [$products, $nextPageInfo] = $service->fetchProductsPage();

        $this->assertSame([], actual: $products);
        $this->assertNull($nextPageInfo);
    }

    #[Test]
    public function it_handles_missing_products_key_in_pagination()
    {
        $mockResponse = json_encode(['other_data' => 'test']);

        $mock = new MockHandler([
            new Response(200, [], $mockResponse),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new ShopifyService($client);

        [$products, $nextPageInfo] = $service->fetchProductsPage();

        $this->assertSame([], $products);
        $this->assertNull($nextPageInfo);
    }
}
