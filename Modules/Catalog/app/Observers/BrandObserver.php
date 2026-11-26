<?php

namespace Modules\Catalog\Observers;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use Modules\Catalog\Models\Brand;

class BrandObserver
{
    public function created(Brand $brand): void
    {
        $this->flushBrandCache();
    }

    public function updated(Brand $brand): void
    {
        $this->flushBrandCache();
    }

    public function deleted(Brand $brand): void
    {
        $this->flushBrandCache();
    }

    public function restored(Brand $brand): void
    {
        $this->flushBrandCache();
    }

    public function forceDeleted(Brand $brand): void
    {
        $this->flushBrandCache();
    }

    private function flushBrandCache(): void
    {
        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(['brands'])->flush();
        }
    }
}
