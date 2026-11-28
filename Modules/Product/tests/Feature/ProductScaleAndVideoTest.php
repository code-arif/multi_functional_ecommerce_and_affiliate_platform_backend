<?php

namespace Modules\Product\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Modules\Catalog\Models\Brand;
use Modules\Catalog\Models\Category;
use Modules\Notifications\Notifications\ProductStatusNotification;
use Modules\Notifications\Notifications\ProductSubmittedNotification;
use Modules\Product\Emails\NewProductAdminAlertMail;
use Modules\Product\Emails\ProductStatusVendorMail;
use Modules\Product\Emails\VendorProductUpdatedAdminMail;
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

    public function test_product_hybrid_ownership_and_audit_trail(): void
    {
        $vendorUserA = User::factory()->create(['status' => 'active', 'name' => 'Seller Alpha']);
        $vendorUserA->assignRole('vendor');
        $vendorA = Vendor::factory()->create([
            'user_id'   => $vendorUserA->id,
            'shop_name' => 'Alpha Store',
            'slug'      => 'alpha-store',
            'status'    => 'active',
        ]);

        $vendorUserB = User::factory()->create(['status' => 'active', 'name' => 'Seller Beta']);
        $vendorUserB->assignRole('vendor');
        $vendorB = Vendor::factory()->create([
            'user_id'   => $vendorUserB->id,
            'shop_name' => 'Beta Store',
            'slug'      => 'beta-store',
            'status'    => 'active',
        ]);

        // 1. Admin creates a platform catalog product
        $platformRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'name'           => 'Platform Shared Headphones',
                'type'           => 'simple',
                'price'          => 150,
                'stock_quantity' => 40,
                'status'         => 'active',
                'category_id'    => $this->category->id,
                'brand_id'       => $this->brand->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.owner_type', 'platform')
            ->assertJsonPath('data.created_by', $this->admin->id)
            ->assertJsonPath('data.updated_by', $this->admin->id)
            ->assertJsonPath('data.creator.id', $this->admin->id);

        $platformUuid = $platformRes->json('data.uuid');
        $platformProduct = Product::where('uuid', $platformUuid)->firstOrFail();
        $this->assertNull($platformProduct->vendor_id);

        // 2. Vendor A creates an exclusive vendor-owned product
        $vendorCreate = $this->actingAs($vendorUserA, 'sanctum')
            ->postJson('/api/v1/vendor/products', [
                'name'              => 'Alpha Exclusive Charger',
                'price'             => 45,
                'stock_quantity'    => 20,
                'short_description' => 'Fast 65W charger',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.owner_type', 'vendor')
            ->assertJsonPath('data.is_owner', true)
            ->assertJsonPath('data.vendor.uuid', $vendorA->uuid)
            ->assertJsonPath('data.created_by', $vendorUserA->id);

        $vendorProductUuid = $vendorCreate->json('data.uuid');
        $vendorProduct = Product::where('uuid', $vendorProductUuid)->firstOrFail();
        $this->assertEquals($vendorA->id, $vendorProduct->vendor_id);

        // 3. Admin listing filters cleanly by owner_type and vendor_uuid
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/products?owner_type=platform')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $platformUuid);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/admin/products?owner_type=vendor&vendor_uuid={$vendorA->uuid}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $vendorProductUuid);

        // 4. Vendor A can edit their own exclusive product's master fields + pricing
        $this->actingAs($vendorUserA, 'sanctum')
            ->putJson("/api/v1/vendor/products/{$vendorProductUuid}", [
                'name'           => 'Alpha Exclusive GaN Charger',
                'price'          => 55,
                'stock_quantity' => 15,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_owner', true)
            ->assertJsonPath('data.name', 'Alpha Exclusive GaN Charger')
            ->assertJsonPath('data.vendor_pricing.price', '55.00');

        $this->assertEquals('Alpha Exclusive GaN Charger', $vendorProduct->fresh()->name);
        $this->assertEquals($vendorUserA->id, $vendorProduct->fresh()->updated_by);

        // 5. Vendor B cannot link, update, or delete Vendor A's exclusive product
        $this->actingAs($vendorUserB, 'sanctum')
            ->postJson('/api/v1/vendor/products', [
                'product_uuid'   => $vendorProductUuid,
                'price'          => 49,
                'stock_quantity' => 5,
            ])
            ->assertStatus(403);

        $this->actingAs($vendorUserB, 'sanctum')
            ->putJson("/api/v1/vendor/products/{$vendorProductUuid}", ['price' => 40])
            ->assertStatus(403);

        $this->actingAs($vendorUserB, 'sanctum')
            ->deleteJson("/api/v1/vendor/products/{$vendorProductUuid}")
            ->assertStatus(403);

        // 6. Vendor B links to platform catalog product and can only update pricing/stock
        $this->actingAs($vendorUserB, 'sanctum')
            ->postJson('/api/v1/vendor/products', [
                'product_uuid'   => $platformUuid,
                'price'          => 139,
                'stock_quantity' => 8,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_owner', false)
            ->assertJsonPath('data.vendor_pricing.price', '139.00');

        $this->actingAs($vendorUserB, 'sanctum')
            ->putJson("/api/v1/vendor/products/{$platformUuid}", [
                'name'  => 'Attempted Master Hijack',
                'price' => 129,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_owner', false)
            ->assertJsonPath('data.vendor_pricing.price', '129.00');

        $this->assertEquals('Platform Shared Headphones', $platformProduct->fresh()->name);

        // 7. Vendor A deletes their owned product -> soft-deleted
        $this->actingAs($vendorUserA, 'sanctum')
            ->deleteJson("/api/v1/vendor/products/{$vendorProductUuid}")
            ->assertOk();

        $this->assertSoftDeleted('products', ['id' => $vendorProduct->id]);

        // 8. Admin creates a product on behalf of Vendor A (via vendor_uuid) and searches vendors by owner/shop
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/vendors?search=Seller%20Alpha')
            ->assertOk()
            ->assertJsonPath('data.0.uuid', $vendorA->uuid);

        $adminForVendorRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'name'           => 'Admin Assisted Alpha Dock',
                'type'           => 'simple',
                'price'          => 89,
                'stock_quantity' => 15,
                'status'         => 'active',
                'vendor_uuid'    => $vendorA->uuid,
                'category_id'    => $this->category->id,
                'brand_id'       => $this->brand->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.owner_type', 'vendor')
            ->assertJsonPath('data.vendor.uuid', $vendorA->uuid);

        $adminForVendorUuid = $adminForVendorRes->json('data.uuid');
        $adminForVendorId = $adminForVendorRes->json('data.id');

        $this->assertDatabaseHas('vendor_product_prices', [
            'vendor_id'  => $vendorA->id,
            'product_id' => $adminForVendorId,
            'is_active'  => true,
        ]);

        // Vendor A sees the admin-assisted product in their shop immediately with is_owner = true
        $this->actingAs($vendorUserA, 'sanctum')
            ->getJson("/api/v1/vendor/products/{$adminForVendorUuid}")
            ->assertOk()
            ->assertJsonPath('data.is_owner', true)
            ->assertJsonPath('data.vendor.uuid', $vendorA->uuid);

        // Admin clearing vendor_uuid reassigns product back to Platform Catalog
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/products/{$adminForVendorUuid}", [
                'vendor_uuid' => '',
            ])
            ->assertOk()
            ->assertJsonPath('data.owner_type', 'platform');
    }

    public function test_vendor_variable_product_starts_inactive_and_notifies_admins(): void
    {
        Mail::fake();

        $vendorUser = User::factory()->create(['status' => 'active', 'name' => 'Vendor Creator']);
        $vendorUser->assignRole('vendor');
        $vendor = Vendor::factory()->create([
            'user_id'   => $vendorUser->id,
            'shop_name' => 'Nova Gadget Shop',
            'slug'      => 'nova-gadget-shop',
            'email'     => 'nova@example.com',
            'status'    => 'active',
        ]);

        // Vendor creates a variable product with rich HTML description
        $response = $this->actingAs($vendorUser, 'sanctum')
            ->postJson('/api/v1/vendor/products', [
                'type'              => 'variable',
                'name'              => 'Nova Smart Watch',
                'short_description' => 'AMOLED fitness watch',
                'description'       => '<p><strong>Waterproof</strong> up to 50m.</p>',
                'category_uuid'     => $this->category->uuid,
                'brand_uuid'        => $this->brand->uuid,
                'attributes'        => [
                    ['name' => 'Color', 'values' => [['value' => 'Black'], ['value' => 'Silver']]],
                ],
                'variants'          => [
                    ['attributes' => ['Color' => 'Black'], 'price' => 120, 'stock_quantity' => 10, 'sku' => 'NSW-BLK'],
                    ['attributes' => ['Color' => 'Silver'], 'price' => 135, 'stock_quantity' => 5, 'sku' => 'NSW-SLV'],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.type', 'variable')
            ->assertJsonCount(2, 'data.variants');

        $productUuid = $response->json('data.uuid');
        $product = Product::where('uuid', $productUuid)->firstOrFail();

        $this->assertEquals('inactive', $product->status);
        $this->assertEquals(120.00, (float) $product->min_price);
        $this->assertEquals(135.00, (float) $product->max_price);
        $this->assertEquals(15, $product->total_stock);

        // In-app database notification stored for admin (matching Category & Brand workflow)
        $this->assertEquals(
            1,
            $this->admin->notifications()->where('type', ProductSubmittedNotification::class)->count()
        );

        // Queued admin email alert using emails.admin.new-product-alert template
        Mail::assertQueued(NewProductAdminAlertMail::class, function (NewProductAdminAlertMail $mail) use ($product) {
            return $mail->hasTo($this->admin->email)
                && $mail->product->id === $product->id
                && str_contains($mail->render(), $product->name);
        });

        // Admin single status change API transitions active/inactive/draft and notifies vendor in-app + email
        foreach (['active', 'inactive', 'draft'] as $newStatus) {
            $this->actingAs($this->admin, 'sanctum')
                ->patchJson("/api/v1/admin/products/{$productUuid}/status", [
                    'status' => $newStatus,
                    'reason' => $newStatus === 'inactive' ? 'Needs sharper photo' : null,
                ])
                ->assertOk()
                ->assertJsonPath('data.status', $newStatus);
        }

        $this->assertEquals(
            3,
            $vendorUser->notifications()->where('type', ProductStatusNotification::class)->count()
        );

        Mail::assertQueued(ProductStatusVendorMail::class, 3);
        Mail::assertQueued(ProductStatusVendorMail::class, function (ProductStatusVendorMail $mail) use ($vendor, $product) {
            return $mail->hasTo($vendor->email)
                && $mail->product->id === $product->id
                && str_contains($mail->render(), $product->name);
        });

        // Option 1 (Daraz-style Smart Re-Approval):
        // Activate a simple vendor product, then test pricing-only vs content edit
        $simpleRes = $this->actingAs($vendorUser, 'sanctum')
            ->postJson('/api/v1/vendor/products', [
                'type'           => 'simple',
                'name'           => 'Nova Wireless Mouse',
                'price'          => 35,
                'stock_quantity' => 50,
            ])
            ->assertStatus(201);

        $simpleUuid = $simpleRes->json('data.uuid');
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/products/{$simpleUuid}/status", ['status' => 'active'])
            ->assertOk();

        // Pricing-only update (resending unchanged name) keeps status = active & queues dedicated update mail
        $this->actingAs($vendorUser, 'sanctum')
            ->putJson("/api/v1/vendor/products/{$simpleUuid}", [
                'name'           => 'Nova Wireless Mouse',
                'price'          => 29,
                'stock_quantity' => 45,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.reapproval_required', false);

        Mail::assertQueued(VendorProductUpdatedAdminMail::class, function (VendorProductUpdatedAdminMail $mail) use ($simpleUuid) {
            return $mail->requiresReapproval === false
                && $mail->product->uuid === $simpleUuid
                && str_contains($mail->render(), 'Vendor Updated Product');
        });

        // Content edit resets status -> inactive and triggers admin re-approval notification + update mail
        $this->actingAs($vendorUser, 'sanctum')
            ->putJson("/api/v1/vendor/products/{$simpleUuid}", [
                'name'  => 'Nova Pro Wireless Mouse',
                'price' => 29,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.reapproval_required', true);

        Mail::assertQueued(VendorProductUpdatedAdminMail::class, function (VendorProductUpdatedAdminMail $mail) use ($simpleUuid) {
            return $mail->requiresReapproval === true
                && $mail->product->uuid === $simpleUuid
                && str_contains($mail->render(), 'Re-approval Needed');
        });
    }

    public function test_vendor_can_upload_gallery_images_and_edit_gallery(): void
    {
        Storage::fake('public');
        Mail::fake();

        $vendorUser = User::factory()->create(['status' => 'active', 'name' => 'Gallery Seller']);
        $vendorUser->assignRole('vendor');
        Vendor::factory()->create([
            'user_id'   => $vendorUser->id,
            'shop_name' => 'Gallery Shop',
            'slug'      => 'gallery-shop',
            'email'     => 'gallery@example.com',
            'status'    => 'active',
        ]);

        $img1 = $this->actingAs($vendorUser, 'sanctum')
            ->post('/api/v1/vendor/products/upload-image', [
                'image' => UploadedFile::fake()->image('front.jpg', 800, 800),
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['path', 'url']]);

        $img2 = $this->actingAs($vendorUser, 'sanctum')
            ->post('/api/v1/vendor/products/upload-image', [
                'image' => UploadedFile::fake()->image('side.jpg', 800, 800),
            ])
            ->assertOk();

        $created = $this->actingAs($vendorUser, 'sanctum')
            ->postJson('/api/v1/vendor/products', [
                'type'           => 'simple',
                'name'           => 'Gallery Studio Lamp',
                'price'          => 65,
                'stock_quantity' => 12,
                'images'         => [
                    ['path' => $img1->json('data.path'), 'is_primary' => true, 'sort_order' => 0],
                    ['path' => $img2->json('data.path'), 'is_primary' => false, 'sort_order' => 1],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonCount(2, 'data.images')
            ->assertJsonPath('data.images.0.image_path', $img1->json('data.path'));

        $uuid = $created->json('data.uuid');
        $firstImageId = $created->json('data.images.0.id');

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/products/{$uuid}/status", ['status' => 'active'])
            ->assertOk();

        // Removing the second gallery image resets active product for admin re-approval
        $this->actingAs($vendorUser, 'sanctum')
            ->putJson("/api/v1/vendor/products/{$uuid}", [
                'name'   => 'Gallery Studio Lamp',
                'price'  => 65,
                'images' => [
                    ['id' => $firstImageId, 'path' => $img1->json('data.path'), 'is_primary' => true, 'sort_order' => 0],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data.images')
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.reapproval_required', true);
    }
}
