<?php

namespace Tests\Unit;

use App\Repositories\ProductRepository;
use App\Services\ProductService;
use App\Services\ShopifyService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductServiceTest extends TestCase
{
    private $mockProductRepository;
    private $mockShopifyService;
    private ProductService $productService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockProductRepository = Mockery::mock(ProductRepository::class);
        $this->mockShopifyService = Mockery::mock(ShopifyService::class);
        $this->productService = new ProductService($this->mockProductRepository, $this->mockShopifyService);

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
    public function it_lists_products_using_repository()
    {
        $mockPaginator = Mockery::mock(LengthAwarePaginator::class);

        $this->mockProductRepository
            ->shouldReceive('listPaginated')
            ->once()
            ->with(25)
            ->andReturn($mockPaginator);

        $result = $this->productService->listPaginated(25);

        $this->assertSame($mockPaginator, $result);
    }

    #[Test]
    public function it_clears_products_via_repository()
    {
        $this->mockProductRepository
            ->shouldReceive('clear')
            ->once()
            ->andReturn(7);

        $cleared = $this->productService->clear();

        $this->assertSame(7, $cleared);
    }

    #[Test]
    public function it_syncs_products_successfully()
    {
        $this->mockShopifyService
            ->shouldReceive('fetchProducts')
            ->once()
            ->andReturn([
                [
                    'id' => '999888777',
                    'title' => 'Test API Product',
                    'variants' => [
                        ['price' => 199.99, 'inventory_quantity' => 5],
                    ],
                ],
            ]);

        $this->mockProductRepository
            ->shouldReceive('upsert')
            ->once()
            ->with([
                'shopify_id' => '999888777',
                'title' => 'Test API Product',
                'price' => 199.99,
                'stock' => 5,
            ]);

        $result = $this->productService->syncFromShopify();

        $this->assertEquals(1, $result['synced']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(1, $result['total']);
    }

    #[Test]
    public function it_handles_shopify_service_exceptions()
    {
        $this->mockShopifyService
            ->shouldReceive('fetchProducts')
            ->once()
            ->andThrow(new \Exception('Shopify API connection failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to sync products: Shopify API connection failed');

        $this->productService->syncFromShopify();
    }

    #[Test]
    public function it_handles_empty_products_response()
    {
        $this->mockShopifyService
            ->shouldReceive('fetchProducts')
            ->once()
            ->andReturn([]);

        $this->mockProductRepository
            ->shouldNotReceive('upsert');

        $result = $this->productService->syncFromShopify();

        $this->assertEquals(0, $result['synced']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(0, $result['total']);
    }

    #[Test]
    public function it_handles_products_without_variants()
    {
        $this->mockShopifyService
            ->shouldReceive('fetchProducts')
            ->once()
            ->andReturn([
                [
                    'id' => '999888777',
                    'title' => 'Product Without Variants',
                ],
            ]);

        $this->mockProductRepository
            ->shouldReceive('upsert')
            ->once()
            ->with([
                'shopify_id' => '999888777',
                'title' => 'Product Without Variants',
                'price' => 0.0,
                'stock' => 0,
            ]);

        $result = $this->productService->syncFromShopify();

        $this->assertEquals(1, $result['synced']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(1, $result['total']);
    }

    #[Test]
    public function it_handles_products_with_missing_title()
    {
        $this->mockShopifyService
            ->shouldReceive('fetchProducts')
            ->once()
            ->andReturn([
                [
                    'id' => '999888777',
                    'variants' => [
                        ['price' => '99.99', 'inventory_quantity' => 10],
                    ],
                ],
            ]);

        $this->mockProductRepository
            ->shouldReceive('upsert')
            ->once()
            ->with([
                'shopify_id' => '999888777',
                'title' => 'Untitled Product',
                'price' => 99.99,
                'stock' => 10,
            ]);

        $result = $this->productService->syncFromShopify();

        $this->assertEquals(1, $result['synced']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(1, $result['total']);
    }

    #[Test]
    public function it_skips_products_without_id()
    {
        $this->mockShopifyService
            ->shouldReceive('fetchProducts')
            ->once()
            ->andReturn([
                [
                    'title' => 'Product Without ID',
                    'variants' => [['price' => '99.99', 'inventory_quantity' => 10]],
                ],
            ]);

        $this->mockProductRepository
            ->shouldNotReceive('upsert');

        $result = $this->productService->syncFromShopify();

        $this->assertEquals(0, $result['synced']);
        $this->assertEquals(1, $result['skipped']);
        $this->assertEquals(1, $result['total']);
    }

    #[Test]
    public function it_handles_mixed_valid_and_invalid_products()
    {
        $this->mockShopifyService
            ->shouldReceive('fetchProducts')
            ->once()
            ->andReturn([
                [
                    'id' => '111111111',
                    'title' => 'Valid Product 1',
                    'variants' => [['price' => '10.00', 'inventory_quantity' => 5]],
                ],
                ['title' => 'Invalid Product - No ID'],
                [
                    'id' => '222222222',
                    'title' => 'Valid Product 2',
                    'variants' => [['price' => '20.00', 'inventory_quantity' => 10]],
                ],
                ['title' => 'Another Invalid Product'],
                [
                    'id' => '333333333',
                    'title' => 'Valid Product 3',
                ],
            ]);

        $this->mockProductRepository
            ->shouldReceive('upsert')
            ->once()
            ->with([
                'shopify_id' => '111111111',
                'title' => 'Valid Product 1',
                'price' => 10.0,
                'stock' => 5,
            ]);

        $this->mockProductRepository
            ->shouldReceive('upsert')
            ->once()
            ->with([
                'shopify_id' => '222222222',
                'title' => 'Valid Product 2',
                'price' => 20.0,
                'stock' => 10,
            ]);

        $this->mockProductRepository
            ->shouldReceive('upsert')
            ->once()
            ->with([
                'shopify_id' => '333333333',
                'title' => 'Valid Product 3',
                'price' => 0.0,
                'stock' => 0,
            ]);

        $result = $this->productService->syncFromShopify();

        $this->assertEquals(3, $result['synced']);
        $this->assertEquals(2, $result['skipped']);
        $this->assertEquals(5, $result['total']);
    }
}
