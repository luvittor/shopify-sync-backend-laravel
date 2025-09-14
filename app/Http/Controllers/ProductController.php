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
     * List all products with pagination
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get the per_page parameter from the request, default to 10
            $perPage = (int) $request->get('per_page', 10);
            
            // Validate per_page parameter (between 1 and 100)
            if ($perPage < 1) {
                $perPage = 10;
            } elseif ($perPage > 100) {
                $perPage = 100;
            }
            
            $products = $this->productRepository->listPaginated($perPage);
            
            return response()->json([
                'data' => $products->items(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'last_page' => $products->lastPage(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                    'has_more_pages' => $products->hasMorePages(),
                    'prev_page_url' => $products->previousPageUrl(),
                    'next_page_url' => $products->nextPageUrl(),
                ],
                'links' => [
                    'first' => $products->url(1),
                    'last' => $products->url($products->lastPage()),
                    'prev' => $products->previousPageUrl(),
                    'next' => $products->nextPageUrl(),
                ]
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