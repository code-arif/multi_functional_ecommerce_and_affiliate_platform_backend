<?php

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Catalog\Models\Brand;
use Modules\Catalog\Traits\HasSlug;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $brandId = $this->route('brand')?->id;

        $rules = [
            'name' => [
                'sometimes',
                'string',
                'max:100',
                HasSlug::slugValidationRule(Brand::class, $brandId, 'brand'),
            ],
            'description' => 'nullable|string|max:1000',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'banner' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'website' => 'nullable|url|max:255',
            'meta_title' => 'nullable|string|max:100',
            'meta_description' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];

        // Only Admin/Moderator can set status directly
        if ($this->user() && ($this->user()->isAdmin() || $this->user()->isModerator())) {
            $rules['status'] = 'sometimes|string|in:approved,pending,rejected';
        }

        return $rules;
    }
}
