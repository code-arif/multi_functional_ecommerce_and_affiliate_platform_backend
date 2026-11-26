<?php

namespace Modules\Customer\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray($request): array
    {
        $liveOrdersCount = (int) ($this->live_orders_count ?? 0);
        $isVerifiedCustomer = $liveOrdersCount > 0;

        return [
            'id'                   => $this->id,
            'uuid'                 => $this->uuid,
            'name'                 => $this->name,
            'email'                => $this->email,
            'phone'                => $this->phone,
            'avatar'               => $this->avatar_url,
            'status'               => $this->status,
            'is_active'            => $this->is_active,
            'email_verified_at'    => $this->email_verified_at,
            'roles'                => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'orders_count'         => $liveOrdersCount,
            'total_spent'          => (float) ($this->total_spent ?? 0),
            'last_order_at'        => $this->last_order_at,
            'is_verified_customer' => $isVerifiedCustomer,
            'customer_type'        => $isVerifiedCustomer ? 'verified_customer' : 'free_user',
            'default_address'      => $this->whenLoaded('defaultAddress'),
            'addresses'            => $this->whenLoaded('addresses'),
            'orders'               => $this->whenLoaded('orders', function () {
                return $this->orders->map(fn ($order) => [
                    'id'             => $order->id,
                    'uuid'           => $order->uuid,
                    'order_number'   => $order->order_number,
                    'status'         => $order->status,
                    'payment_status' => $order->payment_status,
                    'payment_method' => $order->payment_method,
                    'total_amount'   => (float) $order->total_amount,
                    'vendor'         => $order->relationLoaded('vendor') && $order->vendor ? [
                        'id'        => $order->vendor->id,
                        'uuid'      => $order->vendor->uuid,
                        'shop_name' => $order->vendor->shop_name,
                        'slug'      => $order->vendor->slug,
                    ] : null,
                    'created_at'     => $order->created_at,
                ]);
            }),
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
