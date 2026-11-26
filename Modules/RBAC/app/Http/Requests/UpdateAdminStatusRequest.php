<?php

namespace Modules\RBAC\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:active,inactive,banned',
        ];
    }
}
