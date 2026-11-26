<?php

namespace Modules\RBAC\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class AdminStaffResource extends JsonResource
{
    public function toArray($request): array
    {
        $primaryRole = $this->relationLoaded('roles') ? $this->roles->first() : null;

        return [
            'id'                => $this->id,
            'uuid'              => $this->uuid,
            'name'              => $this->name,
            'email'             => $this->email,
            'phone'             => $this->phone,
            'avatar'            => $this->avatar_url,
            'status'            => $this->status,
            'is_active'         => $this->is_active,
            'role'              => $primaryRole?->name,
            'role_display_name' => $primaryRole?->display_name ?: $primaryRole?->name,
            'roles'             => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'permissions'       => $this->whenLoaded('roles', fn () => $this->getAllPermissions()->pluck('name')->unique()->values()),
            'email_verified_at' => $this->email_verified_at,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
