<?php

namespace Modules\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Transformers\CategoryNavResource;
use Modules\Catalog\Transformers\CategoryPopularResource;
use Modules\Catalog\Transformers\CategoryResource;
use Modules\Core\Traits\ApiResponse;

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

    public function show(string $slug): JsonResponse
    {
        $category = Category::active()
            ->where('status', 'approved')
            ->with(['children' => fn($q) => $q->active()->where('status', 'approved')->orderBy('sort_order')])
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->successResponse(new CategoryResource($category));
    }
}
