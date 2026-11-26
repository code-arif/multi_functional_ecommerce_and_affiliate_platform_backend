<?php

namespace Modules\Catalog\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Modules\Catalog\Http\Requests\StoreBrandRequest;
use Modules\Catalog\Http\Requests\UpdateBrandRequest;
use Modules\Catalog\Models\Brand;
use Modules\Catalog\Transformers\BrandResource;
use Modules\Core\Traits\ApiResponse;
use Modules\Notifications\Notifications\BrandProposedNotification;
use Modules\Notifications\Notifications\BrandStatusNotification;

class BrandManageController
{
    use ApiResponse;

    // List all brands with optional search and pagination
    public function index(Request $request): JsonResponse
    {
        $brands = Brand::withCount('products')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->per_page ?? 50);

        return $this->paginatedResponse(BrandResource::collection($brands));
    }

    // Show a single brand with creator, updater, and associated products
    public function show(Brand $brand): JsonResponse
    {
        $brand->load(['creator', 'updater', 'products']);
        $brand->loadCount('products');
        return $this->successResponse(new BrandResource($brand));
    }

    // Store a new brand
    public function store(StoreBrandRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('brands', 'public');
        }
        if ($request->hasFile('banner')) {
            $data['banner'] = $request->file('banner')->store('brands/banners', 'public');
        }

        $brand = Brand::create($data);

        // Notify admins/moderators when a vendor submits a brand proposal
        $user = $request->user();
        if ($user && !$user->isAdmin() && !$user->isModerator()) {
            $user->loadMissing('vendor');
            $submittedBy = $user->vendor?->shop_name ?? $user->name;
            $admins = User::role(['super-admin', 'admin', 'moderator'])
                ->where('status', 'active')
                ->get();

            if ($admins->isNotEmpty()) {
                Notification::send($admins, new BrandProposedNotification($brand, $submittedBy));
            }
        }

        return $this->createdResponse(new BrandResource($brand), 'Brand created.');
    }

    // Update an existing brand
    public function update(Brand $brand, UpdateBrandRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('brands', 'public');
        }
        if ($request->hasFile('banner')) {
            $data['banner'] = $request->file('banner')->store('brands/banners', 'public');
        }

        $previousStatus = $brand->status;
        $brand->update($data);

        if (isset($data['status']) && $data['status'] !== $previousStatus) {
            $this->notifyCreatorStatusChange($brand, $data['status']);
        }

        return $this->successResponse(new BrandResource($brand->fresh()), 'Brand updated.');
    }

    // Approve a brand proposal and notify the vendor creator
    public function approve(Brand $brand): JsonResponse
    {
        $previousStatus = $brand->status;
        $brand->update(['status' => 'approved']);

        if ($previousStatus !== 'approved') {
            $this->notifyCreatorStatusChange($brand, 'approved');
        }

        $brand = $brand->fresh()->load(['creator', 'updater', 'products'])->loadCount('products');

        return $this->successResponse(new BrandResource($brand), 'Brand approved.');
    }

    // Reject a brand proposal and notify the vendor creator
    public function reject(Request $request, Brand $brand): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $previousStatus = $brand->status;
        $reason = $validated['reason'] ?? null;
        $brand->update(['status' => 'rejected']);

        if ($previousStatus !== 'rejected') {
            $this->notifyCreatorStatusChange($brand, 'rejected', $reason);
        }

        $brand = $brand->fresh()->load(['creator', 'updater', 'products'])->loadCount('products');

        return $this->successResponse(new BrandResource($brand), 'Brand rejected.');
    }

    // Notify vendor creator when brand approval status changes
    private function notifyCreatorStatusChange(Brand $brand, string $status, ?string $reason = null): void
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return;
        }

        $creator = $brand->creator;
        if ($creator && !$creator->isAdmin() && !$creator->isModerator()) {
            $creator->notify(new BrandStatusNotification($brand, $status, $reason));
        }
    }

    // Delete a brand, ensuring it has no associated products
    public function destroy(Brand $brand): JsonResponse
    {
        if ($brand->products()->exists()) {
            return $this->errorResponse('Cannot delete brand with associated products.', null, 400);
        }
        $brand->delete();
        return $this->noContentResponse('Brand deleted.');
    }
}
