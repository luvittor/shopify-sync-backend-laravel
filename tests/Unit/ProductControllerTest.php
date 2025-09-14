<?php

namespace Tests\Unit;

use App\Http\Controllers\ProductController;
use App\Repositories\ProductRepository;
use App\Services\ShopifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{

    private $mockProductRepository;
    private $mockShopifyService;
    private ProductController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockProductRepository = Mockery::mock(ProductRepository::class);
        $this->mockShopifyService = Mockery::mock(ShopifyService::class);
        $this->controller = new ProductController($this->mockProductRepository, $this->mockShopifyService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_can_be_instantiated()
    {
        $this->assertInstanceOf(ProductController::class, $this->controller);
    }

    #[Test]
    public function index_returns_products_successfully()
    {
        // Arrange
        $products = collect([
            ['id' => 1, 'shopify_id' => '123', 'title' => 'Product 1', 'price' => 19.99, 'stock' => 10],
            ['id' => 2, 'shopify_id' => '456', 'title' => 'Product 2', 'price' => 29.99, 'stock' => 5],
        ]);

        $mockPaginator = Mockery::mock(LengthAwarePaginator::class);
        $mockPaginator->shouldReceive('items')->andReturn($products->toArray());
        $mockPaginator->shouldReceive('currentPage')->andReturn(1);
        $mockPaginator->shouldReceive('perPage')->andReturn(15);
        $mockPaginator->shouldReceive('total')->andReturn(2);
        $mockPaginator->shouldReceive('lastPage')->andReturn(1);
        $mockPaginator->shouldReceive('firstItem')->andReturn(1);
        $mockPaginator->shouldReceive('lastItem')->andReturn(2);
        $mockPaginator->shouldReceive('hasMorePages')->andReturn(false);
        $mockPaginator->shouldReceive('previousPageUrl')->andReturn(null);
        $mockPaginator->shouldReceive('nextPageUrl')->andReturn(null);
        $mockPaginator->shouldReceive('url')->with(1)->andReturn('http://localhost/api/v1/products?page=1');

        $this->mockProductRepository
            ->shouldReceive('listPaginated')
            ->once()
            ->with(15)
            ->andReturn($mockPaginator);

        $request = new Request(['per_page' => 15]);

        // Act
        $response = $this->controller->index($request);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('pagination', $responseData);
        $this->assertArrayHasKey('links', $responseData);
        $this->assertCount(2, $responseData['data']);
        $this->assertEquals(2, $responseData['pagination']['total']);
    }

    #[Test]
    public function index_returns_empty_products_list()
    {
        // Arrange
        $mockPaginator = Mockery::mock(LengthAwarePaginator::class);
        $mockPaginator->shouldReceive('items')->andReturn([]);
        $mockPaginator->shouldReceive('currentPage')->andReturn(1);
        $mockPaginator->shouldReceive('perPage')->andReturn(10);
        $mockPaginator->shouldReceive('total')->andReturn(0);
        $mockPaginator->shouldReceive('lastPage')->andReturn(1);
        $mockPaginator->shouldReceive('firstItem')->andReturn(null);
        $mockPaginator->shouldReceive('lastItem')->andReturn(null);
        $mockPaginator->shouldReceive('hasMorePages')->andReturn(false);
        $mockPaginator->shouldReceive('previousPageUrl')->andReturn(null);
        $mockPaginator->shouldReceive('nextPageUrl')->andReturn(null);
        $mockPaginator->shouldReceive('url')->andReturn('http://localhost/api/v1/products?page=1');

        $this->mockProductRepository
            ->shouldReceive('listPaginated')
            ->once()
            ->with(10)  // Default per_page is 10, not 15
            ->andReturn($mockPaginator);

        $request = new Request();

        // Act
        $response = $this->controller->index($request);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('pagination', $responseData);
        $this->assertEquals(0, $responseData['pagination']['total']);
        $this->assertEmpty($responseData['data']);
    }

    #[Test]
    public function index_has_correct_json_structure()
    {
        // Arrange
        $products = collect([
            [
                'id' => 1, 
                'shopify_id' => '123', 
                'title' => 'Test Product', 
                'price' => 19.99, 
                'stock' => 10,
                'created_at' => '2025-09-13T00:00:00.000000Z',
                'updated_at' => '2025-09-13T00:00:00.000000Z'
            ],
        ]);

        $mockPaginator = Mockery::mock(LengthAwarePaginator::class);
        $mockPaginator->shouldReceive('items')->andReturn($products->toArray());
        $mockPaginator->shouldReceive('currentPage')->andReturn(1);
        $mockPaginator->shouldReceive('perPage')->andReturn(10);
        $mockPaginator->shouldReceive('total')->andReturn(1);
        $mockPaginator->shouldReceive('lastPage')->andReturn(1);
        $mockPaginator->shouldReceive('firstItem')->andReturn(1);
        $mockPaginator->shouldReceive('lastItem')->andReturn(1);
        $mockPaginator->shouldReceive('hasMorePages')->andReturn(false);
        $mockPaginator->shouldReceive('previousPageUrl')->andReturn(null);
        $mockPaginator->shouldReceive('nextPageUrl')->andReturn(null);
        $mockPaginator->shouldReceive('url')->andReturn('http://localhost/api/v1/products?page=1');

        $this->mockProductRepository
            ->shouldReceive('listPaginated')
            ->once()
            ->with(10)  // Default per_page is 10, not 15
            ->andReturn($mockPaginator);

        $request = new Request();

        // Act
        $response = $this->controller->index($request);

        // Assert
        $responseData = $response->getData(true);
        
        // Validate overall structure
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('pagination', $responseData);
        $this->assertArrayHasKey('links', $responseData);
        
        // Validate product structure
        $product = $responseData['data'][0];
        $this->assertArrayHasKey('id', $product);
        $this->assertArrayHasKey('shopify_id', $product);
        $this->assertArrayHasKey('title', $product);
        $this->assertArrayHasKey('price', $product);
        $this->assertArrayHasKey('stock', $product);
        $this->assertArrayHasKey('created_at', $product);
        $this->assertArrayHasKey('updated_at', $product);
    }

    #[Test]
    public function index_handles_exception()
    {
        // Arrange
        $exceptionMessage = 'Database connection failed';
        
        $this->mockProductRepository
            ->shouldReceive('listPaginated')
            ->once()
            ->andThrow(new \Exception($exceptionMessage));

        $request = new Request();

        // Act
        $response = $this->controller->index($request);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Failed to fetch products', $responseData['error']);
        $this->assertEquals($exceptionMessage, $responseData['message']);
    }

    #[Test]
    public function sync_returns_successful_response()
    {
        // Arrange
        $syncResult = [
            'synced' => 5,
            'skipped' => 1,
            'total' => 6
        ];

        $this->mockShopifyService
            ->shouldReceive('sync')
            ->once()
            ->andReturn($syncResult);

        // Act
        $response = $this->controller->sync();

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertEquals($syncResult, $responseData);
        $this->assertArrayHasKey('synced', $responseData);
        $this->assertArrayHasKey('skipped', $responseData);
        $this->assertArrayHasKey('total', $responseData);
    }

    #[Test]
    public function sync_handles_service_exception()
    {
        // Arrange
        $exceptionMessage = 'Shopify API connection failed';
        
        $this->mockShopifyService
            ->shouldReceive('sync')
            ->once()
            ->andThrow(new \Exception($exceptionMessage));

        // Act
        $response = $this->controller->sync();

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Failed to sync products', $responseData['error']);
        $this->assertEquals($exceptionMessage, $responseData['message']);
    }

    #[Test]
    public function sync_has_correct_error_json_structure()
    {
        // Arrange
        $this->mockShopifyService
            ->shouldReceive('sync')
            ->once()
            ->andThrow(new \Exception('Test error'));

        // Act
        $response = $this->controller->sync();

        // Assert
        $responseData = $response->getData(true);
        
        $this->assertArrayHasKey('error', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertIsString($responseData['error']);
        $this->assertIsString($responseData['message']);
    }

    #[Test]
    public function sync_handles_runtime_exception()
    {
        // Arrange
        $this->mockShopifyService
            ->shouldReceive('sync')
            ->once()
            ->andThrow(new \RuntimeException('Configuration error'));

        // Act
        $response = $this->controller->sync();

        // Assert
        $this->assertEquals(500, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertEquals('Failed to sync products', $responseData['error']);
        $this->assertEquals('Configuration error', $responseData['message']);
    }

    #[Test]
    public function sync_returns_zero_counts_for_empty_sync()
    {
        // Arrange
        $syncResult = [
            'synced' => 0,
            'skipped' => 0,
            'total' => 0
        ];

        $this->mockShopifyService
            ->shouldReceive('sync')
            ->once()
            ->andReturn($syncResult);

        // Act
        $response = $this->controller->sync();

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertEquals(0, $responseData['synced']);
        $this->assertEquals(0, $responseData['skipped']);
        $this->assertEquals(0, $responseData['total']);
    }

    #[Test]
    public function clear_returns_successful_response()
    {
        // Arrange
        $clearedCount = 5;

        $this->mockProductRepository
            ->shouldReceive('clear')
            ->once()
            ->andReturn($clearedCount);

        // Act
        $response = $this->controller->clear();

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('cleared', $responseData);
        $this->assertEquals('All products cleared successfully', $responseData['message']);
        $this->assertEquals($clearedCount, $responseData['cleared']);
    }

    #[Test]
    public function clear_returns_zero_count_when_no_products()
    {
        // Arrange
        $clearedCount = 0;

        $this->mockProductRepository
            ->shouldReceive('clear')
            ->once()
            ->andReturn($clearedCount);

        // Act
        $response = $this->controller->clear();

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertEquals('All products cleared successfully', $responseData['message']);
        $this->assertEquals(0, $responseData['cleared']);
    }

    #[Test]
    public function clear_handles_exception()
    {
        // Arrange
        $exceptionMessage = 'Database connection failed';
        
        $this->mockProductRepository
            ->shouldReceive('clear')
            ->once()
            ->andThrow(new \Exception($exceptionMessage));

        // Act
        $response = $this->controller->clear();

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Failed to clear products', $responseData['error']);
        $this->assertEquals($exceptionMessage, $responseData['message']);
    }

    #[Test]
    public function clear_has_correct_json_structure()
    {
        // Arrange
        $this->mockProductRepository
            ->shouldReceive('clear')
            ->once()
            ->andReturn(3);

        // Act
        $response = $this->controller->clear();

        // Assert
        $responseData = $response->getData(true);
        
        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('cleared', $responseData);
        $this->assertIsString($responseData['message']);
        $this->assertIsInt($responseData['cleared']);
    }

    #[Test]
    public function index_handles_per_page_less_than_one()
    {
        // Arrange
        $mockPaginator = Mockery::mock(LengthAwarePaginator::class);
        $mockPaginator->shouldReceive('items')->andReturn([]);
        $mockPaginator->shouldReceive('currentPage')->andReturn(1);
        $mockPaginator->shouldReceive('perPage')->andReturn(10); // Should default to 10
        $mockPaginator->shouldReceive('total')->andReturn(0);
        $mockPaginator->shouldReceive('lastPage')->andReturn(1);
        $mockPaginator->shouldReceive('firstItem')->andReturn(null);
        $mockPaginator->shouldReceive('lastItem')->andReturn(null);
        $mockPaginator->shouldReceive('hasMorePages')->andReturn(false);
        $mockPaginator->shouldReceive('previousPageUrl')->andReturn(null);
        $mockPaginator->shouldReceive('nextPageUrl')->andReturn(null);
        $mockPaginator->shouldReceive('url')->andReturn('http://localhost/api/v1/products?page=1');

        $this->mockProductRepository
            ->shouldReceive('listPaginated')
            ->with(10) // Should be corrected to 10, not the invalid value
            ->once()
            ->andReturn($mockPaginator);

        $request = new Request(['per_page' => -5]); // Invalid: less than 1

        // Act
        $response = $this->controller->index($request);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertEquals(10, $responseData['pagination']['per_page']); // Should be corrected to 10
    }

    #[Test]
    public function index_handles_per_page_greater_than_hundred()
    {
        // Arrange
        $mockPaginator = Mockery::mock(LengthAwarePaginator::class);
        $mockPaginator->shouldReceive('items')->andReturn([]);
        $mockPaginator->shouldReceive('currentPage')->andReturn(1);
        $mockPaginator->shouldReceive('perPage')->andReturn(100); // Should be capped at 100
        $mockPaginator->shouldReceive('total')->andReturn(0);
        $mockPaginator->shouldReceive('lastPage')->andReturn(1);
        $mockPaginator->shouldReceive('firstItem')->andReturn(null);
        $mockPaginator->shouldReceive('lastItem')->andReturn(null);
        $mockPaginator->shouldReceive('hasMorePages')->andReturn(false);
        $mockPaginator->shouldReceive('previousPageUrl')->andReturn(null);
        $mockPaginator->shouldReceive('nextPageUrl')->andReturn(null);
        $mockPaginator->shouldReceive('url')->andReturn('http://localhost/api/v1/products?page=1');

        $this->mockProductRepository
            ->shouldReceive('listPaginated')
            ->with(100) // Should be capped at 100, not the invalid value
            ->once()
            ->andReturn($mockPaginator);

        $request = new Request(['per_page' => 150]); // Invalid: greater than 100

        // Act
        $response = $this->controller->index($request);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertEquals(100, $responseData['pagination']['per_page']); // Should be capped at 100
    }

    #[Test]
    public function index_handles_per_page_zero()
    {
        // Arrange
        $mockPaginator = Mockery::mock(LengthAwarePaginator::class);
        $mockPaginator->shouldReceive('items')->andReturn([]);
        $mockPaginator->shouldReceive('currentPage')->andReturn(1);
        $mockPaginator->shouldReceive('perPage')->andReturn(10); // Should default to 10
        $mockPaginator->shouldReceive('total')->andReturn(0);
        $mockPaginator->shouldReceive('lastPage')->andReturn(1);
        $mockPaginator->shouldReceive('firstItem')->andReturn(null);
        $mockPaginator->shouldReceive('lastItem')->andReturn(null);
        $mockPaginator->shouldReceive('hasMorePages')->andReturn(false);
        $mockPaginator->shouldReceive('previousPageUrl')->andReturn(null);
        $mockPaginator->shouldReceive('nextPageUrl')->andReturn(null);
        $mockPaginator->shouldReceive('url')->andReturn('http://localhost/api/v1/products?page=1');

        $this->mockProductRepository
            ->shouldReceive('listPaginated')
            ->with(10) // Should be corrected to 10, not 0
            ->once()
            ->andReturn($mockPaginator);

        $request = new Request(['per_page' => 0]); // Invalid: zero

        // Act
        $response = $this->controller->index($request);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertEquals(10, $responseData['pagination']['per_page']); // Should be corrected to 10
    }

    #[Test]
    public function index_handles_valid_per_page_values()
    {
        // Arrange
        $mockPaginator = Mockery::mock(LengthAwarePaginator::class);
        $mockPaginator->shouldReceive('items')->andReturn([]);
        $mockPaginator->shouldReceive('currentPage')->andReturn(1);
        $mockPaginator->shouldReceive('perPage')->andReturn(25); // Should use the valid value
        $mockPaginator->shouldReceive('total')->andReturn(0);
        $mockPaginator->shouldReceive('lastPage')->andReturn(1);
        $mockPaginator->shouldReceive('firstItem')->andReturn(null);
        $mockPaginator->shouldReceive('lastItem')->andReturn(null);
        $mockPaginator->shouldReceive('hasMorePages')->andReturn(false);
        $mockPaginator->shouldReceive('previousPageUrl')->andReturn(null);
        $mockPaginator->shouldReceive('nextPageUrl')->andReturn(null);
        $mockPaginator->shouldReceive('url')->andReturn('http://localhost/api/v1/products?page=1');

        $this->mockProductRepository
            ->shouldReceive('listPaginated')
            ->with(25) // Should use the exact valid value
            ->once()
            ->andReturn($mockPaginator);

        $request = new Request(['per_page' => 25]); // Valid value

        // Act
        $response = $this->controller->index($request);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $responseData = $response->getData(true);
        $this->assertEquals(25, $responseData['pagination']['per_page']); // Should use the exact value
    }
}
