<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Traits\ApiResponse;
use Modules\Product\Services\ProductService;
use Modules\Product\Transformers\ProductListResource;
use Modules\Product\Transformers\ProductResource;

class NewProductController
{
    use ApiResponse;

    public function __construct(private ProductService $productService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'category', 'brand', 'min_price', 'max_price',
            'rating', 'search', 'sort', 'per_page',
            'featured', 'new', 'in_stock', 'cursor', 'pagination',
        ]);

        $products = $this->productService->getProducts($filters);

        return $this->paginatedResponse(ProductListResource::collection($products));
    }

    public function show(string $slug): JsonResponse
    {
        $product = $this->productService->getBySlug($slug);

        return $this->successResponse(new ProductResource($product));
    }

    public function featured(Request $request): JsonResponse
    {
        $products = $this->productService->getFeatured(min((int) ($request->limit ?? 10), 50));

        return $this->successResponse(ProductListResource::collection($products));
    }

    public function newArrivals(Request $request): JsonResponse
    {
        $products = $this->productService->getNew(min((int) ($request->limit ?? 10), 50));

        return $this->successResponse(ProductListResource::collection($products));
    }

    public function bestsellers(Request $request): JsonResponse
    {
        $products = $this->productService->getBestsellers(min((int) ($request->limit ?? 10), 50));

        return $this->successResponse(ProductListResource::collection($products));
    }

    public function related(string $slug, Request $request): JsonResponse
    {
        $product = $this->productService->getBySlug($slug);
        $related = $this->productService->getRelated($product, min((int) ($request->limit ?? 8), 24));

        return $this->successResponse(ProductListResource::collection($related));
    }
}
