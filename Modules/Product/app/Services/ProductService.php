<?php

namespace Modules\Product\Services;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Modules\Catalog\Models\Brand;
use Modules\Catalog\Models\Category;
use Modules\Notifications\Notifications\ProductStatusNotification;
use Modules\Product\Emails\ProductStatusVendorMail;
use Modules\Product\Jobs\ProcessProductVideoJob;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductAttribute;
use Modules\Product\Models\ProductAttributeValue;
use Modules\Product\Models\ProductImage;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\ProductVideo;
use Modules\Product\Models\VendorProductPrice;
use Modules\Vendor\Models\Vendor;

class ProductService
{
    public function getProducts(array $filters)
    {
        $version = (int) Cache::get('products_cache_version', 1);
        $cacheKey = "products_v{$version}_" . md5(serialize($filters));

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($filters) {
            $query = Product::active()
                ->select(Product::LIST_COLUMNS)
                ->with(['category:id,uuid,name,slug', 'brand:id,uuid,name,slug', 'vendor:id,uuid,shop_name,slug', 'primaryImage:id,product_id,image_path,is_primary'])
                ->when($filters['category'] ?? null, function ($q, $slug) {
                    $categoryId = Category::where('slug', $slug)->value('id');
                    return $categoryId ? $q->where('category_id', $categoryId) : $q->whereRaw('1 = 0');
                })
                ->when($filters['brand'] ?? null, function ($q, $slug) {
                    $brandId = Brand::where('slug', $slug)->value('id');
                    return $brandId ? $q->where('brand_id', $brandId) : $q->whereRaw('1 = 0');
                })
                ->when($filters['min_price'] ?? null, fn($q, $min) => $q->where('effective_price', '>=', (float) $min))
                ->when($filters['max_price'] ?? null, fn($q, $max) => $q->where('effective_price', '<=', (float) $max))
                ->when($filters['rating'] ?? null, fn($q, $rating) => $q->where('average_rating', '>=', (float) $rating))
                ->when(!empty($filters['featured']), fn($q) => $q->where('is_featured', true))
                ->when(!empty($filters['new']), fn($q) => $q->where('is_new', true))
                ->when(!empty($filters['in_stock']), fn($q) => $q->inStock())
                ->when($filters['search'] ?? null, function ($q, $term) {
                    $term = trim((string) $term);
                    if ($term === '') return;
                    if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) && mb_strlen($term) >= 3) {
                        $q->where(fn($sq) => $sq->whereRaw('MATCH(name, short_description) AGAINST(? IN BOOLEAN MODE)', [$term . '*'])
                            ->orWhere('sku', 'like', "{$term}%"));
                    } else {
                        $q->where(fn($sq) => $sq->where('name', 'like', "%{$term}%")
                            ->orWhere('sku', 'like', "{$term}%"));
                    }
                });

            match ($filters['sort'] ?? 'newest') {
                'price_asc'  => $query->orderBy('effective_price')->orderBy('id'),
                'price_desc' => $query->orderByDesc('effective_price')->orderByDesc('id'),
                'popular'    => $query->orderByDesc('total_sold')->orderByDesc('id'),
                'rating'     => $query->orderByDesc('average_rating')->orderByDesc('id'),
                default      => $query->orderByDesc('created_at')->orderByDesc('id'),
            };

            $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

