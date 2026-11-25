<?php

namespace Modules\Vendor\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class AdminVendorDocumentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'uuid'             => $this->uuid,
            'type'             => $this->type,
            'document_number'  => $this->document_number,
            'document_url'     => $this->document_url,
            'status'           => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'verified_at'      => $this->verified_at,
            'created_at'       => $this->created_at,
            'vendor'           => $this->whenLoaded('vendor', fn() => [
                'uuid'      => $this->vendor?->uuid,
                'shop_name' => $this->vendor?->shop_name,
                'email'     => $this->vendor?->email,
                'status'    => $this->vendor?->status,
            ]),
        ];
    }
}
