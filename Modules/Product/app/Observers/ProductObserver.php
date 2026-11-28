<?php

namespace Modules\Product\Observers;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use Modules\Product\Models\Product;

class ProductObserver
{
    public function created(Product $product): void
    {
        $this->invalidateProductCache($product);
    }

    public function updated(Product $product): void
    {
        $this->invalidateProductCache($product);
    }

    public function deleted(Product $product): void
    {
        $this->invalidateProductCache($product);
    }

    public function restored(Product $product): void
    {
        $this->invalidateProductCache($product);
    }

    public function forceDeleted(Product $product): void
    {
        $this->invalidateProductCache($product);
    }

    private function invalidateProductCache(Product $product): void
    {
        Cache::forget("product_{$product->slug}");

        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(['products'])->flush();
            return;
        }

        // Bump version key so cached lists expire in O(1) without Cache::flush()
        Cache::has('products_cache_version')
            ? Cache::increment('products_cache_version')
            : Cache::forever('products_cache_version', 2);
    }
}

