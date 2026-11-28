<?php

namespace Modules\Vendor\Http\Controllers;

use App\Models\User;
use Modules\Catalog\Models\Brand;
use Modules\Catalog\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Traits\ApiResponse;
use Modules\Notifications\Notifications\ProductSubmittedNotification;
use Modules\Product\Emails\NewProductAdminAlertMail;
use Modules\Product\Emails\VendorProductUpdatedAdminMail;
use Modules\Product\Models\Product;
use Modules\Product\Models\VendorProductPrice;
use Modules\Product\Services\ProductService;
use Modules\Product\Transformers\ProductListResource;
use Modules\Product\Transformers\ProductResource;
use Modules\Vendor\Models\Vendor;

class VendorProductController
{
    use ApiResponse;

    private const VENDOR_PRICING_FIELDS = [
        'price',
        'sale_price',
        'stock_quantity',
        'low_stock_threshold',
        'manage_stock',
        'stock_status',
        'is_active',
    ];

    public function __construct(private ProductService $productService) {}

    /**
     * GET /api/v1/vendor/products
     * List products this vendor owns or sells via vendor_product_prices.
     */
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendor;

        $linkedProductIds = VendorProductPrice::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->pluck('product_id');

        $products = Product::select(Product::LIST_COLUMNS)
            ->with([
                'category:id,uuid,name,slug',
                'brand:id,uuid,name,slug',
                'vendor:id,uuid,shop_name,slug',
                'primaryImage:id,product_id,image_path,is_primary',
            ])
            ->where(function ($q) use ($vendor, $linkedProductIds) {
                $q->where('vendor_id', $vendor->id)
                    ->when($linkedProductIds->isNotEmpty(), fn($sq) => $sq->orWhereIn('id', $linkedProductIds));
            })
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('sku', 'like', "%{$request->search}%");
            }))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->category_uuid, fn($q) => $q->where('category_id', Category::findByUuid($request->category_uuid)?->id))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        // Attach vendor-specific pricing to each product
        $products->load(['vendorProductPrices' => fn($q) => $q->where('vendor_id', $vendor->id)]);

        return $this->paginatedResponse(
            $products->through(fn($product) => array_merge(
                (new ProductListResource($product))->toArray($request),
                [
                    'is_owner'     => $product->isOwnedByVendor($vendor->id),
                    'vendor_price' => $product->vendorProductPrices->first()?->only([
                        'price', 'sale_price', 'stock_quantity', 'is_active',
                    ]),
                ]
            ))
        );
    }

    /**
     * POST /api/v1/vendor/products
     * Link a product to vendor with custom pricing, or create a new vendor-owned product.
     */
    public function store(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendor;

        $this->decodeJsonArrays($request);

        $validated = $request->validate(array_merge([
            'product_uuid'      => 'nullable|exists:products,uuid',
            'type'              => 'nullable|in:simple,variable',
            'name'              => 'required_without:product_uuid|string|max:255',
            'sku'               => 'nullable|string|max:100|unique:products,sku',
            'category_uuid'     => 'nullable|exists:categories,uuid',
            'brand_uuid'        => 'nullable|exists:brands,uuid',
            'short_description' => 'nullable|string',
            'description'       => 'nullable|string',
            'thumbnail'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'meta_title'        => 'nullable|string|max:255',
            'meta_description'  => 'nullable|string|max:500',
        ], $this->pricingRules($request), $this->variantRules(), $this->imageRules(), $this->videoRules()));

        $validated = $this->mapTaxonomyUuids($validated);

        // If linking to an existing product
        if (!empty($validated['product_uuid'])) {
            $product = Product::findByUuidOrFail($validated['product_uuid']);
            unset($validated['product_uuid']);

            if (!$product->isPlatformProduct() && !$product->isOwnedByVendor($vendor->id)) {
                return $this->errorResponse(
                    'You cannot link to another vendor\'s exclusive product.',
                    null,
                    403
                );
            }

            $vendorPrice = VendorProductPrice::updateOrCreate(
                ['vendor_id' => $vendor->id, 'product_id' => $product->id],
                [
                    'price'               => $validated['price'] ?? $product->price ?? $product->min_price ?? 0,
                    'sale_price'          => $validated['sale_price'] ?? null,
                    'stock_quantity'      => $validated['stock_quantity'] ?? $product->stock_quantity ?? $product->total_stock ?? 0,
                    'low_stock_threshold' => $validated['low_stock_threshold'] ?? 5,
                    'manage_stock'        => $validated['manage_stock'] ?? true,
                    'is_active'           => $validated['is_active'] ?? true,
                ]
            );

            $data = (new ProductResource($product->load(['vendor', 'vendorProductPrices' => fn($q) => $q->where('vendor_id', $vendor->id)])))
                ->toArray($request);
            $data['is_owner'] = $product->isOwnedByVendor($vendor->id);
            $data['vendor_pricing'] = $vendorPrice->only(self::VENDOR_PRICING_FIELDS);

            return $this->successResponse($data, 'Product linked to your shop.');
        }

        // Create a new vendor-owned product (initially inactive until admin review)
        $data = $validated;
        $data['type'] = $data['type'] ?? 'simple';
        $data['vendor_id'] = $vendor->id;
        $data['status'] = 'inactive';

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $this->productService->uploadThumbnail($request->file('thumbnail'));
        }

        $product = $this->productService->createProduct($data);

        // Link to vendor pricing table
        $vendorPrice = VendorProductPrice::updateOrCreate(
            [
                'vendor_id'  => $vendor->id,
                'product_id' => $product->id,
            ],
            [
                'price'               => $data['price'] ?? $product->price ?? $product->min_price ?? 0,
                'sale_price'          => $data['sale_price'] ?? null,
                'stock_quantity'      => $data['stock_quantity'] ?? $product->stock_quantity ?? $product->total_stock ?? 0,
                'low_stock_threshold' => $data['low_stock_threshold'] ?? 5,
                'manage_stock'        => $data['manage_stock'] ?? true,
                'is_active'           => $data['is_active'] ?? true,
            ]
        );

        $this->notifyAdminsOfSubmittedProduct($product, $vendor, $request->user());

        $responseData = (new ProductResource($product->load(['vendor', 'vendorProductPrices'])))->toArray($request);
        $responseData['is_owner'] = true;
        $responseData['vendor_pricing'] = $vendorPrice->only(self::VENDOR_PRICING_FIELDS);

        return $this->createdResponse($responseData, 'Product created successfully and submitted for review.');
    }

    /**
     * GET /api/v1/vendor/products/{product}
     * Show product details with vendor pricing.
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        $vendor = $request->user()->vendor;
        $isOwner = $product->isOwnedByVendor($vendor->id);

        $vendorPrice = VendorProductPrice::where('vendor_id', $vendor->id)
            ->where('product_id', $product->id)
            ->first();

        if (!$isOwner && !$vendorPrice) {
            return $this->errorResponse('Product not found in your shop.', null, 404);
        }

        $product->load(['images', 'videos', 'variants', 'attributes.values', 'category', 'brand', 'vendor']);

        $data = (new ProductResource($product))->toArray($request);
        $data['is_owner'] = $isOwner;
        $data['vendor_pricing'] = $vendorPrice?->only(self::VENDOR_PRICING_FIELDS);

        return $this->successResponse($data, 'Product details retrieved.');
    }

    /**
     * POST /api/v1/vendor/products/{product}?_method=PUT
     * Update owned product details & pricing, or update pricing-only for linked platform products.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $vendor = $request->user()->vendor;
        $isOwner = $product->isOwnedByVendor($vendor->id);

        $vendorPrice = VendorProductPrice::where('vendor_id', $vendor->id)
            ->where('product_id', $product->id)
            ->first();

        if (!$isOwner && !$vendorPrice) {
            return $this->errorResponse('You do not have permission to update this product.', null, 403);
        }

        // Owning vendor can edit full product details + pricing
        if ($isOwner) {
            $this->decodeJsonArrays($request);

            $validated = $request->validate(array_merge([
                'name'              => 'sometimes|string|max:255',
                'sku'               => "nullable|string|max:100|unique:products,sku,{$product->id}",
                'category_uuid'     => 'nullable|exists:categories,uuid',
                'brand_uuid'        => 'nullable|exists:brands,uuid',
                'short_description' => 'nullable|string',
                'description'       => 'nullable|string',
                'thumbnail'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'meta_title'        => 'nullable|string|max:255',
                'meta_description'  => 'nullable|string|max:500',
            ], $this->pricingRules($request), $this->variantRules(false), $this->imageRules(), $this->videoRules()));

            $data = $this->mapTaxonomyUuids($validated);

            if ($request->hasFile('thumbnail')) {
                $data['thumbnail'] = $this->productService->uploadThumbnail($request->file('thumbnail'));
            }

            // Option 1 (Daraz-style Smart Re-Approval): price/stock changes apply immediately,
            // while content, media, taxonomy, or variant edits on an active product require admin review.
            $wasActive = $product->status === 'active';
            $requiresReapproval = $wasActive && $this->hasContentChangesRequiringApproval($product, $data, $request);

            if ($requiresReapproval) {
                $data['status'] = 'inactive';
            }

            $product = $this->productService->updateProduct($product, $data);

            $pricingPayload = array_intersect_key($validated, array_flip(self::VENDOR_PRICING_FIELDS));
            $vendorPrice = VendorProductPrice::updateOrCreate(
                ['vendor_id' => $vendor->id, 'product_id' => $product->id],
                array_merge([
                    'price'               => $product->price ?? $product->min_price ?? 0,
                    'stock_quantity'      => $product->total_stock ?? $product->stock_quantity ?? 0,
                    'low_stock_threshold' => $product->low_stock_threshold ?? 5,
                    'manage_stock'        => $product->manage_stock ?? true,
                    'is_active'           => $vendorPrice?->is_active ?? true,
                ], $pricingPayload)
            );

            $this->notifyAdminsOfUpdatedProduct($product, $vendor, $request->user(), $requiresReapproval);

            $responseData = (new ProductResource($product))->toArray($request);
            $responseData['is_owner'] = true;
            $responseData['reapproval_required'] = $requiresReapproval;
            $responseData['vendor_pricing'] = $vendorPrice->fresh()->only(self::VENDOR_PRICING_FIELDS);

            $message = $requiresReapproval
                ? 'Product updated and submitted for admin re-approval.'
                : 'Product updated successfully.';

            return $this->successResponse($responseData, $message);
        }

        // Linked platform product: vendor may only update their pricing & stock
        $validated = $request->validate($this->pricingRules($request));

        $vendorPrice->update($validated);

        return $this->successResponse([
            'is_owner'       => false,
            'vendor_pricing' => $vendorPrice->fresh()->only(self::VENDOR_PRICING_FIELDS),
        ], 'Product pricing updated.');
    }

    /**
     * DELETE /api/v1/vendor/products/{product}
     * Delete a vendor-owned product or unlink a platform product from vendor's shop.
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        $vendor = $request->user()->vendor;
        $isOwner = $product->isOwnedByVendor($vendor->id);

        $vendorPrice = VendorProductPrice::where('vendor_id', $vendor->id)
            ->where('product_id', $product->id)
            ->first();

        if (!$isOwner && !$vendorPrice) {
            return $this->errorResponse('You do not have permission to delete this product.', null, 403);
        }

        if ($vendorPrice) {
            $vendorPrice->update(['is_active' => false]);
        }

        if ($isOwner) {
            $this->productService->deleteProduct($product);
            return $this->successResponse(null, 'Product deleted successfully.');
        }

        return $this->successResponse(null, 'Product removed from your shop.');
    }

    /**
     * POST /api/v1/vendor/products/upload-image
     * Upload a gallery image or thumbnail for a vendor product.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $payload = $this->productService->handleImageUpload($request);

        return $this->successResponse($payload, 'Image uploaded.');
    }

    /**
     * POST /api/v1/vendor/products/upload-video
     * Upload a product video file and optional poster image for a vendor product.
     */
    public function uploadVideo(Request $request): JsonResponse
    {
        $payload = $this->productService->handleVideoUpload($request);

        return $this->successResponse($payload, 'Video uploaded.');
    }

    private function decodeJsonArrays(Request $request): void
    {
        $decoded = [];
        foreach (['images', 'videos', 'attributes', 'variants', 'tags'] as $field) {
            if ($request->has($field) && is_string($request->input($field))) {
                $parsed = json_decode($request->input($field), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $decoded[$field] = $parsed;
                }
            }
        }

        if (!empty($decoded)) {
            $request->merge($decoded);
        }
    }

    private function variantRules(bool $requireOnVariable = true): array
    {
        $attrRule = $requireOnVariable ? 'required_if:type,variable|array|min:1' : 'nullable|array';
        $variantRule = $requireOnVariable ? 'required_if:type,variable|array|min:1' : 'nullable|array';

        return [
            'attributes'                  => $attrRule,
            'attributes.*.name'           => 'required_with:attributes|string|max:100',
            'attributes.*.values'         => 'required_with:attributes|array|min:1',
            'attributes.*.values.*.value' => 'required|string|max:100',
            'variants'                    => $variantRule,
            'variants.*.id'               => 'nullable|integer',
            'variants.*.attributes'       => 'required_with:variants|array',
            'variants.*.price'            => 'required_with:variants|numeric|min:0',
            'variants.*.sale_price'       => 'nullable|numeric|min:0',
            'variants.*.stock_quantity'   => 'nullable|integer|min:0',
            'variants.*.sku'              => 'nullable|string|max:100',
        ];
    }

    private function notifyAdminsOfSubmittedProduct(
        Product $product,
        Vendor $vendor,
        ?User $submittedByUser
    ): void {
        $submittedBy = $vendor->shop_name ?? $submittedByUser?->name ?? 'Vendor';

        $this->dispatchAdminAlert(
            $product,
            $vendor,
            new ProductSubmittedNotification($product, $submittedBy, false),
            new NewProductAdminAlertMail($product, $vendor)
        );
    }

    private function notifyAdminsOfUpdatedProduct(
        Product $product,
        Vendor $vendor,
        ?User $updatedByUser,
        bool $requiresReapproval
    ): void {
        $submittedBy = $vendor->shop_name ?? $updatedByUser?->name ?? 'Vendor';

        $notification = $requiresReapproval
            ? new ProductSubmittedNotification($product, $submittedBy, true)
            : null;

        $this->dispatchAdminAlert(
            $product,
            $vendor,
            $notification,
            new VendorProductUpdatedAdminMail($product, $vendor, $requiresReapproval)
        );
    }

    private function dispatchAdminAlert(
        Product $product,
        Vendor $vendor,
        ?object $inAppNotification,
        \Illuminate\Mail\Mailable $mailable
    ): void {
        try {
            $admins = User::with('roles')
                ->role(['super-admin', 'admin', 'moderator'])
                ->where('status', 'active')
                ->get();

            if ($inAppNotification && $admins->isNotEmpty()) {
                Notification::send($admins, $inAppNotification);
            }

            $adminEmails = $admins
                ->filter(fn(User $admin) => $admin->hasAnyRole(['super-admin', 'admin']) && !empty($admin->email))
                ->pluck('email')
                ->unique()
                ->values();

            if ($adminEmails->isEmpty() && config('ecommerce.admin_email')) {
                $adminEmails = collect([config('ecommerce.admin_email')]);
            }

            if ($adminEmails->isNotEmpty()) {
                Mail::to($adminEmails->all())->later(now()->addSeconds(3), $mailable);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to dispatch admin product alert: ' . $e->getMessage(), [
                'product_id' => $product->id,
                'vendor_id'  => $vendor->id,
                'mailable'   => $mailable::class,
            ]);
        }
    }

    /**
     * Detect Tier-2 catalog content/media/variant edits that require admin re-approval.
     * Price, sale_price, stock_quantity, low_stock_threshold, manage_stock, and is_active bypass review.
     */
    private function hasContentChangesRequiringApproval(Product $product, array $data, Request $request): bool
    {
        if ($request->hasFile('thumbnail')) {
            return true;
        }

        foreach (['name', 'sku', 'category_id', 'brand_id', 'short_description', 'description', 'meta_title', 'meta_description'] as $field) {
            if (array_key_exists($field, $data) && (string) ($product->{$field} ?? '') !== (string) ($data[$field] ?? '')) {
                return true;
            }
        }

        if (array_key_exists('images', $data)) {
            $currentImages = $product->images()->orderBy('sort_order')->get()->map(fn($img) => [
                'path'       => (string) ($img->image_path ?? ''),
                'alt_text'   => (string) ($img->alt_text ?? ''),
                'is_primary' => (bool) $img->is_primary,
            ])->values()->all();

            $incomingImages = collect($data['images'] ?? [])->map(fn($img) => [
                'path'       => (string) ($img['path'] ?? ''),
                'alt_text'   => (string) ($img['alt_text'] ?? ''),
                'is_primary' => (bool) ($img['is_primary'] ?? false),
            ])->values()->all();

            if ($currentImages !== $incomingImages) {
                return true;
            }
        }

        if (array_key_exists('videos', $data)) {
            $currentVideos = $product->videos()->orderBy('sort_order')->get()->map(fn($v) => [
                'source_type'  => $v->source_type,
                'title'        => (string) ($v->title ?? ''),
                'video_path'   => (string) ($v->video_path ?? ''),
                'external_url' => (string) ($v->external_url ?? ''),
                'is_primary'   => (bool) $v->is_primary,
            ])->values()->all();

            $incomingVideos = collect($data['videos'] ?? [])->map(fn($v) => [
                'source_type'  => $v['source_type'] ?? 'upload',
                'title'        => (string) ($v['title'] ?? ''),
                'video_path'   => (string) ($v['video_path'] ?? ''),
                'external_url' => (string) ($v['external_url'] ?? ''),
                'is_primary'   => (bool) ($v['is_primary'] ?? false),
            ])->values()->all();

            if ($currentVideos !== $incomingVideos) {
                return true;
            }
        }

        if ($product->type === 'variable') {
            if (array_key_exists('attributes', $data)) {
                $currentAttrs = $product->attributes()->with('values')->orderBy('sort_order')->get()->map(fn($a) => [
                    'name'   => $a->name,
                    'values' => $a->values->pluck('value')->values()->all(),
                ])->values()->all();

                $incomingAttrs = collect($data['attributes'] ?? [])->map(fn($a) => [
                    'name'   => $a['name'] ?? '',
                    'values' => collect($a['values'] ?? [])->pluck('value')->values()->all(),
                ])->values()->all();

                if ($currentAttrs !== $incomingAttrs) {
                    return true;
                }
            }

            if (array_key_exists('variants', $data)) {
                $normalizeAttrs = function (array $attrs): array {
                    ksort($attrs);
                    return $attrs;
                };

                $currentVariants = $product->variants()->orderBy('sort_order')->get()->map(fn($v) => [
                    'attributes'     => $normalizeAttrs($v->attributes ?? []),
                    'price'          => (float) $v->price,
                    'sale_price'     => is_null($v->sale_price) ? null : (float) $v->sale_price,
                    'stock_quantity' => (int) $v->stock_quantity,
                    'sku'            => (string) ($v->sku ?? ''),
                ])->values()->all();

                $incomingVariants = collect($data['variants'] ?? [])->map(fn($v) => [
                    'attributes'     => $normalizeAttrs($v['attributes'] ?? []),
                    'price'          => (float) ($v['price'] ?? 0),
                    'sale_price'     => (!isset($v['sale_price']) || $v['sale_price'] === '') ? null : (float) $v['sale_price'],
                    'stock_quantity' => (int) ($v['stock_quantity'] ?? 0),
                    'sku'            => (string) ($v['sku'] ?? ''),
                ])->values()->all();

                if ($currentVariants !== $incomingVariants) {
                    return true;
                }
            }
        }

        return false;
    }

    private function pricingRules(Request $request): array
    {
        return [
            'price'               => 'nullable|numeric|min:0',
            'sale_price'          => $request->filled('price')
                ? 'nullable|numeric|min:0|lte:price'
                : 'nullable|numeric|min:0',
            'stock_quantity'      => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'manage_stock'        => 'nullable|boolean',
            'is_active'           => 'nullable|boolean',
        ];
    }

    private function imageRules(): array
    {
        return [
            'images'              => 'nullable|array',
            'images.*.id'         => 'nullable|integer',
            'images.*.path'       => 'nullable|string|max:500',
            'images.*.is_primary' => 'boolean',
            'images.*.alt_text'   => 'nullable|string|max:255',
            'images.*.sort_order' => 'nullable|integer|min:0',
        ];
    }

    private function videoRules(): array
    {
        return [
            'videos'                => 'nullable|array',
            'videos.*.source_type'  => 'nullable|in:upload,youtube,vimeo,external',
            'videos.*.title'        => 'nullable|string|max:255',
            'videos.*.video_path'   => 'nullable|string|max:500',
            'videos.*.external_url' => 'nullable|url|max:1000',
            'videos.*.poster_path'  => 'nullable|string|max:500',
            'videos.*.mime_type'    => 'nullable|string|max:100',
            'videos.*.file_size'    => 'nullable|integer|min:0',
            'videos.*.is_primary'   => 'boolean',
            'videos.*.sort_order'   => 'nullable|integer|min:0',
        ];
    }

    private function mapTaxonomyUuids(array $validated): array
    {
        if (array_key_exists('category_uuid', $validated)) {
            $validated['category_id'] = !empty($validated['category_uuid'])
                ? Category::findByUuidOrFail($validated['category_uuid'])->id
                : null;
            unset($validated['category_uuid']);
        }

        if (array_key_exists('brand_uuid', $validated)) {
            $validated['brand_id'] = !empty($validated['brand_uuid'])
                ? Brand::findByUuidOrFail($validated['brand_uuid'])->id
                : null;
            unset($validated['brand_uuid']);
        }

        return $validated;
    }
}
