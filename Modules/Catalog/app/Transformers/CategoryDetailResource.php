<?php

namespace Modules\Catalog\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Product\Transformers\ProductListResource;

class CategoryDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        $productsPaginator = $this->resource->getAttribute('detail_products');
        $filtersMeta = $this->resource->getAttribute('detail_filters') ?? [
            'brands'      => [],
            'price_range' => ['min' => 0, 'max' => 0],
        ];

        return [
            'uuid'             => $this->uuid,
            'name'             => $this->name,
            'slug'             => $this->slug,
            'description'      => $this->description,
            'image_url'        => $this->image_url,
            'banner_url'       => $this->banner_url,
            'icon'             => $this->icon,
            'meta_title'       => $this->meta_title,
            'meta_description' => $this->meta_description,
            'products_count'   => $productsPaginator ? $productsPaginator->total() : ($this->whenCounted('products') ?? 0),
            'parent'           => $this->whenLoaded('parent', fn() => $this->parent ? [
                'uuid' => $this->parent->uuid,
                'name' => $this->parent->name,
                'slug' => $this->parent->slug,
            ] : null),
            'children'         => $this->whenLoaded('children', fn() => $this->children->map(fn($child) => [
                'uuid'           => $child->uuid,
                'name'           => $child->name,
                'slug'           => $child->slug,
                'image_url'      => $child->image_url,
                'icon'           => $child->icon,
                'products_count' => (int) ($child->products_count ?? 0),
            ])->values()),
            'filters'          => $filtersMeta,
            'products'         => $productsPaginator ? [
                'items'      => ProductListResource::collection($productsPaginator->getCollection()),
                'pagination' => [
                    'total'        => $productsPaginator->total(),
                    'per_page'     => $productsPaginator->perPage(),
                    'current_page' => $productsPaginator->currentPage(),
                    'last_page'    => $productsPaginator->lastPage(),
                    'from'         => $productsPaginator->firstItem(),
                    'to'           => $productsPaginator->lastItem(),
                ],
            ] : [
                'items'      => [],
                'pagination' => null,
            ],
        ];
    }
}
