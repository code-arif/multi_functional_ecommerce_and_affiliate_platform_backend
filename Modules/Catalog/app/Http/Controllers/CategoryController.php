<?php

namespace Modules\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Brand;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Transformers\CategoryDetailResource;
use Modules\Catalog\Transformers\CategoryNavResource;
use Modules\Catalog\Transformers\CategoryPopularResource;
use Modules\Catalog\Transformers\CategoryResource;
use Modules\Core\Traits\ApiResponse;
use Modules\Product\Models\Product;

class CategoryController
{
    use ApiResponse;

    public function popular(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->input('limit', 16), 1), 32);

        $orderCountsSub = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereNotIn('orders.status', ['cancelled', 'refunded'])
            ->whereNull('orders.deleted_at')
            ->whereNotNull('products.category_id')
            ->selectRaw('products.category_id, COUNT(DISTINCT order_items.order_id) as orders_count')
            ->groupBy('products.category_id');

        $categories = Category::query()
            ->leftJoinSub($orderCountsSub, 'cat_orders', function ($join) {
                $join->on('categories.id', '=', 'cat_orders.category_id');
            })
            ->select([
                'categories.id',
                'categories.uuid',
                'categories.parent_id',
                'categories.name',
                'categories.slug',
                'categories.image',
                'categories.icon',
                'categories.sort_order',
                DB::raw('COALESCE(cat_orders.orders_count, 0) as orders_count'),
            ])
            ->where('categories.is_active', true)
            ->where('categories.status', 'approved')
            ->orderByDesc('orders_count')
            ->orderBy('categories.sort_order')
            ->orderBy('categories.id')
            ->limit($limit)
            ->get();

        return $this->successResponse(CategoryPopularResource::collection($categories));
    }

    public function navigation(): JsonResponse
    {
        $categories = Category::query()
            ->select(['id', 'uuid', 'parent_id', 'name', 'slug', 'icon', 'sort_order'])
            ->active()
            ->where('status', 'approved')
            ->with([
                'children' => fn($q) => $q
                    ->select(['id', 'uuid', 'parent_id', 'name', 'slug', 'icon', 'sort_order'])
                    ->active()
                    ->where('status', 'approved')
                    ->orderBy('sort_order'),
            ])
            ->root()
            ->orderBy('sort_order')
            ->get();

        return $this->successResponse(CategoryNavResource::collection($categories));
    }

    public function index(): JsonResponse
    {
        $categories = Category::active()
            ->where('status', 'approved')
            ->withCount(['products' => fn($q) => $q->active()])
            ->with([
                'children' => fn($q) => $q
                    ->active()
                    ->where('status', 'approved')
                    ->withCount(['products' => fn($pq) => $pq->active()])
                    ->orderBy('sort_order'),
            ])
            ->root()
            ->orderBy('sort_order')
            ->get();

        return $this->successResponse(CategoryResource::collection($categories));
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'sub'       => 'nullable|string|max:120',
            'brand'     => 'nullable|string|max:120',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => $request->filled('min_price')
                ? 'nullable|numeric|min:0|gte:min_price'
                : 'nullable|numeric|min:0',
            'rating'    => 'nullable|numeric|min:1|max:5',
            'search'    => 'nullable|string|max:120',
            'in_stock'  => 'nullable|boolean',
            'sort'      => 'nullable|in:relevance,newest,popular,rating,price_asc,price_desc',
            'per_page'  => 'nullable|integer|min:1|max:48',
            'page'      => 'nullable|integer|min:1',
        ]);

        $category = Category::active()
            ->where('status', 'approved')
            ->with([
                'parent' => fn($q) => $q->active()->where('status', 'approved')->select(['id', 'uuid', 'name', 'slug']),
                'children' => fn($q) => $q
                    ->active()
                    ->where('status', 'approved')
                    ->withCount(['products' => fn($pq) => $pq->active()])
                    ->orderBy('sort_order'),
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        $scopeCategory = $category;
        if (!empty($validated['sub'])) {
            $scopeCategory = $category->children
                ->firstWhere('slug', $validated['sub'])
                ?? Category::active()
                    ->where('status', 'approved')
                    ->where('parent_id', $category->id)
                    ->where('slug', $validated['sub'])
                    ->firstOrFail();
        }

        $scopedCategoryIds = $this->collectActiveDescendantIds($scopeCategory);

        $baseQuery = Product::active()->whereIn('category_id', $scopedCategoryIds);

        // Extract facet bounds cleanly on scoped products before narrow brand/price filters
        $brandIds = (clone $baseQuery)->whereNotNull('brand_id')->distinct()->pluck('brand_id');
        $availableBrands = $brandIds->isEmpty()
            ? []
            : Brand::active()
                ->where('status', 'approved')
                ->whereIn('id', $brandIds)
                ->select(['uuid', 'name', 'slug'])
                ->orderBy('name')
                ->get()
                ->map(fn($b) => [
                    'uuid' => $b->uuid,
                    'name' => $b->name,
                    'slug' => $b->slug,
                ])
                ->values()
                ->all();

        $priceStats = (clone $baseQuery)
            ->selectRaw('COALESCE(MIN(effective_price), 0) as min_price, COALESCE(MAX(effective_price), 0) as max_price')
            ->first();

        $productQuery = (clone $baseQuery)
            ->select(Product::LIST_COLUMNS)
            ->with([
                'category:id,uuid,name,slug',
                'brand:id,uuid,name,slug',
                'vendor:id,uuid,shop_name,slug',
                'primaryImage:id,product_id,image_path,is_primary',
            ])
            ->when($validated['brand'] ?? null, function ($q, $brandSlug) {
                $brandId = Brand::active()->where('status', 'approved')->where('slug', $brandSlug)->value('id');
                return $brandId ? $q->where('brand_id', $brandId) : $q->whereRaw('1 = 0');
            })
            ->when(isset($validated['min_price']), fn($q) => $q->where('effective_price', '>=', (float) $validated['min_price']))
            ->when(isset($validated['max_price']), fn($q) => $q->where('effective_price', '<=', (float) $validated['max_price']))
            ->when(isset($validated['rating']), fn($q) => $q->where('average_rating', '>=', (float) $validated['rating']))
            ->when(!empty($validated['in_stock']), fn($q) => $q->inStock())
            ->when($validated['search'] ?? null, function ($q, $term) {
                $term = trim((string) $term);
                if ($term === '') {
                    return;
                }
                $q->where(fn($sq) => $sq->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "{$term}%"));
            });

        match ($validated['sort'] ?? 'popular') {
            'price_asc'  => $productQuery->orderBy('effective_price')->orderBy('id'),
            'price_desc' => $productQuery->orderByDesc('effective_price')->orderByDesc('id'),
            'newest'     => $productQuery->orderByDesc('created_at')->orderByDesc('id'),
            'rating'     => $productQuery->orderByDesc('average_rating')->orderByDesc('id'),
            default      => $productQuery->orderByDesc('total_sold')->orderByDesc('created_at')->orderByDesc('id'),
        };

        $perPage = (int) ($validated['per_page'] ?? 20);
        $products = $productQuery->paginate($perPage);

        $category->setAttribute('detail_products', $products);
        $category->setAttribute('detail_filters', [
            'brands'      => $availableBrands,
            'price_range' => [
                'min' => (float) ($priceStats->min_price ?? 0),
                'max' => (float) ($priceStats->max_price ?? 0),
            ],
        ]);

        return $this->successResponse(
            new CategoryDetailResource($category),
            'Category details retrieved.'
        );
    }

    private function collectActiveDescendantIds(Category $root): array
    {
        $ids = [$root->id];
        $frontier = [$root->id];

        while (!empty($frontier)) {
            $childIds = Category::active()
                ->where('status', 'approved')
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->diff($ids)
                ->values()
                ->all();

            if (empty($childIds)) {
                break;
            }

            $ids = array_merge($ids, $childIds);
            $frontier = $childIds;
        }

        return $ids;
    }
}
