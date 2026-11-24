<?php

namespace Modules\Catalog\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Category;
use Tests\TestCase;

class CategoryCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_create_and_update_work_with_database_cache_store(): void
    {
        config(['cache.default' => 'database']);

        $category = Category::create([
            'name' => 'Original category',
            'slug' => 'original-category',
            'is_active' => true,
        ]);

        $category->update(['name' => 'Updated category']);

        $this->assertSame('Updated category', $category->fresh()->name);
    }
}
