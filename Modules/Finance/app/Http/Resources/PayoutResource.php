<?php

namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PayoutResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'uuid'            => $this->uuid,
            'vendor_id'       => $this->vendor_id,
            'vendor'          => $this->whenLoaded('vendor', fn() => [
                'uuid'            => $this->vendor?->uuid,
                'shop_name'       => $this->vendor?->shop_name,
                'email'           => $this->vendor?->email,
                'commission_rate' => (float) ($this->vendor?->commission_rate ?? 0),
                'wallet_balance'  => (float) ($this->vendor?->wallet_balance ?? 0),
            ]),
            'amount'          => (float) $this->amount,
            'balance_before'  => (float) $this->balance_before,
            'balance_after'   => (float) $this->balance_after,
            'payment_method'  => $this->payment_method,
            'payment_details' => $this->payment_details,
            'notes'           => $this->notes,
            'status'          => $this->status,
            'is_pending'      => $this->is_pending,
            'is_completed'    => $this->is_completed,
            'admin_notes'     => $this->admin_notes,
            'approved_at'     => $this->approved_at,
            'completed_at'    => $this->completed_at,
            'created_at'      => $this->created_at,
        ];
    }
}
