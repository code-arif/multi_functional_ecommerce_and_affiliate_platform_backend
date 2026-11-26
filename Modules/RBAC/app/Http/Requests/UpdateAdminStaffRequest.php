<?php

namespace Modules\RBAC\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $param = $this->route('admin');
        $userId = is_object($param)
            ? $param->id
            : User::query()
                ->when(is_numeric($param), fn ($q) => $q->where('id', (int) $param), fn ($q) => $q->where('uuid', (string) $param))
                ->value('id');

        return [
            'name'     => 'sometimes|required|string|max:100',
            'email'    => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone'    => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($userId)],
            'role'     => 'sometimes|string|exists:roles,name',
            'roles'    => 'sometimes|array|min:1',
            'roles.*'  => 'string|exists:roles,name',
            'status'   => 'sometimes|string|in:active,inactive,banned',
            'password' => 'nullable|string|min:8|confirmed',
        ];
    }
}