            return !empty($filters['cursor']) || ($filters['pagination'] ?? null) === 'cursor'
                ? $query->cursorPaginate($perPage)
                : $query->paginate($perPage);
        });
    }

    public function getBySlug(string $slug): Product
    {
        return Cache::remember("product_{$slug}", now()->addMinutes(30), function () use ($slug) {
            return Product::active()
                ->with(['images', 'videos', 'variants', 'attributes.values', 'category', 'brand', 'vendor:id,uuid,shop_name,slug'])
                ->where('slug', $slug)
                ->firstOrFail();
        });
    }

    public function getFeatured(int $limit = 10)
    {
        $version = (int) Cache::get('products_cache_version', 1);
        return Cache::remember("products_v{$version}_featured_{$limit}", now()->addMinutes(30), function () use ($limit) {
            return Product::select(Product::LIST_COLUMNS)
                ->with(['primaryImage:id,product_id,image_path,is_primary', 'category:id,uuid,name,slug', 'brand:id,uuid,name,slug'])
                ->featured()
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        });
    }

    public function getNew(int $limit = 10)
    {
        $version = (int) Cache::get('products_cache_version', 1);
        return Cache::remember("products_v{$version}_new_{$limit}", now()->addMinutes(30), function () use ($limit) {
            return Product::select(Product::LIST_COLUMNS)
                ->with(['primaryImage:id,product_id,image_path,is_primary', 'category:id,uuid,name,slug', 'brand:id,uuid,name,slug'])
                ->new()
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        });
    }

    public function getBestsellers(int $limit = 10)
    {
        $version = (int) Cache::get('products_cache_version', 1);
        return Cache::remember("products_v{$version}_bestsellers_{$limit}", now()->addMinutes(30), function () use ($limit) {
            return Product::select(Product::LIST_COLUMNS)
                ->with(['primaryImage:id,product_id,image_path,is_primary', 'category:id,uuid,name,slug', 'brand:id,uuid,name,slug'])
                ->bestseller()
                ->orderByDesc('total_sold')
                ->limit($limit)
                ->get();
        });
    }

    public function getRelated(Product $product, int $limit = 8)
    {
        if (!$product->category_id) {
            return collect();
        }

        $version = (int) Cache::get('products_cache_version', 1);
        $candidateIds = Cache::remember(
            "products_v{$version}_cat_pool_{$product->category_id}",
            now()->addMinutes(10),
            fn() => Product::active()
                ->where('category_id', $product->category_id)
                ->orderByDesc('id')
                ->limit(100)
                ->pluck('id')
                ->all()
        );

        $filteredIds = array_values(array_filter($candidateIds, fn($id) => (int) $id !== (int) $product->id));
        if (empty($filteredIds)) {
            return collect();
        }

        shuffle($filteredIds);
        $pickedIds = array_slice($filteredIds, 0, $limit);

        return Product::select(Product::LIST_COLUMNS)
            ->with(['primaryImage:id,product_id,image_path,is_primary', 'category:id,uuid,name,slug'])
            ->whereIn('id', $pickedIds)
            ->get();
    }

    /**
     * Create product
     */
    public function createProduct(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $data = $this->resolveVendorOwnership($data);

            if (empty($data['slug']) && !empty($data['name'])) {
                $data['slug'] = Product::generateUniqueSlug($data['name']);
            }

            $product = Product::create($data);

            // Handle images
            if (!empty($data['images'])) {
                $this->syncImages($product, $data['images']);
            }

            // Handle videos
            if (!empty($data['videos'])) {
                $this->syncVideos($product, $data['videos']);
            }

            // Handle attributes & variants for variable products
            if ($product->type === 'variable' && !empty($data['attributes'])) {
                $this->syncAttributes($product, $data['attributes']);
            }

            if ($product->type === 'variable' && !empty($data['variants'])) {
                $this->syncVariants($product, $data['variants']);
            }

            $this->syncOwnerVendorPrice($product);

            $this->clearProductCache();

            Log::channel('admin_actions')->info('Product created', [
                'product_id' => $product->id,
                'name'       => $product->name,
            ]);

            return $product->load(['images', 'videos', 'variants', 'attributes.values', 'category', 'brand', 'vendor', 'creator', 'updater']);
        });
    }

    public function updateProduct(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $data = $this->resolveVendorOwnership($data);

            // If name changed and slug is being reset
            if (isset($data['name']) && empty($data['slug'])) {
                $data['slug'] = Product::generateUniqueSlug($data['name'], $product->id);
            }

            $product->update($data);

            if (array_key_exists('images', $data)) {
                $this->syncImages($product, $data['images'] ?? []);
            }

            if (array_key_exists('videos', $data)) {
                $this->syncVideos($product, $data['videos'] ?? []);
            }

            if ($product->type === 'variable') {
                if (array_key_exists('attributes', $data)) {
                    $this->syncAttributes($product, $data['attributes'] ?? []);
                }
                if (array_key_exists('variants', $data)) {
                    $this->syncVariants($product, $data['variants'] ?? []);
                }
            }

            $this->syncOwnerVendorPrice($product);

            $this->clearProductCache($product->slug);

            return $product->fresh()->load(['images', 'videos', 'variants', 'attributes.values', 'category', 'brand', 'vendor', 'creator', 'updater']);
        });
    }

    private function resolveVendorOwnership(array $data): array
    {
        if (array_key_exists('vendor_uuid', $data)) {
            if (!array_key_exists('vendor_id', $data)) {
                $data['vendor_id'] = !empty($data['vendor_uuid'])
                    ? Vendor::findByUuidOrFail($data['vendor_uuid'])->id
                    : null;
            }
            unset($data['vendor_uuid']);
        }

        return $data;
    }

    /**
     * Ensure an admin-assigned vendor owner has a matching vendor_product_prices entry
     * so storefront & vendor shop queries stay consistent immediately on creation.
     */
    private function syncOwnerVendorPrice(Product $product): void
    {
        if (is_null($product->vendor_id)) {
            return;
        }

        $fresh = $product->fresh() ?? $product;

        VendorProductPrice::firstOrCreate(
            [
                'vendor_id'  => $fresh->vendor_id,
                'product_id' => $fresh->id,
            ],
            [
                'price'               => $fresh->price ?? $fresh->min_price ?? 0,
                'sale_price'          => $fresh->sale_price,
                'stock_quantity'      => $fresh->total_stock ?? $fresh->stock_quantity ?? 0,
                'low_stock_threshold' => $fresh->low_stock_threshold ?? 5,
                'manage_stock'        => $fresh->manage_stock ?? true,
                'is_active'           => true,
            ]
        );
    }

    /**
     * Update a product's publish status and notify owning vendor (in-app + email).
     */
    public function updateStatus(Product $product, string $status, ?string $reason = null): Product
    {
        $previousStatus = $product->status;

        $payload = ['status' => $status];
        if ($status === 'active' && is_null($product->published_at)) {
            $payload['published_at'] = now();
        }

        $product->update($payload);
        $this->clearProductCache($product->slug);

        $fresh = $product->fresh()->load(['images', 'videos', 'variants', 'attributes.values', 'category', 'brand', 'vendor', 'creator', 'updater']);

        if ($previousStatus !== $status) {
            $this->notifyVendorStatusChange($fresh, $status, $reason);
        }

        return $fresh;
    }

    /**
     * Send in-app + queued email notification to a vendor when their product status changes.
     */
    public function notifyVendorStatusChange(Product $product, string $status, ?string $reason = null): void
    {
        if (is_null($product->vendor_id)) {
            return;
        }

        $product->loadMissing(['vendor.user', 'category', 'brand']);
        $vendor = $product->vendor;
        if (!$vendor) {
            return;
        }

        $vendorUser = $vendor->user;

        try {
            if ($vendorUser) {
                $vendorUser->notify(new ProductStatusNotification($product, $status, $reason));
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to create vendor product status notification: ' . $e->getMessage(), [
                'product_id' => $product->id,
                'vendor_id'  => $vendor->id,
            ]);
        }

        try {
            $recipientEmail = $vendor->email ?: $vendorUser?->email;
            if ($recipientEmail) {
                Mail::to($recipientEmail)->later(
                    now()->addSeconds(3),
                    new ProductStatusVendorMail($product, $vendor, $status, $reason)
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to queue vendor product status email: ' . $e->getMessage(), [
                'product_id' => $product->id,
                'vendor_id'  => $vendor->id,
            ]);
        }
    }

    public function deleteProduct(Product $product): void
    {
        $this->clearProductCache($product->slug);
        $product->delete();
        Log::channel('admin_actions')->info('Product deleted', ['product_id' => $product->id]);
    }

    public function uploadThumbnail($file): string
    {
        return $file->store('products/thumbnails', 'public');
    }

    public function uploadImage($file): string
    {
        return $file->store('products/images', 'public');
    }

    /**
     * Handle multipart image or thumbnail upload for admin and vendor controllers.
     */
    public function handleImageUpload(\Illuminate\Http\Request $request): array
    {
        $request->validate([
            'image'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('thumbnail')) {
            $path = $this->uploadThumbnail($request->file('thumbnail'));
            return [
                'path' => $path,
                'url'  => asset('storage/' . $path),
            ];
        }

        if ($request->hasFile('image')) {
            $path = $this->uploadImage($request->file('image'));
            return [
                'path' => $path,
                'url'  => asset('storage/' . $path),
            ];
        }

        abort(422, 'No file provided.');
    }

    public function uploadVideo($file): string
    {
        return $file->store('products/videos', 'public');
    }

    public function uploadVideoPoster($file): string
    {
        return $file->store('products/video-posters', 'public');
    }

    public function handleVideoUpload(\Illuminate\Http\Request $request): array
    {
        $request->validate([
            'video'  => 'nullable|file|mimetypes:video/mp4,video/webm,video/quicktime,video/x-m4v|max:102400',
            'poster' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if (!$request->hasFile('video') && !$request->hasFile('poster')) {
            abort(422, 'No video or poster file provided.');
        }

        $result = [];

        if ($request->hasFile('video')) {
            $file = $request->file('video');
            $path = $this->uploadVideo($file);
            $result['path'] = $path;
            $result['url'] = asset('storage/' . $path);
            $result['mime_type'] = $file->getClientMimeType();
            $result['file_size'] = $file->getSize();
        }

        if ($request->hasFile('poster')) {
            $posterPath = $this->uploadVideoPoster($request->file('poster'));
            $result['poster_path'] = $posterPath;
            $result['poster_url'] = asset('storage/' . $posterPath);
        }

        return $result;
    }

    // ─── Private Helpers ──────────────────────────────────────────

    // private function syncImages(Product $product, array $images): void
    // {
    //     // Delete removed images
    //     // $keepIds = collect($images)->pluck('id')->filter()->toArray();

    //     $keepIds = collect($images)
    //         ->pluck('id')
    //         ->filter()
    //         ->values()
    //         ->toArray();

    //     $product->images()->whereNotIn('id', $keepIds)->each(function ($img) {
    //         Storage::disk('public')->delete($img->image_path);
    //         $img->delete();
    //     });

    //     foreach ($images as $index => $imageData) {
    //         if (isset($imageData['id'])) {
    //             ProductImage::where('id', $imageData['id'])->update([
    //                 'is_primary'  => $imageData['is_primary'] ?? false,
    //                 'sort_order'  => $index,
    //                 'alt_text'    => $imageData['alt_text'] ?? null,
    //             ]);
    //         } else {
    //             ProductImage::create([
    //                 'product_id'  => $product->id,
    //                 'image_path'  => $imageData['path'],
    //                 'alt_text'    => $imageData['alt_text'] ?? null,
    //                 'is_primary'  => $imageData['is_primary'] ?? ($index === 0),
    //                 'sort_order'  => $index,
    //             ]);
    //         }
    //     }
    // }

    private function syncImages(Product $product, array $images): void
    {
        // Keep images with id
        $keepIds = collect($images)
            ->pluck('id')
            ->filter()
            ->values()
            ->toArray();

        $existingIds = $product->images()->pluck('id')->toArray();

        $toDelete = $product->images()
            ->when(!empty($keepIds), fn($q) => $q->whereNotIn('id', $keepIds))
            ->get();

        foreach ($toDelete as $img) {
            Storage::disk('public')->delete($img->image_path);
            $img->delete();
        }

        // Insert/Update
        foreach ($images as $index => $imageData) {
            if (!empty($imageData['id'])) {
                // Existing image — update metadata only
                ProductImage::where('id', $imageData['id'])
                    ->where('product_id', $product->id) // security check
                    ->update([
                        'is_primary' => $imageData['is_primary'] ?? false,
                        'sort_order' => $index,
                        'alt_text'   => $imageData['alt_text'] ?? null,
                    ]);
            } else {
                // New image — create
                if (empty($imageData['path'])) continue; // safety check

                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $imageData['path'],
                    'alt_text'   => $imageData['alt_text'] ?? null,
                    'is_primary' => $imageData['is_primary'] ?? ($index === 0),
                    'sort_order' => $index,
                ]);
            }
        }

        // There will be only one primary image — ensure
        $primaryCount = $product->images()->where('is_primary', true)->count();
        if ($primaryCount === 0 && $product->images()->exists()) {
            $product->images()->orderBy('sort_order')->first()->update(['is_primary' => true]);
        } elseif ($primaryCount > 1) {
            $firstPrimary = $product->images()->where('is_primary', true)->orderBy('sort_order')->first();
            $product->images()->where('id', '!=', $firstPrimary->id)->update(['is_primary' => false]);
        }
    }

    private function syncVideos(Product $product, array $videos): void
    {
        $keepIds = collect($videos)
            ->pluck('id')
            ->filter()
            ->values()
            ->toArray();

        $toDelete = $product->videos()
            ->when(!empty($keepIds), fn($q) => $q->whereNotIn('id', $keepIds))
            ->get();

        foreach ($toDelete as $vid) {
            if ($vid->video_path && !str_starts_with($vid->video_path, 'http')) {
                Storage::disk('public')->delete($vid->video_path);
            }
            if ($vid->poster_path && !str_starts_with($vid->poster_path, 'http')) {
                Storage::disk('public')->delete($vid->poster_path);
            }
            $vid->delete();
        }

        foreach ($videos as $index => $videoData) {
            $externalUrl = $videoData['external_url'] ?? null;
            $videoPath = $videoData['video_path'] ?? null;

            if (!empty($videoData['id'])) {
                $existing = ProductVideo::where('id', $videoData['id'])
                    ->where('product_id', $product->id)
                    ->first();

                if ($existing) {
                    $update = [
                        'title'      => $videoData['title'] ?? $existing->title,
                        'is_primary' => (bool) ($videoData['is_primary'] ?? false),
                        'sort_order' => $videoData['sort_order'] ?? $index,
                    ];

                    if (!empty($videoData['poster_path'])) {
                        $update['poster_path'] = $videoData['poster_path'];
                    }

                    $existing->update($update);
                }
                continue;
            }

            if (empty($videoPath) && empty($externalUrl)) {
                continue;
            }

            $meta = $externalUrl
                ? ProductVideo::extractVideoMetadata($externalUrl)
                : ['source_type' => 'upload', 'embed_id' => null];

            $sourceType = $videoData['source_type'] ?? $meta['source_type'];
            $isUpload = $sourceType === 'upload';

            $video = ProductVideo::create([
                'product_id'        => $product->id,
                'variant_id'        => $videoData['variant_id'] ?? null,
                'source_type'       => $sourceType,
                'title'             => $videoData['title'] ?? null,
                'video_path'        => $videoPath,
                'external_url'      => $externalUrl,
                'embed_id'          => $meta['embed_id'],
                'poster_path'       => $videoData['poster_path'] ?? null,
                'mime_type'         => $videoData['mime_type'] ?? null,
                'file_size'         => $videoData['file_size'] ?? null,
                'processing_status' => $isUpload ? 'pending' : 'ready',
                'is_primary'        => (bool) ($videoData['is_primary'] ?? ($index === 0)),
                'sort_order'        => $videoData['sort_order'] ?? $index,
            ]);

            if ($isUpload) {
                ProcessProductVideoJob::dispatch($video);
            }
        }

        // Guarantee at most one primary video
        $primaryCount = $product->videos()->where('is_primary', true)->count();
        if ($primaryCount === 0 && $product->videos()->exists()) {
            $product->videos()->orderBy('sort_order')->first()->update(['is_primary' => true]);
        } elseif ($primaryCount > 1) {
            $firstPrimary = $product->videos()->where('is_primary', true)->orderBy('sort_order')->first();
            $product->videos()->where('id', '!=', $firstPrimary->id)->update(['is_primary' => false]);
        }

        $product->updateQuietly([
            'has_video' => $product->videos()->exists(),
        ]);
    }

    private function syncAttributes(Product $product, array $attributes): void
    {
        $keepAttrIds = [];

        foreach ($attributes as $index => $attrData) {
            $attribute = !empty($attrData['id'])
                ? ProductAttribute::where('product_id', $product->id)->where('id', $attrData['id'])->first()
                : null;

            if ($attribute) {
                $attribute->update([
                    'name'       => $attrData['name'],
                    'sort_order' => $index,
                ]);
            } else {
                $attribute = ProductAttribute::updateOrCreate(
                    ['product_id' => $product->id, 'name' => $attrData['name']],
                    ['sort_order' => $index]
                );
            }

            $keepAttrIds[] = $attribute->id;
            $keepValIds = [];

            foreach ($attrData['values'] ?? [] as $valIndex => $valData) {
                $value = !empty($valData['id'])
                    ? ProductAttributeValue::where('product_attribute_id', $attribute->id)->where('id', $valData['id'])->first()
                    : null;

                if ($value) {
                    $value->update([
                        'value'      => $valData['value'],
                        'color_code' => $valData['color_code'] ?? null,
                        'sort_order' => $valIndex,
                    ]);
                } else {
                    $value = ProductAttributeValue::updateOrCreate(
                        ['product_attribute_id' => $attribute->id, 'value' => $valData['value']],
                        ['color_code' => $valData['color_code'] ?? null, 'sort_order' => $valIndex]
                    );
                }

                $keepValIds[] = $value->id;
            }

            $attribute->values()->whereNotIn('id', $keepValIds)->delete();
        }

        $product->attributes()->whereNotIn('id', $keepAttrIds)->delete();
    }

    private function syncVariants(Product $product, array $variants): void
    {
        $keepIds = [];

        foreach ($variants as $index => $variantData) {
            $variant = null;

            if (!empty($variantData['id'])) {
                $variant = ProductVariant::where('product_id', $product->id)
                    ->where('id', $variantData['id'])
                    ->first();
            } elseif (!empty($variantData['sku'])) {
                $variant = ProductVariant::where('product_id', $product->id)
                    ->where('sku', $variantData['sku'])
                    ->first();
            }

            $payload = [
                'product_id'     => $product->id,
                'sku'            => $variantData['sku'] ?? null,
                'name'           => $variantData['name'] ?? null,
                'attributes'     => $variantData['attributes'],
                'price'          => $variantData['price'],
                'sale_price'     => $variantData['sale_price'] ?? null,
                'stock_quantity' => $variantData['stock_quantity'] ?? 0,
                'weight'         => $variantData['weight'] ?? null,
                'image'          => $variantData['image'] ?? null,
                'is_active'      => $variantData['is_active'] ?? true,
                'sort_order'     => $variantData['sort_order'] ?? $index,
            ];

            if ($variant) {
                $variant->update($payload);
            } else {
                $variant = ProductVariant::create($payload);
            }

            $keepIds[] = $variant->id;
        }

        // Preserve referential integrity for historical orders by deactivating removed variants
        ProductVariant::where('product_id', $product->id)
            ->when(!empty($keepIds), fn($q) => $q->whereNotIn('id', $keepIds))
            ->update(['is_active' => false]);

        $product->syncVariantMetrics();
    }

    private function clearProductCache(?string $slug = null): void
    {
        if ($slug) {
            Cache::forget("product_{$slug}");
        }

        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(['products'])->flush();
            return;
        }

        Cache::has('products_cache_version')
            ? Cache::increment('products_cache_version')
            : Cache::forever('products_cache_version', 2);
    }
}
