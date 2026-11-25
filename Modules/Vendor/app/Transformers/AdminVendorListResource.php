<?php

namespace Modules\Vendor\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class AdminVendorListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid'           => $this->uuid,
            'shop_name'      => $this->shop_name,
            'owner_name'     => $this->user?->name,
            'email'          => $this->email,
            'phone'          => $this->phone,
            'logo_url'       => $this->logo_url,
            'status'         => $this->status,
            'products_count' => (int) ($this->products_count ?? 0),
            'total_orders'   => (int) ($this->orders_count ?? 0),
            'total_sales'    => (float) ($this->orders_sum_total_amount ?? $this->total_earned ?? 0),
            'rating'         => (float) ($this->rating ?? 0),
            'reviews_count'  => (int) ($this->reviews_count ?? 0),
            'created_at'     => $this->created_at,
        ];
    }
}
