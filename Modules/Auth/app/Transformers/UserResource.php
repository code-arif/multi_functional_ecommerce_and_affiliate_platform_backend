<?php

namespace Modules\Auth\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'    => $this->id,
            'uuid'  => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar_url,
            'status' => $this->status,
            'roles' => $this->whenLoaded('roles', fn() => $this->roles->pluck('name')),
            'default_address' => $this->whenLoaded('defaultAddress'),
            'vendor' => $this->whenLoaded('vendor', function () {
                if (!$this->vendor) {
                    return null;
                }
                return [
                    'id' => $this->vendor->id,
                    'uuid' => $this->vendor->uuid,
                    'shop_name' => $this->vendor->shop_name,
                    'slug' => $this->vendor->slug,
                    'email' => $this->vendor->email,
                    'phone' => $this->vendor->phone,
                    'status' => $this->vendor->status,
                    'rejection_reason' => $this->vendor->rejection_reason,
                    'created_at' => $this->vendor->created_at,
                ];
            }),
            'email_verified_at' => $this->email_verified_at,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
