<?php

namespace Modules\Product\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Modules\Catalog\Models\Brand;
use Modules\Catalog\Models\Category;
use Modules\Product\Models\Product;
use Modules\RBAC\Database\Seeders\RBACSeeder;
use Modules\Vendor\Models\Vendor;
use Tests\TestCase;

class ProductScaleAndVideoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Category $category;
    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RBACSeeder::class);

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('admin');

        $this->category = Category::factory()->create([
            'name'      => 'Phones',
            'slug'      => 'phones',
            'is_active' => true,
        ]);

        $this->brand = Brand::factory()->create([
            'name'      => 'TechBrand',
            'slug'      => 'techbrand',
            'is_active' => true,
        ]);
    }

    public function test_product_updates_preserve_unrelated_cache_and_variant_ids(): void
    {
        config(['cache.default' => 'file']);
        Cache::forgetDriver('file');
        Cache::put('unrelated_app_key', 'keep-me', 600);

        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'name'        => 'Modular Phone',
                'type'        => 'variable',
                'status'      => 'active',
                'category_id' => $this->category->id,
                'brand_id'    => $this->brand->id,
                'attributes'  => [
                    ['name' => 'Storage', 'values' => [['value' => '128GB'], ['value' => '256GB']]],
                ],
                'variants'    => [
                    ['attributes' => ['Storage' => '128GB'], 'price' => 500, 'stock_quantity' => 5, 'sku' => 'MP-128'],
                    ['attributes' => ['Storage' => '256GB'], 'price' => 650, 'stock_quantity' => 8, 'sku' => 'MP-256'],
                ],
            ])
            ->assertStatus(201);

        $this->assertEquals('keep-me', Cache::get('unrelated_app_key'));

        $productId = $create->json('data.id');
        $productUuid = $create->json('data.uuid');
        $firstVariantId = $create->json('data.variants.0.id');

        // Denormalized metrics should be computed from variants
        $product = Product::findOrFail($productId);
        $this->assertEquals(500.00, (float) $product->min_price);
        $this->assertEquals(650.00, (float) $product->max_price);
        $this->assertEquals(13, $product->total_stock);

        // Update existing variant by uuid: variant ID must stay intact while stock updates
        $update = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/products/{$productUuid}", [
                'variants' => [
                    ['id' => $firstVariantId, 'attributes' => ['Storage' => '128GB'], 'price' => 480, 'stock_quantity' => 10, 'sku' => 'MP-128'],
                ],
            ])
            ->assertOk();

        $this->assertEquals($firstVariantId, $update->json('data.variants.0.id'));
        $this->assertEquals(480.00, (float) $product->fresh()->min_price);
        $this->assertEquals(10, $product->fresh()->total_stock);
    }

    public function test_storefront_supports_cursor_pagination_and_related_without_rand_error(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            Product::factory()->create([
                'category_id' => $this->category->id,
                'brand_id'    => $this->brand->id,
                'name'        => "Scale Item {$i}",
                'slug'        => "scale-item-{$i}",
                'price'       => 100 * $i,
                'status'      => 'active',
            ]);
        }

        $cursorRes = $this->getJson('/api/v1/products?pagination=cursor&per_page=2&category=phones&sort=price_asc')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'pagination' => ['per_page', 'next_cursor', 'prev_cursor', 'has_more'],
            ]);

        $this->assertTrue($cursorRes->json('pagination.has_more'));

        $this->getJson('/api/v1/products/scale-item-1/related')
            ->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_admin_and_vendor_can_upload_and_attach_product_videos(): void
    {
        Storage::fake('public');

        $videoFile = UploadedFile::fake()->create('demo.mp4', 512, 'video/mp4');
        $uploadRes = $this->actingAs($this->admin, 'sanctum')
            ->post('/api/v1/admin/products/upload-video', [
                'video' => $videoFile,
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['path', 'url', 'mime_type', 'file_size']]);

        $createRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'name'           => 'Cinema Camera',
                'type'           => 'simple',
                'price'          => 1500,
                'stock_quantity' => 4,
                'status'         => 'active',
                'videos'         => [
                    [
                        'source_type' => 'upload',
                        'title'       => 'Unboxing Clip',
                        'video_path'  => $uploadRes->json('data.path'),
                        'mime_type'   => 'video/mp4',
                        'file_size'   => 524288,
                        'is_primary'  => true,
                    ],
                    [
                        'source_type'  => 'youtube',
                        'title'        => 'Official Trailer',
                        'external_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'is_primary'   => false,
                    ],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.has_video', true)
            ->assertJsonCount(2, 'data.videos')
            ->assertJsonPath('data.videos.1.embed_id', 'dQw4w9WgXcQ');

        $productId = $createRes->json('data.id');
        $this->assertEquals('ready', $createRes->json('data.videos.0.processing_status'));

        // Vendor video upload + product creation
        $vendorUser = User::factory()->create(['status' => 'active']);
        $vendorUser->assignRole('vendor');
        Vendor::factory()->create([
            'user_id'   => $vendorUser->id,
            'shop_name' => 'Studio Shop',
            'slug'      => 'studio-shop',
            'status'    => 'active',
        ]);

        $vendorUpload = $this->actingAs($vendorUser, 'sanctum')
            ->post('/api/v1/vendor/products/upload-video', [
                'video' => UploadedFile::fake()->create('vendor-clip.mp4', 256, 'video/mp4'),
            ])
            ->assertOk();

        $this->actingAs($vendorUser, 'sanctum')
            ->postJson('/api/v1/vendor/products', [
                'name'           => 'Vendor Tripod',
                'price'          => 95,
                'stock_quantity' => 12,
                'videos'         => [
                    [
                        'source_type' => 'upload',
                        'title'       => 'Vendor Setup',
                        'video_path'  => $vendorUpload->json('data.path'),
                        'is_primary'  => true,
                    ],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.has_video', true);
    }
}
