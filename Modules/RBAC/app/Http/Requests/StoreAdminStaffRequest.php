<?php

namespace Modules\RBAC\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdminStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|max:255|unique:users,email',
            'phone'    => 'nullable|string|max:20|unique:users,phone',
            'role'     => 'required_without:roles|string|exists:roles,name',
            'roles'    => 'sometimes|array|min:1',
            'roles.*'  => 'string|exists:roles,name',
            'status'   => 'nullable|string|in:active,inactive,banned',
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}
