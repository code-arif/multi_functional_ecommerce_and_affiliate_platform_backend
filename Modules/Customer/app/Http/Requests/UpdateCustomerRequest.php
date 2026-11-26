<?php

namespace Modules\Customer\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $param = $this->route('customer') ?? $this->route('user');
        $userId = is_object($param)
            ? $param->id
            : User::query()
                ->when(is_numeric($param), fn ($q) => $q->where('id', (int) $param), fn ($q) => $q->where('uuid', (string) $param))
                ->value('id');

        return [
            'name'   => 'sometimes|required|string|max:100',
            'email'  => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone'  => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($userId)],
            'status' => 'sometimes|string|in:active,inactive,banned',
        ];
    }
}
