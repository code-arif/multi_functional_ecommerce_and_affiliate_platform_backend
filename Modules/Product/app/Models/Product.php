<?php

namespace Modules\Product\Models;

use Modules\Cart\Models\Wishlist;
use Modules\Catalog\Models\Brand;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Traits\HasSlug;
use Modules\Reviews\Models\Review;
use Modules\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Core\Traits\HasUuid;

class Product extends Model
{
    use HasUuid;
    use HasSlug, SoftDeletes;

    protected $table = 'products';

    /**
     * Explicit lightweight columns for high-scale catalog listing queries (excludes longText/SEO blobs).
     */
    public const LIST_COLUMNS = [
        'id',
        'uuid',
        'category_id',
        'brand_id',
        'name',
        'slug',
        'sku',
        'type',
        'price',
        'sale_price',
        'effective_price',
        'min_price',
        'max_price',
        'stock_quantity',
        'total_stock',
        'stock_status',
        'thumbnail',
        'has_video',
        'average_rating',
        'total_reviews',
        'total_sold',
        'is_featured',
        'is_new',
        'is_bestseller',
        'status',
        'created_at',
    ];

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'sku',
        'type',
        'price',
        'sale_price',
        'cost_price',
        'effective_price',
        'min_price',
        'max_price',
        'stock_quantity',
        'total_stock',
        'low_stock_threshold',
        'manage_stock',
        'stock_status',
        'short_description',
        'description',
        'thumbnail',
        'has_video',
        'average_rating',
        'total_reviews',
        'total_sold',
        'weight',
        'weight_unit',
        'tags',
        'is_featured',
        'is_new',
        'is_bestseller',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'status',
        'published_at',
    ];

    protected $casts = [
        'price'               => 'decimal:2',
        'sale_price'          => 'decimal:2',
        'cost_price'          => 'decimal:2',
        'effective_price'     => 'decimal:2',
        'min_price'           => 'decimal:2',
        'max_price'           => 'decimal:2',
        'total_stock'         => 'integer',
        'has_video'           => 'boolean',
        'average_rating'      => 'decimal:2',
        'manage_stock'        => 'boolean',
        'is_featured'         => 'boolean',
        'is_new'              => 'boolean',
        'is_bestseller'       => 'boolean',
        'tags'                => 'array',
        'published_at'        => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if ($product->type !== 'variable') {
                $effective = (float) ($product->sale_price ?? $product->price ?? 0);
                $product->effective_price = $effective;
                $product->min_price = $effective;
                $product->max_price = $effective;
                $product->total_stock = (int) ($product->stock_quantity ?? 0);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(ProductVideo::class)->orderBy('sort_order');
    }

    public function primaryVideo(): HasOne
    {
        return $this->hasOne(ProductVideo::class)->where('is_primary', true);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->where('is_active', true);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->where('status', 'approved');
    }

    public function allReviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function wishlistedByUsers(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Vendors selling this product at their own prices (SRS Section 4.5).
     */
    public function vendorProductPrices(): HasMany
    {
        return $this->hasMany(VendorProductPrice::class);
    }

    /**
     * Active vendors who sell this product.
     */
    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(\Modules\Vendor\Models\Vendor::class, 'vendor_product_prices')
            ->withPivot(['price', 'sale_price', 'stock_quantity', 'is_active'])
            ->wherePivot('is_active', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Products pending admin approval.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Products that need admin attention (pending review).
     */
    public function scopeNeedsApproval($query)
    {
        return $query->whereIn('status', ['pending']);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true)->where('status', 'active');
    }

    public function scopeNew($query)
    {
        return $query->where('is_new', true)->where('status', 'active');
    }

    public function scopeBestseller($query)
    {
        return $query->where('is_bestseller', true)->where('status', 'active');
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_status', 'in_stock');
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail) return null;
        return str_starts_with($this->thumbnail, 'http')
            ? $this->thumbnail
            : asset('storage/' . $this->thumbnail);
    }

    public function getCurrentPriceAttribute()
    {
        return $this->sale_price ?? $this->price;
    }

    public function getIsOnSaleAttribute(): bool
    {
        return !is_null($this->sale_price) && $this->sale_price < $this->price;
    }

    public function getDiscountPercentageAttribute(): int
    {
        if (!$this->is_on_sale) return 0;
        return (int) round((($this->price - $this->sale_price) / $this->price) * 100);
    }

    public function getIsInStockAttribute(): bool
    {
        return $this->stock_status === 'in_stock' && $this->stock_quantity > 0;
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->stock_quantity <= $this->low_stock_threshold && $this->stock_quantity > 0;
    }

    public function getMinVariantPriceAttribute(): ?float
    {
        if ($this->type !== 'variable') return null;
        if (!is_null($this->attributes['min_price'] ?? null)) {
            return (float) $this->attributes['min_price'];
        }
        $variants = $this->relationLoaded('variants')
            ? $this->variants
            : $this->variants()->get();
        if ($variants->isEmpty()) return null;
        $prices = $variants->map(fn($v) => $v->sale_price ?? $v->price);
        return (float) $prices->min();
    }

    public function getMaxVariantPriceAttribute(): ?float
    {
        if ($this->type !== 'variable') return null;
        if (!is_null($this->attributes['max_price'] ?? null)) {
            return (float) $this->attributes['max_price'];
        }
        $variants = $this->relationLoaded('variants')
            ? $this->variants
            : $this->variants()->get();
        if ($variants->isEmpty()) return null;
        $prices = $variants->map(fn($v) => $v->sale_price ?? $v->price);
        return (float) $prices->max();
    }

    public function getTotalVariantStockAttribute(): int
    {
        if ($this->type !== 'variable') return $this->stock_quantity;
        if (array_key_exists('total_stock', $this->attributes) && ($this->attributes['total_stock'] > 0 || !$this->relationLoaded('variants'))) {
            return (int) $this->attributes['total_stock'];
        }
        $variants = $this->relationLoaded('variants')
            ? $this->variants
            : $this->variants()->get();
        return (int) $variants->sum('stock_quantity');
    }

    public function getVariantIsInStockAttribute(): bool
    {
        if ($this->type !== 'variable') return $this->is_in_stock;
        if (array_key_exists('total_stock', $this->attributes) && !$this->relationLoaded('variants')) {
            return (int) $this->attributes['total_stock'] > 0;
        }
        $variants = $this->relationLoaded('variants')
            ? $this->variants
            : $this->variants()->get();
        return $variants->contains(fn($v) => $v->stock_quantity > 0);
    }

    /**
     * Recompute denormalized variant pricing and stock summaries in a single SQL aggregate.
     */
    public function syncVariantMetrics(): void
    {
        if ($this->type !== 'variable') {
            return;
        }

        $stats = $this->variants()
            ->selectRaw('MIN(COALESCE(sale_price, price)) as min_p, MAX(COALESCE(sale_price, price)) as max_p, COALESCE(SUM(stock_quantity), 0) as total_s')
            ->first();

        $minPrice = $stats && $stats->min_p !== null ? (float) $stats->min_p : null;
        $maxPrice = $stats && $stats->max_p !== null ? (float) $stats->max_p : null;
        $totalStock = (int) ($stats->total_s ?? 0);

        $this->updateQuietly([
            'min_price'       => $minPrice,
            'max_price'       => $maxPrice,
            'effective_price' => $minPrice ?? 0,
            'total_stock'     => $totalStock,
            'stock_status'    => $totalStock > 0 ? 'in_stock' : 'out_of_stock',
        ]);
    }

    public function decrementStock(int $quantity): void
    {
        $this->decrement('stock_quantity', $quantity);
        $this->increment('total_sold', $quantity);
        if ($this->stock_quantity <= 0) {
            $this->update(['stock_status' => 'out_of_stock']);
        }
    }

    public function recalculateRating(): void
    {
        $avg   = $this->reviews()->avg('rating') ?? 0;
        $total = $this->reviews()->count();
        $this->update([
            'average_rating' => round($avg, 2),
            'total_reviews'  => $total,
        ]);
    }

    public static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name) ?: 'product';

        $existing = static::withTrashed()
            ->where('slug', 'like', $baseSlug . '%')
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->pluck('slug')
            ->flip();

        if (!$existing->has($baseSlug)) {
            return $baseSlug;
        }

        $count = 1;
        while ($existing->has("{$baseSlug}-{$count}")) {
            $count++;
        }

        return "{$baseSlug}-{$count}";
    }

    /**
     * Approve a pending product for public listing (Product Approval Workflow).
     */
    public function approve(): void
    {
        $this->update([
            'status'       => 'active',
            'published_at' => $this->published_at ?? now(),
        ]);
    }

    /**
     * Reject a pending product.
     */
    public function reject(?string $reason = null): void
    {
        $this->update([
            'status' => 'inactive',
        ]);
    }
}
