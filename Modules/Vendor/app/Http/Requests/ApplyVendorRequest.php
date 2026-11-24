<?php

namespace Modules\Vendor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shop_name'                    => ['required', 'string', 'max:200', Rule::unique('vendors', 'shop_name')],
            'phone'                        => ['nullable', 'string', 'max:20'],
            'description'                  => ['nullable', 'string', 'max:2000'],
            'business_type'                => ['nullable', 'string', 'max:100'],
            'business_registration_number' => ['nullable', 'string', 'max:100'],
            'tax_id'                       => ['nullable', 'string', 'max:100'],
            'website'                      => ['nullable', 'url', 'max:255'],
            'address_line_1'               => ['nullable', 'string', 'max:255'],
            'address_line_2'               => ['nullable', 'string', 'max:255'],
            'city'                         => ['nullable', 'string', 'max:100'],
            'state'                        => ['nullable', 'string', 'max:100'],
            'postal_code'                  => ['nullable', 'string', 'max:20'],
            'country'                      => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'shop_name.required' => 'Shop name is required to apply for a seller account.',
            'shop_name.unique'   => 'This shop name is already taken. Please pick another name.',
            'website.url'        => 'Please provide a valid URL for your website.',
        ];
    }
}
