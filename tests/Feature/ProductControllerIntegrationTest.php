<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Services\ShopifyService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductControllerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Silence log expectations for integration tests
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('debug')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
    }

    #[Test]
    public function get_products_endpoint_works_with_dependency_injection()
    {
        // Create some test products
        Product::create([
            'shopify_id' => '123',
            'title' => 'Test Product 1',
            'price' => 19.99,
            'stock' => 10
        ]);

        Product::create([
            'shopify_id' => '456',
            'title' => 'Test Product 2',
            'price' => 29.99,
            'stock' => 5
        ]);

        // Make request to the API endpoint
        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'shopify_id',
                        'title',
                        'price',
                        'stock',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'pagination' => [
                    'current_page',
                    'per_page',
                    'total',
                    'last_page',
                    'from',
                    'to',
                    'has_more_pages',
                    'prev_page_url',
                    'next_page_url'
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next'
                ]
            ])
            ->assertJsonCount(2, 'data')
            ->assertJson([
                'pagination' => [
                    'total' => 2
                ]
            ]);
    }

    #[Test]
    public function get_products_endpoint_returns_empty_list_when_no_products()
    {
        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [],
                'pagination' => [
                    'total' => 0
                ]
            ]);
    }

    #[Test]
    public function sync_endpoint_works_with_mocked_shopify_service()
    {
        // Mock Shopify API response
        $mockResponse = json_encode([
            'products' => [
                [
                    'id' => '999888777',
                    'title' => 'API Product',
                    'variants' => [
                        ['price' => '99.99', 'inventory_quantity' => 15]
                    ]
                ]
            ]
        ]);

        $mock = new MockHandler([
            new Response(200, [], $mockResponse)
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        // Create service with mocked HTTP client
        $shopifyService = new ShopifyService($client);
        $productRepository = new ProductRepository();

        // Bind the mocked service to the container
        $this->app->instance(ShopifyService::class, $shopifyService);
        $this->app->instance(ProductRepository::class, $productRepository);

        // Make request to sync endpoint
        $response = $this->postJson('/api/v1/products/sync');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'synced',
                'skipped',
                'total'
            ])
            ->assertJson([
                'synced' => 1,
                'skipped' => 0,
                'total' => 1
            ]);

        // Verify product was created
        $this->assertDatabaseHas('products', [
            'shopify_id' => '999888777',
            'title' => 'API Product',
            'price' => 99.99,
            'stock' => 15
        ]);
    }

    #[Test]
    public function sync_endpoint_handles_service_errors_gracefully()
    {
        // Create a service that will throw an exception
        $mockService = $this->createMock(ShopifyService::class);
        $mockService->method('sync')
            ->willThrowException(new \Exception('Shopify API is down'));

        // Bind the service to the container
        $this->app->instance(ShopifyService::class, $mockService);

        // Make request to sync endpoint
        $response = $this->postJson('/api/v1/products/sync');

        $response->assertStatus(500)
            ->assertJsonStructure([
                'error',
                'message'
            ])
            ->assertJson([
                'error' => 'Failed to sync products',
                'message' => 'Shopify API is down'
            ]);
    }

    #[Test]
    public function controller_uses_injected_dependencies()
    {
        // Create custom repository that we can track
        $productRepository = new class extends ProductRepository {
            public bool $listPaginatedCalled = false;

            public function listPaginated(int $perPage = 15): \Illuminate\Pagination\LengthAwarePaginator
            {
                $this->listPaginatedCalled = true;
                
                // Create a mock paginator for testing
                return new \Illuminate\Pagination\LengthAwarePaginator(
                    [],  // items
                    0,   // total
                    $perPage, // perPage  
                    1,   // currentPage
                    ['path' => request()->url(), 'pageName' => 'page']
                );
            }
        };

        // Bind to container
        $this->app->instance(ProductRepository::class, $productRepository);

        // Make request
        $response = $this->getJson('/api/v1/products');

        // Verify the injected repository was used
        $response->assertStatus(200);
        $this->assertTrue($productRepository->listPaginatedCalled);
    }

    #[Test]
    public function api_endpoints_maintain_consistent_response_format()
    {
        // Test products endpoint response format
        $productsResponse = $this->getJson('/api/v1/products');
        $productsResponse->assertStatus(200)
            ->assertJsonStructure(['data', 'pagination', 'links']);

        // Create a mock service for sync endpoint
        $mockService = $this->createMock(ShopifyService::class);
        $mockService->method('sync')->willReturn([
            'synced' => 0,
            'skipped' => 0,
            'total' => 0
        ]);

        $this->app->instance(ShopifyService::class, $mockService);

        // Test sync endpoint response format
        $syncResponse = $this->postJson('/api/v1/products/sync');
        $syncResponse->assertStatus(200)
            ->assertJsonStructure(['synced', 'skipped', 'total']);
    }

    #[Test]
    public function clear_all_products_endpoint_works()
    {
        // Create some test products
        Product::create([
            'shopify_id' => '123',
            'title' => 'Test Product 1',
            'price' => 19.99,
            'stock' => 10
        ]);

        Product::create([
            'shopify_id' => '456',
            'title' => 'Test Product 2', 
            'price' => 29.99,
            'stock' => 5
        ]);

        Product::create([
            'shopify_id' => '789',
            'title' => 'Test Product 3',
            'price' => 39.99,
            'stock' => 15
        ]);

        // Verify products exist
        $this->assertEquals(3, Product::count());

        // Make request to clear all products
        $response = $this->deleteJson('/api/v1/products/clear');

        // Verify response
        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'cleared'])
            ->assertJson([
                'message' => 'All products cleared successfully',
                'cleared' => 3
            ]);

        // Verify all products are cleared
        $this->assertEquals(0, Product::count());
    }

    #[Test]
    public function clear_all_products_handles_empty_database()
    {
        // Ensure no products exist
        $this->assertEquals(0, Product::count());

        // Make request to clear all products
        $response = $this->deleteJson('/api/v1/products/clear');

        // Verify response
        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'cleared'])
            ->assertJson([
                'message' => 'All products cleared successfully',
                'cleared' => 0
            ]);

        // Verify still no products
        $this->assertEquals(0, Product::count());
    }
}