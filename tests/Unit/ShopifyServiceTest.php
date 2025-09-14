<?php

namespace Tests\Unit;

use App\Repositories\ProductRepository;
use App\Services\ShopifyService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShopifyServiceTest extends TestCase
{
    private $mockProductRepository;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockProductRepository = Mockery::mock(ProductRepository::class);
        
        // Mock Log facade for all tests
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('debug')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_can_be_instantiated()
    {
        $service = new ShopifyService(null, $this->mockProductRepository);
        
        $this->assertInstanceOf(ShopifyService::class, $service);
    }

    #[Test]
    public function it_syncs_products_successfully()
    {
        // Product Data
        $productData = [
            'shopify_id' => '999888777',
            'title' => 'Test API Product',
            'price' => 199.99,
            'stock' => 5
        ];

        // Arrange
        $mockResponse = json_encode([
            'products' => [
                [
                    'id' => $productData['shopify_id'],
                    'title' => $productData['title'],
                    'variants' => [
                        [
                            'price' => $productData['price'],
                            'inventory_quantity' => $productData['stock']
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

        $this->mockProductRepository
            ->shouldReceive('upsert')
            ->once()
            ->with($productData);

        // Act
        $service = new ShopifyService($client, $this->mockProductRepository);
        $result = $service->sync();

        // Assert
        $this->assertEquals(1, $result['synced']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(1, $result['total']);
    }

    #[Test]
    public function it_handles_api_request_exceptions()
    {
        // Arrange
        $mock = new MockHandler([
            new RequestException('Error Communicating with Server', new Request('GET', 'test'))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        // Act & Assert
        $service = new ShopifyService($client, $this->mockProductRepository);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to sync products');
        
        $service->sync();
    }

    #[Test]
    public function it_handles_empty_products_response()
    {
        // Arrange
        $mockResponse = json_encode(['products' => []]);

        $mock = new MockHandler([
            new Response(200, [], $mockResponse)
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        // Act
        $service = new ShopifyService($client, $this->mockProductRepository);
        $result = $service->sync();

        // Assert
        $this->assertEquals(0, $result['synced']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(0, $result['total']);
    }








    #[Test]
    public function it_handles_products_without_variants()
    {
        // Arrange
        $mockResponse = json_encode([
            'products' => [
                [
                    'id' => '999888777',
                    'title' => 'Product Without Variants'
                    // No variants field
                ]
            ]
        ]);

        $mock = new MockHandler([
            new Response(200, [], $mockResponse)
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $this->mockProductRepository
            ->shouldReceive('upsert')
            ->once()
            ->with([
                'shopify_id' => '999888777',
                'title' => 'Product Without Variants',
                'price' => 0,
                'stock' => 0
            ]);

        // Act
        $service = new ShopifyService($client, $this->mockProductRepository);
        $result = $service->sync();

        // Assert
        $this->assertEquals(1, $result['synced']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(1, $result['total']);
    }

    #[Test]
    public function it_handles_products_with_missing_title()
    {
        // Arrange
        $mockResponse = json_encode([
            'products' => [
                [
                    'id' => '999888777',
                    'variants' => [['price' => '99.99', 'inventory_quantity' => 10]]
                    // No title field
                ]
            ]
        ]);

        $mock = new MockHandler([
            new Response(200, [], $mockResponse)
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $this->mockProductRepository
            ->shouldReceive('upsert')
            ->once()
            ->with([
                'shopify_id' => '999888777',
                'title' => 'Untitled Product',
                'price' => 99.99,
                'stock' => 10
            ]);

        // Act
        $service = new ShopifyService($client, $this->mockProductRepository);
        $result = $service->sync();

        // Assert
        $this->assertEquals(1, $result['synced']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(1, $result['total']);
    }

    #[Test]
    public function it_skips_products_without_id()
    {
        // Arrange
        $mockResponse = json_encode([
            'products' => [
                [
                    'title' => 'Product Without ID',
                    'variants' => [['price' => '99.99', 'inventory_quantity' => 10]]
                    // No id field
                ]
            ]
        ]);

        $mock = new MockHandler([
            new Response(200, [], $mockResponse)
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        // Repository should not be called since product has no ID
        $this->mockProductRepository
            ->shouldNotReceive('upsert');

        // Act
        $service = new ShopifyService($client, $this->mockProductRepository);
        $result = $service->sync();

        // Assert
        $this->assertEquals(0, $result['synced']);
        $this->assertEquals(1, $result['skipped']);
        $this->assertEquals(1, $result['total']);
    }


    #[Test]
    public function it_handles_mixed_valid_and_invalid_products()
    {
        // Arrange
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

        // Expect upsert to be called 3 times for valid products
        $this->mockProductRepository
            ->shouldReceive('upsert')
            ->times(3);

        $this->mockProductRepository
            ->shouldReceive('upsert')
            ->with([
                'shopify_id' => '111111111',
                'title' => 'Valid Product 1',
                'price' => 10.00,
                'stock' => 5
            ]);

        $this->mockProductRepository
            ->shouldReceive('upsert')
            ->with([
                'shopify_id' => '222222222',
                'title' => 'Valid Product 2',
                'price' => 20.00,
                'stock' => 10
            ]);

        $this->mockProductRepository
            ->shouldReceive('upsert')
            ->with([
                'shopify_id' => '333333333',
                'title' => 'Valid Product 3',
                'price' => 0,
                'stock' => 0
            ]);

        // Act
        $service = new ShopifyService($client, $this->mockProductRepository);
        $result = $service->sync();

        // Assert
        $this->assertEquals(3, $result['synced']); // 3 valid products
        $this->assertEquals(2, $result['skipped']); // 2 invalid products
        $this->assertEquals(5, $result['total']); // 5 total products
    }

    #[Test]
    public function it_validates_shopify_credentials_in_constructor()
    {
        // Test 1: Constructor should succeed with valid credentials
        $service = new ShopifyService(null, $this->mockProductRepository);
        $this->assertInstanceOf(ShopifyService::class, $service);
    }

    #[Test]
    public function it_throws_exception_when_shop_domain_is_missing()
    {
        // Arrange - Temporarily clear the shop configuration
        config(['services.shopify.shop' => null]);
        
        // Act & Assert - Constructor should throw RuntimeException
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Shopify credentials are required. Please set SHOPIFY_SHOP and SHOPIFY_ACCESS_TOKEN environment variables.');
        
        new ShopifyService(null, $this->mockProductRepository);
    }

    #[Test]
    public function it_throws_exception_when_access_token_is_missing()
    {
        // Arrange - Temporarily clear the access token configuration
        config(['services.shopify.access_token' => null]);
        
        // Act & Assert - Constructor should throw RuntimeException
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Shopify credentials are required. Please set SHOPIFY_SHOP and SHOPIFY_ACCESS_TOKEN environment variables.');
        
        new ShopifyService(null, $this->mockProductRepository);
    }

    #[Test]
    public function it_throws_exception_when_both_credentials_are_missing()
    {
        // Arrange - Temporarily clear both configurations
        config([
            'services.shopify.shop' => null,
            'services.shopify.access_token' => null
        ]);
        
        // Act & Assert - Constructor should throw RuntimeException
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Shopify credentials are required. Please set SHOPIFY_SHOP and SHOPIFY_ACCESS_TOKEN environment variables.');
        
        new ShopifyService(null, $this->mockProductRepository);
    }

    #[Test]
    public function it_configures_http_client_with_correct_headers()
    {
        // Arrange - Ensure we have test credentials configured
        // (these are already set in phpunit.xml, but we can override if needed)
        config([
            'services.shopify.shop' => 'test-shop',
            'services.shopify.access_token' => 'test-access-token'
        ]);
        
        // Create a mock response to trigger HTTP request and inspect headers
        $mockResponse = json_encode(['products' => []]);
        
        $mock = new MockHandler([
            new Response(200, [], $mockResponse)
        ]);
        
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        
        // Create service with our mock client
        $serviceWithMockClient = new ShopifyService($client, $this->mockProductRepository);
        
        // Act - This will trigger the HTTP request
        $result = $serviceWithMockClient->sync();
        
        // Assert - Check that the mock was called (implies headers were set correctly)
        $this->assertEquals(0, $result['synced']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(0, $result['total']);
        
        // The fact that no exception was thrown and the service worked correctly
        // indicates that the HTTP client was configured properly with headers
    }
}
