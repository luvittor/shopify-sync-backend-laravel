<?php

namespace App\Http\Controllers;

use App\Repositories\ProductRepository;
use App\Services\ShopifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    private ProductRepository $productRepository;
    private ShopifyService $shopifyService;

    public function __construct(ProductRepository $productRepository, ShopifyService $shopifyService)
    {
        $this->productRepository = $productRepository;
        $this->shopifyService = $shopifyService;
    }

    /**
     * List all products
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $products = $this->productRepository->list();
            
            return response()->json([
                'products' => $products,
                'count' => $products->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch products',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync products from Shopify
     *
     * @return JsonResponse
     */
    public function sync(): JsonResponse
    {
        try {
            $result = $this->shopifyService->sync();
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to sync products',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear all products
     *
     * @return JsonResponse
     */
    public function clear(): JsonResponse
    {
        try {
            $count = $this->productRepository->clear();
            
            return response()->json([
                'message' => 'All products cleared successfully',
                'cleared' => $count
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to clear products',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}