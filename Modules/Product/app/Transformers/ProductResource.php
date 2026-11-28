<?php

namespace Modules\Product\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'uuid'                 => $this->uuid,
            'name'                 => $this->name,
            'slug'                 => $this->slug,
            'sku'                  => $this->sku,
            'type'                 => $this->type,
            'price'               => $this->type === 'variable' ? null : (float) $this->price,
            'sale_price'          => $this->type === 'variable' ? null : ($this->sale_price ? (float) $this->sale_price : null),
            'current_price'       => $this->type === 'variable' ? $this->min_variant_price : (float) $this->current_price,
            'is_on_sale'          => $this->type === 'variable' ? false : $this->is_on_sale,
            'discount_percentage' => $this->type === 'variable' ? 0 : $this->discount_percentage,
            'price_range'         => $this->type === 'variable' ? [
                'min' => $this->min_variant_price,
                'max' => $this->max_variant_price,
            ] : null,
            'thumbnail_url'        => $this->thumbnail_url,
            'has_video'            => (bool) $this->has_video,
            'short_description'    => $this->short_description,
            'description'          => $this->description,
            'stock_status'         => $this->stock_status,
            'stock_quantity'       => $this->stock_quantity,
            'is_in_stock'          => $this->is_in_stock,
            'is_low_stock'         => $this->is_low_stock,
            'average_rating'       => (float) $this->average_rating,
            'total_reviews'        => $this->total_reviews,
            'total_sold'           => $this->total_sold,
            'is_featured'          => $this->is_featured,
            'is_new'               => $this->is_new,
            'is_bestseller'        => $this->is_bestseller,
            'tags'                 => $this->tags ?? [],
            'weight'               => $this->weight,
            'weight_unit'          => $this->weight_unit,
            'meta_title'           => $this->meta_title,
            'meta_description'     => $this->meta_description,
            'meta_keywords'        => $this->meta_keywords,
            'status'               => $this->status,
            'published_at'         => $this->published_at?->toDateTimeString(),
            'created_at'           => $this->created_at?->toDateTimeString(),

            // Relationships
            'category'  => $this->whenLoaded('category', fn() => [
                'id'   => $this->category->id,
                'uuid' => $this->category->uuid,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'brand'     => $this->whenLoaded('brand', fn() => [
                'id'       => $this->brand->id,
                'uuid'     => $this->brand->uuid,
                'name'     => $this->brand->name,
                'slug'     => $this->brand->slug,
                'logo_url' => $this->brand->logo_url,
            ]),
            'images'    => $this->whenLoaded(
                'images',
                fn() =>
                $this->images->map(fn($img) => [
                    'id'         => $img->id,
                    'url'        => $img->image_url,
                    'alt_text'   => $img->alt_text,
                    'is_primary' => $img->is_primary,
                    'sort_order' => $img->sort_order,
                ])
            ),
            'videos'    => $this->whenLoaded(
                'videos',
                fn() =>
                $this->videos->map(fn($v) => [
                    'id'                => $v->id,
                    'uuid'              => $v->uuid,
                    'source_type'       => $v->source_type,
                    'title'             => $v->title,
                    'video_path'        => $v->video_path,
                    'video_url'         => $v->video_url,
                    'external_url'      => $v->external_url,
                    'embed_id'          => $v->embed_id,
                    'embed_url'         => $v->embed_url,
                    'hls_url'           => $v->hls_url,
                    'poster_path'       => $v->poster_path,
                    'poster_url'        => $v->poster_url,
                    'mime_type'         => $v->mime_type,
                    'file_size'         => $v->file_size,
                    'duration_seconds'  => $v->duration_seconds,
                    'width'             => $v->width,
                    'height'            => $v->height,
                    'processing_status' => $v->processing_status,
                    'is_primary'        => $v->is_primary,
                    'sort_order'        => $v->sort_order,
                ])
            ),
            'attributes' => $this->whenLoaded(
                'attributes',
                fn() =>
                $this->attributes->map(fn($attr) => [
                    'id'     => $attr->id,
                    'name'   => $attr->name,
                    'values' => $attr->values->map(fn($v) => [
                        'id'         => $v->id,
                        'value'      => $v->value,
                        'color_code' => $v->color_code,
                    ]),
                ])
            ),
            'variants'   => $this->whenLoaded(
                'variants',
                fn() =>
                $this->variants->map(fn($v) => [
                    'id'             => $v->id,
                    'sku'            => $v->sku,
                    'attributes'     => $v->attributes,
                    'price'          => (float) $v->price,
                    'sale_price'     => $v->sale_price ? (float) $v->sale_price : null,
                    'current_price'  => (float) $v->current_price,
                    'stock_quantity' => $v->stock_quantity,
                    'is_in_stock'    => $v->is_in_stock,
                    'image_url'      => $v->image_url,
                ])
            ),
            // 'reviews'    => $this->whenLoaded(
            //     'reviews',
            //     fn() =>
            //     ReviewResource::collection($this->reviews)
            // ),
        ];
    }
}

