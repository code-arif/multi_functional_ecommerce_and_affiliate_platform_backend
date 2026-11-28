<?php

namespace Modules\Catalog\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryNavResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid'     => $this->uuid,
            'name'     => $this->name,
            'slug'     => $this->slug,
            'icon'     => $this->icon,
            'children' => $this->whenLoaded(
                'children',
                fn() => self::collection($this->children)
            ),
        ];
    }
}
