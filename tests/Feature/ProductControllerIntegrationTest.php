<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Services\ProductService;
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

        // Create services with mocked dependencies
        $shopifyService = new ShopifyService($client);
        $productRepository = new ProductRepository();
        $productService = new ProductService($productRepository, $shopifyService);

        // Bind the service to the container
        $this->app->instance(ProductService::class, $productService);

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
    public function sync_endpoint_updates_existing_products_with_mocked_shopify_service()
    {
        // Create initial product
        $oldProductData = [
            'shopify_id' => '999888777',
            'title' => 'Old Product Title',
            'price' => 49.99,
            'stock' => 20
        ];
        Product::create($oldProductData);

        // New data to update the existing product
        $newProductData = [
            'shopify_id' => '999888777',
            'title' => 'Updated Product Title',
            'price' => 59.99,
            'stock' => 25
        ];

        // Mock Shopify API response with updated product data
        $mockResponse = json_encode([
            'products' => [
                [
                    'id' => $newProductData['shopify_id'],
                    'title' => $newProductData['title'],
                    'variants' => [
                        [
                            'price' => $newProductData['price'],
                            'inventory_quantity' => $newProductData['stock']
                        ]
                    ]
                ]
            ]
        ]);

        $mock = new MockHandler([
            new Response(200, [], $mockResponse)
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        // Create services with mocked dependencies
        $shopifyService = new ShopifyService($client);
        $productRepository = new ProductRepository();
        $productService = new ProductService($productRepository, $shopifyService);

        // Bind the service to the container
        $this->app->instance(ProductService::class, $productService);

        // Make request to sync endpoint
        $response = $this->postJson('/api/v1/products/sync');

        $response->assertStatus(200)
            ->assertJson([
                'synced' => 1,
                'skipped' => 0,
                'total' => 1
            ]);

        // Verify product was updated
        $this->assertDatabaseHas('products', $newProductData);

        // Verify old data no longer exists
        $this->assertDatabaseMissing('products', $oldProductData);

        // Ensure only one product exists
        $this->assertEquals(1, Product::count());
    }

    #[Test]
    public function sync_endpoint_handles_service_errors_gracefully()
    {
        // Create a service that will throw an exception
        $mockService = $this->createMock(ProductService::class);
        $mockService->method('syncFromShopify')
            ->willThrowException(new \Exception('Shopify API is down'));

        // Bind the service to the container
        $this->app->instance(ProductService::class, $mockService);

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
        $productService = new class extends ProductService {
            public bool $listPaginatedCalled = false;

            public function __construct()
            {
            }

            public function listPaginated(int $perPage = 15): \Illuminate\Pagination\LengthAwarePaginator
            {
                $this->listPaginatedCalled = true;

                return new \Illuminate\Pagination\LengthAwarePaginator(
                    [],
                    0,
                    $perPage,
                    1,
                    ['path' => request()->url(), 'pageName' => 'page']
                );
            }

            public function clear(): int
            {
                return 0;
            }

            public function syncFromShopify(): array
            {
                return [
                    'synced' => 0,
                    'skipped' => 0,
                    'total' => 0,
                ];
            }
        };

        $this->app->instance(ProductService::class, $productService);

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200);
        $this->assertTrue($productService->listPaginatedCalled);
    }

    #[Test]
    public function api_endpoints_maintain_consistent_response_format()
    {
        // Test products endpoint response format
        $productsResponse = $this->getJson('/api/v1/products');
        $productsResponse->assertStatus(200)
            ->assertJsonStructure(['data', 'pagination', 'links']);

        // Create a mock service for sync endpoint
        $mockService = $this->createMock(ProductService::class);
        $mockService->method('syncFromShopify')->willReturn([
            'synced' => 0,
            'skipped' => 0,
            'total' => 0
        ]);

        $this->app->instance(ProductService::class, $mockService);

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