<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VendorRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                         => ['required', 'string', 'max:100'],
            'email'                        => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone'                        => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password'                     => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation'        => ['required', 'string'],
            'shop_name'                    => ['required', 'string', 'max:200', Rule::unique('vendors', 'shop_name')],
            'business_type'                => ['nullable', 'string', 'max:100'],
            'description'                  => ['nullable', 'string', 'max:2000'],
            'business_registration_number' => ['nullable', 'string', 'max:100'],
            'tax_id'                       => ['nullable', 'string', 'max:100'],
            'website'                      => ['nullable', 'url', 'max:255'],
            'address_line_1'               => ['nullable', 'string', 'max:255'],
            'city'                         => ['nullable', 'string', 'max:100'],
            'state'                        => ['nullable', 'string', 'max:100'],
            'postal_code'                  => ['nullable', 'string', 'max:20'],
            'country'                      => ['nullable', 'string', 'max:100'],
            'bank_name'                    => ['nullable', 'string', 'max:200'],
            'branch_name'                  => ['nullable', 'string', 'max:200'],
            'account_name'                 => ['nullable', 'string', 'max:200'],
            'account_number'               => ['nullable', 'string', 'max:100'],
            'routing_number'               => ['nullable', 'string', 'max:50'],
            'mobile_banking_provider'      => ['nullable', 'string', 'max:50'],
            'mobile_banking_number'        => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'                  => 'Representative or owner full name is required.',
            'email.required'                 => 'Email address is required.',
            'email.email'                    => 'Please provide a valid email address.',
            'email.unique'                   => 'This email is already registered.',
            'phone.required'                 => 'Contact phone number is required.',
            'phone.unique'                   => 'This phone number is already registered.',
            'password.required'              => 'Password is required.',
            'password.min'                   => 'Password must be at least 8 characters.',
            'password.confirmed'             => 'Password confirmation does not match.',
            'password_confirmation.required' => 'Please confirm your password.',
            'shop_name.required'             => 'Store or brand name is required.',
            'shop_name.unique'               => 'This shop name is already taken. Please choose another name.',
        ];
    }
}
