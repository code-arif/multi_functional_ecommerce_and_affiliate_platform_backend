<?php

namespace Modules\Promotions\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Traits\ApiResponse;
use Modules\Promotions\Http\Requests\StoreBannerRequest;
use Modules\Promotions\Http\Requests\UpdateBannerRequest;
use Modules\Promotions\Http\Resources\BannerResource;
use Modules\Promotions\Models\Banner;

class AdminBannerController
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $banners = Banner::orderBy('position')->orderBy('sort_order')
            ->paginate($request->per_page ?? 50);

        return $this->paginatedResponse(BannerResource::collection($banners));
    }

    public function store(StoreBannerRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('banners', 'public');
        }
        if ($request->hasFile('mobile_image')) {
            $data['mobile_image'] = $request->file('mobile_image')->store('banners/mobile', 'public');
        }

        $banner = Banner::create($data);

        return $this->createdResponse(new BannerResource($banner), 'Banner created.');
    }

    public function update(Banner $banner, UpdateBannerRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('banners', 'public');
        }
        if ($request->hasFile('mobile_image')) {
            $data['mobile_image'] = $request->file('mobile_image')->store('banners/mobile', 'public');
        }

        $banner->update($data);

        return $this->successResponse(new BannerResource($banner->fresh()), 'Banner updated.');
    }

    public function destroy(Banner $banner): JsonResponse
    {
        $banner->delete();
        return $this->noContentResponse('Banner deleted.');
    }
}
