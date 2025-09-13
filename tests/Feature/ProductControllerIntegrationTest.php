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
        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'products' => [
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
                'count'
            ])
            ->assertJsonCount(2, 'products')
            ->assertJson([
                'count' => 2
            ]);
    }

    #[Test]
    public function get_products_endpoint_returns_empty_list_when_no_products()
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJson([
                'products' => [],
                'count' => 0
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
        $response = $this->postJson('/api/products/sync');

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
        $response = $this->postJson('/api/products/sync');

        $response->assertStatus(500)
            ->assertJsonStructure([
                'error',
                'message'
            ])
            ->assertJson([
                'error' => 'Sync failed',
                'message' => 'Shopify API is down'
            ]);
    }

    #[Test]
    public function controller_uses_injected_dependencies()
    {
        // Create custom repository that we can track
        $productRepository = new class extends ProductRepository {
            public bool $listCalled = false;

            public function list(): \Illuminate\Support\Collection
            {
                $this->listCalled = true;
                return collect([]);
            }
        };

        // Bind to container
        $this->app->instance(ProductRepository::class, $productRepository);

        // Make request
        $response = $this->getJson('/api/products');

        // Verify the injected repository was used
        $response->assertStatus(200);
        $this->assertTrue($productRepository->listCalled);
    }

    #[Test]
    public function api_endpoints_maintain_consistent_response_format()
    {
        // Test products endpoint response format
        $productsResponse = $this->getJson('/api/products');
        $productsResponse->assertStatus(200)
            ->assertJsonStructure(['products', 'count']);

        // Create a mock service for sync endpoint
        $mockService = $this->createMock(ShopifyService::class);
        $mockService->method('sync')->willReturn([
            'synced' => 0,
            'skipped' => 0,
            'total' => 0
        ]);

        $this->app->instance(ShopifyService::class, $mockService);

        // Test sync endpoint response format
        $syncResponse = $this->postJson('/api/products/sync');
        $syncResponse->assertStatus(200)
            ->assertJsonStructure(['synced', 'skipped', 'total']);
    }
}