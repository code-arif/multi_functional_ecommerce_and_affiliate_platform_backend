<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        $productId = $this->route('product')?->id;
        return [
            'name'              => 'sometimes|string|max:255',
            'category_id'       => 'nullable|exists:categories,id',
            'brand_id'          => 'nullable|exists:brands,id',
            'vendor_uuid'       => 'nullable|exists:vendors,uuid',
            'vendor_id'         => 'nullable|exists:vendors,id',
            'sku'               => "nullable|string|unique:products,sku,{$productId}",
            'price'             => 'sometimes|numeric|min:0',
            'sale_price'        => 'nullable|numeric|min:0',
            'cost_price'        => 'nullable|numeric|min:0',
            'stock_quantity'    => 'sometimes|integer|min:0',
            'manage_stock'      => 'sometimes|boolean',
            'short_description' => 'nullable|string|max:500',
            'description'       => 'nullable|string',
            'thumbnail'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_featured'       => 'sometimes|boolean',
            'is_new'            => 'sometimes|boolean',
            'is_bestseller'     => 'sometimes|boolean',
            'meta_title'        => 'nullable|string|max:255',
            'meta_description'  => 'nullable|string|max:500',
            'status'            => 'sometimes|in:active,inactive,draft,pending',
            'tags'                    => 'nullable|array',
            'images'                  => 'nullable|array',
            'videos'                  => 'nullable|array',
            'videos.*.id'             => 'nullable|integer',
            'videos.*.source_type'    => 'nullable|in:upload,youtube,vimeo,external',
            'videos.*.title'          => 'nullable|string|max:255',
            'videos.*.video_path'     => 'nullable|string|max:500',
            'videos.*.external_url'   => 'nullable|url|max:1000',
            'videos.*.poster_path'    => 'nullable|string|max:500',
            'videos.*.mime_type'      => 'nullable|string|max:100',
            'videos.*.file_size'      => 'nullable|integer|min:0',
            'videos.*.is_primary'     => 'boolean',
            'videos.*.sort_order'     => 'nullable|integer|min:0',
            'attributes'              => 'nullable|array',
            'variants'                => 'nullable|array',
        ];
    }

    protected function prepareForValidation(): void
    {
        $casts = [];

        // Boolean fields: "true"/"1"/1 → true
        foreach (['manage_stock', 'is_featured', 'is_new', 'is_bestseller'] as $field) {
            if ($this->has($field)) {
                $casts[$field] = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN);
            }
        }

        // JSON string arrays → real arrays
        foreach (['tags', 'attributes', 'variants', 'images', 'videos'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $decoded = json_decode($this->input($field), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $casts[$field] = $decoded;
                }
            }
        }

        // Empty strings from multipart FormData → null for nullable foreign keys
        foreach (['vendor_uuid', 'vendor_id', 'category_id', 'brand_id'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $casts[$field] = null;
            }
        }

        $this->merge($casts);
    }
}
