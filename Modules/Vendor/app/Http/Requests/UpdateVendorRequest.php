<?php

namespace Modules\Vendor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // For the vendor's own profile update (no route param), use the user's vendor ID
        $vendorId = $this->user()?->vendor?->id;

        // For admin updating a specific vendor, use the route parameter
        if (!$vendorId) {
            $vendorId = $this->route('vendor')?->id ?? $this->route('vendor');
        }

        return [
            'shop_name'                    => ['sometimes', 'string', 'max:200', Rule::unique('vendors', 'shop_name')->ignore($vendorId)],
            'email'                        => 'nullable|email|max:100',
            'phone'                        => 'nullable|string|max:20',
            'description'                  => 'nullable|string|max:2000',
            'logo'                         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'banner'                       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'business_type'                => 'nullable|string|max:100',
            'business_registration_number' => 'nullable|string|max:100',
            'tax_id'                       => 'nullable|string|max:100',
            'website'                      => 'nullable|url|max:255',
            'social_facebook'              => 'nullable|url|max:255',
            'social_instagram'             => 'nullable|url|max:255',
            'social_youtube'               => 'nullable|url|max:255',
            'return_policy'                => 'nullable|string|max:5000',
            'shipping_policy'              => 'nullable|string|max:5000',
        ];
    }
}
