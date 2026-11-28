<?php

namespace Modules\Catalog\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryPopularResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid'         => $this->uuid,
            'name'         => $this->name,
            'slug'         => $this->slug,
            'image_url'    => $this->image_url,
            'icon'         => $this->icon,
            'orders_count' => (int) ($this->orders_count ?? 0),
        ];
    }
}
