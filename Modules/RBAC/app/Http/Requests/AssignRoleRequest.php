<?php

namespace Modules\RBAC\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_uuid' => 'required|exists:users,uuid',
            'roles'   => 'required|array',
            'roles.*' => 'string|exists:roles,name',
        ];
    }

    public function messages(): array
    {
        return [
            'user_uuid.required' => 'User UUID is required.',
            'user_uuid.exists'   => 'The selected user does not exist.',
            'roles.required'     => 'At least one role is required.',
            'roles.*.exists'     => 'One or more selected roles do not exist.',
        ];
    }
}
