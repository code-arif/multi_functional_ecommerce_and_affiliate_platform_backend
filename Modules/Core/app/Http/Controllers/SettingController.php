<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Requests\UpdateSettingsRequest;
use Modules\Core\Http\Requests\UploadSettingFileRequest;
use Modules\Core\Http\Resources\SettingResource;
use Modules\Core\Models\Setting;
use Modules\Core\Traits\ApiResponse;

class SettingController
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $settings = Setting::orderBy('group')->orderBy('key')->get();
        return $this->successResponse(SettingResource::collection($settings));
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        foreach ($request->validated('settings') as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'] ?? '']
            );
        }

        return $this->successResponse(null, 'Settings updated.');
    }

    public function uploadFile(UploadSettingFileRequest $request): JsonResponse
    {
        $path = $request->file('file')->store('settings', 'public');

        return $this->successResponse([
            'path' => $path,
            'url'  => asset('storage/' . $path),
        ], 'File uploaded.');
    }
}
