<?php

namespace Modules\Catalog\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Modules\Catalog\Http\Requests\StoreCategoryRequest;
use Modules\Catalog\Http\Requests\UpdateCategoryRequest;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Transformers\CategoryResource;
use Modules\Core\Traits\ApiResponse;
use Modules\Notifications\Notifications\CategoryProposedNotification;
use Modules\Notifications\Notifications\CategoryStatusNotification;

class CategoryManageController
{
    use ApiResponse;

    // List all categories with optional search and pagination
    public function index(Request $request): JsonResponse
    {
        $categories = Category::with(['parent', 'children' => fn($q) => $q->orderBy('sort_order')])
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('sort_order')
            ->paginate($request->per_page ?? 50);

        return $this->paginatedResponse(CategoryResource::collection($categories));
    }

    // Show a single category with children, parent, and products
    public function show(Category $category): JsonResponse
    {
        $category->load(['parent', 'creator', 'updater', 'products']);
        $category->loadCount('products');
        return $this->successResponse(new CategoryResource($category));
    }

    // Store a new category
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $this->mapParentUuid($request->validated());

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }
        if ($request->hasFile('banner')) {
            $data['banner'] = $request->file('banner')->store('categories/banners', 'public');
        }

        $category = Category::create($data);

        // Notify admins/moderators when a vendor submits a category proposal
        $user = $request->user();
        if ($user && !$user->isAdmin() && !$user->isModerator()) {
            $user->loadMissing('vendor');
            $submittedBy = $user->vendor?->shop_name ?? $user->name;
            $admins = User::role(['super-admin', 'admin', 'moderator'])
                ->where('status', 'active')
                ->get();

            if ($admins->isNotEmpty()) {
                Notification::send($admins, new CategoryProposedNotification($category, $submittedBy));
            }
        }

        return $this->createdResponse(new CategoryResource($category->load('parent')), 'Category created.');
    }

    // Update an existing category
    public function update(Category $category, UpdateCategoryRequest $request): JsonResponse
    {
        $data = $this->mapParentUuid($request->validated());

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }
        if ($request->hasFile('banner')) {
            $data['banner'] = $request->file('banner')->store('categories/banners', 'public');
        }

        $previousStatus = $category->status;
        $category->update($data);

        if (isset($data['status']) && $data['status'] !== $previousStatus) {
            $this->notifyCreatorStatusChange($category, $data['status']);
        }

        return $this->successResponse(new CategoryResource($category->fresh()->load('parent')), 'Category updated.');
    }

    // Approve a category proposal and notify the vendor creator
    public function approve(Category $category): JsonResponse
    {
        $previousStatus = $category->status;
        $category->update(['status' => 'approved']);

        if ($previousStatus !== 'approved') {
            $this->notifyCreatorStatusChange($category, 'approved');
        }

        $category = $category->fresh()->load(['parent', 'creator', 'updater', 'products'])->loadCount('products');

        return $this->successResponse(new CategoryResource($category), 'Category approved.');
    }

    // Reject a category proposal and notify the vendor creator
    public function reject(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $previousStatus = $category->status;
        $reason = $validated['reason'] ?? null;
        $category->update(['status' => 'rejected']);

        if ($previousStatus !== 'rejected') {
            $this->notifyCreatorStatusChange($category, 'rejected', $reason);
        }

        $category = $category->fresh()->load(['parent', 'creator', 'updater', 'products'])->loadCount('products');

        return $this->successResponse(new CategoryResource($category), 'Category rejected.');
    }

    // Notify vendor creator when category approval status changes
    private function notifyCreatorStatusChange(Category $category, string $status, ?string $reason = null): void
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return;
        }

        $creator = $category->creator;
        if ($creator && !$creator->isAdmin() && !$creator->isModerator()) {
            $creator->notify(new CategoryStatusNotification($category, $status, $reason));
        }
    }


    // Map public parent_uuid reference to internal parent_id.
    private function mapParentUuid(array $data): array
    {
        if (array_key_exists('parent_uuid', $data)) {
            $data['parent_id'] = !empty($data['parent_uuid'])
                ? Category::findByUuidOrFail($data['parent_uuid'])->id
                : null;
        }
        unset($data['parent_uuid']);
        return $data;
    }

    // Delete a category, ensuring it has no associated products
    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->exists()) {
            return $this->errorResponse('Cannot delete category with associated products.', null, 400);
        }
        $category->delete();
        return $this->noContentResponse('Category deleted.');
    }
}
