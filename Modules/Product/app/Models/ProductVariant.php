<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Traits\HasUuid;

class ProductVariant extends Model
{
    use HasUuid;
    protected $fillable = [
        'product_id',
        'sku',
        'name',
        'attributes',
        'price',
        'sale_price',
        'stock_quantity',
        'weight',
        'image',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'attributes'     => 'array',
        'price'          => 'decimal:2',
        'sale_price'     => 'decimal:2',
        'stock_quantity' => 'integer',
        'weight'         => 'decimal:2',
        'is_active'      => 'boolean',
        'sort_order'     => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getCurrentPriceAttribute(): float
    {
        return (float) ($this->sale_price ?? $this->price);
    }

    public function getIsInStockAttribute(): bool
    {
        return $this->is_active && $this->stock_quantity > 0;
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) return null;
        return str_starts_with($this->image, 'http') ? $this->image : asset('storage/' . $this->image);
    }
}

