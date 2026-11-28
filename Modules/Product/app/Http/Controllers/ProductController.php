<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Traits\ApiResponse;
use Modules\Product\Http\Requests\StoreProductRequest;
use Modules\Product\Http\Requests\UpdateProductRequest;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductService;
use Modules\Product\Transformers\ProductListResource;
use Modules\Product\Transformers\ProductResource;
use Modules\Vendor\Models\Vendor;

class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(private ProductService $productService) {}

    public function index(Request $request): JsonResponse
    {
        $products = Product::select(Product::LIST_COLUMNS)
            ->with([
                'category:id,uuid,name,slug',
                'brand:id,uuid,name,slug',
                'vendor:id,uuid,shop_name,slug',
                'primaryImage:id,product_id,image_path,is_primary',
            ])
            ->when($request->search, function ($q) use ($request) {
                $term = trim((string) $request->search);
                $q->where(fn($sq) =>
                    $sq->where('name', 'like', "%{$term}%")
                        ->orWhere('sku', 'like', "{$term}%"));
            })
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->owner_type === 'platform', fn($q) => $q->whereNull('vendor_id'))
            ->when($request->owner_type === 'vendor', fn($q) => $q->whereNotNull('vendor_id'))
            ->when($request->vendor_uuid, function ($q) use ($request) {
                $vendorId = Vendor::where('uuid', $request->vendor_uuid)->value('id');
                $vendorId ? $q->where('vendor_id', $vendorId) : $q->whereRaw('1 = 0');
            })
            ->orderByDesc('created_at')
            ->paginate(min((int) ($request->per_page ?? 20), 100));

        return $this->paginatedResponse(
            ProductListResource::collection($products)
        );
    }

    /**
     * Store product form admin dashboard
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $this->productService->uploadThumbnail($request->file('thumbnail'));
        }

        $product = $this->productService->createProduct($data);

        return $this->createdResponse(new ProductResource($product), 'Product created successfully.');
    }

    public function show(Product $product): JsonResponse
    {
        $product->load(['images', 'videos', 'variants', 'attributes.values', 'category', 'brand', 'vendor', 'creator', 'updater']);
        return $this->successResponse(new ProductResource($product));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $previousStatus = $product->status;
        $data = $request->validated();

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $this->productService->uploadThumbnail($request->file('thumbnail'));
        }

        if (($data['status'] ?? null) === 'active' && is_null($product->published_at)) {
            $data['published_at'] = now();
        }

        $product = $this->productService->updateProduct($product, $data);

        if (isset($data['status']) && $data['status'] !== $previousStatus) {
            $this->productService->notifyVendorStatusChange(
                $product,
                $data['status'],
                $request->input('reason')
            );
        }

        return $this->successResponse(new ProductResource($product), 'Product updated successfully.');
    }

    /**
     * PATCH /api/v1/admin/products/{product}/status
     * Quickly update a product's publish status from the admin products table.
     */
    public function updateStatus(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:active,inactive,draft,pending',
            'reason' => 'nullable|string|max:500',
        ]);

        $product = $this->productService->updateStatus(
            $product,
            $validated['status'],
            $validated['reason'] ?? null
        );

        return $this->successResponse(
            new ProductResource($product),
            "Product status updated to {$validated['status']}."
        );
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->productService->deleteProduct($product);
        return $this->noContentResponse('Product deleted.');
    }

    /**
     * POST /api/v1/admin/products/upload-image
     * Handles both gallery images AND thumbnail uploads
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $payload = $this->productService->handleImageUpload($request);

        return $this->successResponse($payload, 'Image uploaded.');
    }

    /**
     * POST /api/v1/admin/products/upload-video
     * Upload a product video file and optional poster image.
     */
    public function uploadVideo(Request $request): JsonResponse
    {
        $payload = $this->productService->handleVideoUpload($request);

        return $this->successResponse($payload, 'Video uploaded.');
    }
}
