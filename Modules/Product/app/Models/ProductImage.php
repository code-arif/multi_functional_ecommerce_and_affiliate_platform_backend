<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Traits\HasUuid;

class ProductImage extends Model
{
    use HasUuid;
    protected $fillable = ['product_id', 'image_path', 'alt_text', 'is_primary', 'sort_order'];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getImageUrlAttribute(): string
    {
        return str_starts_with($this->image_path, 'http')
            ? $this->image_path
            : asset('storage/' . $this->image_path);
    }
}
