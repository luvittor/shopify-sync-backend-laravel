<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\ShopifyService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShopifyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // We'll need to work with the service as-is since it reads from env() directly
        // The tests will verify behavior based on current environment state
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    #[Test]
    public function it_can_be_instantiated()
    {
        Log::shouldReceive('info')->once();
        
        $service = new ShopifyService();
        
        $this->assertInstanceOf(ShopifyService::class, $service);
    }

    #[Test]
    public function it_syncs_products_successfully_in_real_mode()
    {
        // Since the service will be in real mode due to .env, we need to mock the HTTP client
        $mockResponse = json_encode([
            'products' => [
                [
                    'id' => '999888777',
                    'title' => 'Test API Product',
                    'variants' => [
                        ['price' => '199.99', 'inventory_quantity' => 5]
                    ]
                ]
            ]
        ]);

        $mock = new MockHandler([
            new Response(200, [], $mockResponse)
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        Log::shouldReceive('info')->times(2); // Constructor + sync completion
        Log::shouldReceive('debug')->once(); // Product upserted

        // Create service and inject mocked client
        $service = new ShopifyService();
        $reflection = new \ReflectionClass($service);
        $httpClient = $reflection->getProperty('httpClient');
        $httpClient->setAccessible(true);
        $httpClient->setValue($service, $client);

        $result = $service->sync();

        $this->assertEquals('real', $result['mode']);
        $this->assertEquals(1, $result['synced']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(1, $result['total']);
        
        // Check that product was created in database
        $this->assertDatabaseHas('products', [
            'shopify_id' => '999888777',
            'title' => 'Test API Product',
            'price' => 199.99,
            'stock' => 5
        ]);
    }

    #[Test]
    public function it_handles_api_request_exceptions()
    {
        // Mock HTTP exception
        $mock = new MockHandler([
            new RequestException('Error Communicating with Server', new Request('GET', 'test'))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        Log::shouldReceive('info')->once(); // Constructor
        Log::shouldReceive('error')->twice(); // API error + sync failed

        $service = new ShopifyService();
        $reflection = new \ReflectionClass($service);
        $httpClient = $reflection->getProperty('httpClient');
        $httpClient->setAccessible(true);
        $httpClient->setValue($service, $client);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Product sync failed:');

        $service->sync();
    }

    #[Test]
    public function it_handles_empty_products_response()
    {
        // Mock empty response
        $mockResponse = json_encode(['products' => []]);

        $mock = new MockHandler([
            new Response(200, [], $mockResponse)
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        Log::shouldReceive('info')->times(2); // Constructor + sync completion

        $service = new ShopifyService();
        $reflection = new \ReflectionClass($service);
        $httpClient = $reflection->getProperty('httpClient');
        $httpClient->setAccessible(true);
        $httpClient->setValue($service, $client);

        $result = $service->sync();

        $this->assertEquals('real', $result['mode']);
        $this->assertEquals(0, $result['synced']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(0, $result['total']);
    }

    #[Test]
    public function it_updates_existing_products()
    {
        // Create an existing product
        Product::create([
            'shopify_id' => '999888777',
            'title' => 'Old Title',
            'price' => 50.00,
            'stock' => 5
        ]);

        $mockResponse = json_encode([
            'products' => [
                [
                    'id' => '999888777',
                    'title' => 'Updated Title',
                    'variants' => [
                        ['price' => '199.99', 'inventory_quantity' => 15]
                    ]
                ]
            ]
        ]);

        $mock = new MockHandler([
            new Response(200, [], $mockResponse)
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        Log::shouldReceive('info')->times(2); // Constructor + sync completion
        Log::shouldReceive('debug')->once(); // Product upserted

        $service = new ShopifyService();
        $reflection = new \ReflectionClass($service);
        $httpClient = $reflection->getProperty('httpClient');
        $httpClient->setAccessible(true);
        $httpClient->setValue($service, $client);

        $initialCount = Product::count();
        $result = $service->sync();

        // Product should be updated, not duplicated
        $this->assertEquals($initialCount, Product::count());
        $this->assertEquals(1, $result['synced']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(1, $result['total']);
        $this->assertDatabaseHas('products', [
            'shopify_id' => '999888777',
            'title' => 'Updated Title',
            'price' => 199.99,
            'stock' => 15
        ]);
    }

    #[Test]
    public function it_handles_products_without_variants()
    {
        $mockResponse = json_encode([
            'products' => [
                [
                    'id' => '999888777',
                    'title' => 'Product Without Variants'
                    // No 'variants' field
                ]
            ]
        ]);

        $mock = new MockHandler([
            new Response(200, [], $mockResponse)
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        Log::shouldReceive('info')->times(2); // Constructor + sync completion
        Log::shouldReceive('debug')->once(); // Product upserted

        $service = new ShopifyService();
        $reflection = new \ReflectionClass($service);
        $httpClient = $reflection->getProperty('httpClient');
        $httpClient->setAccessible(true);
        $httpClient->setValue($service, $client);

        $result = $service->sync();

        $this->assertEquals(1, $result['synced']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(1, $result['total']);
        
        // Should create product with default price and stock
        $this->assertDatabaseHas('products', [
            'shopify_id' => '999888777',
            'title' => 'Product Without Variants',
            'price' => 0,
            'stock' => 0
        ]);
    }

    #[Test]
    public function it_handles_products_with_missing_title()
    {
        $mockResponse = json_encode([
            'products' => [
                [
                    'id' => '999888777',
                    'variants' => [['price' => '99.99', 'inventory_quantity' => 10]]
                    // No 'title' field
                ]
            ]
        ]);

        $mock = new MockHandler([
            new Response(200, [], $mockResponse)
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        Log::shouldReceive('info')->times(2); // Constructor + sync completion
        Log::shouldReceive('debug')->once(); // Product upserted

        $service = new ShopifyService();
        $reflection = new \ReflectionClass($service);
        $httpClient = $reflection->getProperty('httpClient');
        $httpClient->setAccessible(true);
        $httpClient->setValue($service, $client);

        $result = $service->sync();

        $this->assertEquals(1, $result['synced']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(1, $result['total']);
        
        // Should create product with default title
        $this->assertDatabaseHas('products', [
            'shopify_id' => '999888777',
            'title' => 'Untitled Product',
            'price' => 99.99,
            'stock' => 10
        ]);
    }

    #[Test]
    public function it_skips_products_without_id()
    {
        $mockResponse = json_encode([
            'products' => [
                ['title' => 'Product without ID'], // Missing 'id' field
                [
                    'id' => '999888777',
                    'title' => 'Valid Product',
                    'variants' => [['price' => '99.99', 'inventory_quantity' => 10]]
                ]
            ]
        ]);

        $mock = new MockHandler([
            new Response(200, [], $mockResponse)
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        Log::shouldReceive('info')->times(2); // Constructor + sync completion
        Log::shouldReceive('warning')
            ->once()
            ->with('Product data missing ID, skipping', \Mockery::type('array'));
        Log::shouldReceive('debug')->once(); // Only valid product

        $service = new ShopifyService();
        $reflection = new \ReflectionClass($service);
        $httpClient = $reflection->getProperty('httpClient');
        $httpClient->setAccessible(true);
        $httpClient->setValue($service, $client);

        $result = $service->sync();

        // With the fixed counting logic, we now properly track synced vs skipped
        $this->assertEquals(1, $result['synced']); // Only valid product synced
        $this->assertEquals(1, $result['skipped']); // Invalid product skipped
        $this->assertEquals(2, $result['total']); // Total products processed
        
        // Verify only the valid product was created (the one without ID should be skipped)
        $this->assertDatabaseHas('products', [
            'shopify_id' => '999888777',
            'title' => 'Valid Product'
        ]);
        
        // Verify the invalid product was NOT created
        $this->assertDatabaseMissing('products', [
            'title' => 'Product without ID'
        ]);
    }

    #[Test]
    public function it_handles_mixed_valid_and_invalid_products()
    {
        $mockResponse = json_encode([
            'products' => [
                [
                    'id' => '111111111',
                    'title' => 'Valid Product 1',
                    'variants' => [['price' => '10.00', 'inventory_quantity' => 5]]
                ],
                ['title' => 'Invalid Product - No ID'],
                [
                    'id' => '222222222',
                    'title' => 'Valid Product 2',
                    'variants' => [['price' => '20.00', 'inventory_quantity' => 10]]
                ],
                ['title' => 'Another Invalid Product'],
                [
                    'id' => '333333333',
                    'title' => 'Valid Product 3'
                    // No variants
                ]
            ]
        ]);

        $mock = new MockHandler([
            new Response(200, [], $mockResponse)
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        Log::shouldReceive('info')->times(2); // Constructor + sync completion
        Log::shouldReceive('warning')->times(2); // Two invalid products
        Log::shouldReceive('debug')->times(3); // Three valid products

        $service = new ShopifyService();
        $reflection = new \ReflectionClass($service);
        $httpClient = $reflection->getProperty('httpClient');
        $httpClient->setAccessible(true);
        $httpClient->setValue($service, $client);

        $result = $service->sync();

        $this->assertEquals(3, $result['synced']); // 3 valid products
        $this->assertEquals(2, $result['skipped']); // 2 invalid products
        $this->assertEquals(5, $result['total']); // 5 total products
        
        // Verify all valid products were created
        $this->assertDatabaseHas('products', ['shopify_id' => '111111111']);
        $this->assertDatabaseHas('products', ['shopify_id' => '222222222']);
        $this->assertDatabaseHas('products', ['shopify_id' => '333333333']);
        
        // Verify invalid products were NOT created
        $this->assertDatabaseMissing('products', ['title' => 'Invalid Product - No ID']);
        $this->assertDatabaseMissing('products', ['title' => 'Another Invalid Product']);
    }
}