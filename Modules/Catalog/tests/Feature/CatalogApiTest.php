<?php

namespace Modules\Catalog\Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Brand;
use Modules\Notifications\Notifications\BrandProposedNotification;
use Modules\Notifications\Notifications\BrandStatusNotification;
use Modules\Notifications\Notifications\CategoryProposedNotification;
use Modules\Notifications\Notifications\CategoryStatusNotification;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;
use Modules\Product\Models\Product;
use Modules\RBAC\Database\Seeders\RBACSeeder;
use Modules\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Category $category;
    private Brand $brand;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name'   => 'Test Customer',
            'email'  => 'customer@example.com',
            'status' => 'active',
        ]);

        $this->category = Category::factory()->create([
            'name'      => 'Electronics',
            'slug'      => 'electronics',
            'is_active' => true,
        ]);

        $this->brand = Brand::factory()->create([
            'name'      => 'Apple',
            'slug'      => 'apple',
            'is_active' => true,
        ]);

        $this->product = Product::factory()->create([
            'category_id' => $this->category->id,
            'brand_id'    => $this->brand->id,
            'name'        => 'iPhone 15 Pro',
            'slug'        => 'iphone-15-pro',
            'sku'         => 'IP15P-256',
            'type'        => 'simple',
            'price'       => 1199.00,
            'status'      => 'active',
        ]);
    }

    // ─── Categories ───────────────────────────────────────────────

    /** @test */
    public function guest_can_list_categories(): void
    {
        $child = Category::factory()->create([
            'parent_id' => $this->category->id,
            'name'      => 'Laptops',
            'slug'      => 'laptops',
            'is_active' => true,
            'status'    => 'approved',
        ]);

        Category::factory()->create([
            'parent_id' => $this->category->id,
            'name'      => 'Pending Child',
            'slug'      => 'pending-child',
            'is_active' => true,
            'status'    => 'pending',
        ]);

        Category::factory()->create([
            'name'      => 'Pending Root',
            'slug'      => 'pending-root',
            'is_active' => true,
            'status'    => 'pending',
        ]);

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'data.0.children')
            ->assertJsonPath('data.0.children.0.uuid', $child->uuid)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['uuid', 'name', 'slug', 'children'],
                ],
            ]);

        // Dedicated lightweight navigation endpoint returns only nav fields
        $navResponse = $this->getJson('/api/v1/categories/navigation');

        $navResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'data.0.children')
            ->assertJsonPath('data.0.children.0.uuid', $child->uuid)
            ->assertJsonMissingPath('data.0.banner_url')
            ->assertJsonMissingPath('data.0.meta_title')
            ->assertJsonMissingPath('data.0.created_by')
            ->assertJsonMissingPath('data.0.children.0.banner_url')
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'uuid',
                        'name',
                        'slug',
                        'icon',
                        'children' => [
                            '*' => ['uuid', 'name', 'slug', 'icon'],
                        ],
                    ],
                ],
            ]);
    }

    /** @test */
    public function guest_can_list_top_16_popular_categories_ordered_by_max_orders(): void
    {
        // Create 17 extra approved active categories (18 total with $this->category)
        $createdCategories = [];
        for ($i = 1; $i <= 17; $i++) {
            $createdCategories[] = Category::factory()->create([
                'name'       => "Popular Cat {$i}",
                'slug'       => "popular-cat-{$i}",
                'is_active'  => true,
                'status'     => 'approved',
                'sort_order' => $i,
            ]);
        }

        // Hidden / pending categories must never be returned
        Category::factory()->create(['name' => 'Inactive Cat', 'slug' => 'inactive-cat', 'is_active' => false, 'status' => 'approved']);
        Category::factory()->create(['name' => 'Pending Cat', 'slug' => 'pending-cat', 'is_active' => true, 'status' => 'pending']);

        $topCategory = $createdCategories[4];
        $secondCategory = $createdCategories[9];

        $topProduct = Product::factory()->create(['category_id' => $topCategory->id, 'status' => 'active', 'slug' => 'top-prod']);
        $secondProduct = Product::factory()->create(['category_id' => $secondCategory->id, 'status' => 'active', 'slug' => 'second-prod']);

        $createOrder = function (Product $prod, string $status = 'delivered', int $seq = 1): void {
            $order = Order::create([
                'order_number'          => "ORD-POP-{$prod->id}-{$seq}",
                'user_id'               => $this->user->id,
                'status'                => $status,
                'subtotal'              => 100,
                'total_amount'          => 100,
                'shipping_name'         => 'Customer',
                'shipping_phone'        => '01700000000',
                'shipping_address_line1'=> 'Road 1',
                'shipping_city'         => 'Dhaka',
            ]);
            OrderItem::create([
                'order_id'     => $order->id,
                'product_id'   => $prod->id,
                'product_name' => $prod->name,
                'unit_price'   => 100,
                'quantity'     => 1,
                'subtotal'     => 100,
            ]);
        };

        // 3 valid orders for topCategory, 2 valid + 1 cancelled for secondCategory
        $createOrder($topProduct, 'delivered', 1);
        $createOrder($topProduct, 'confirmed', 2);
        $createOrder($topProduct, 'pending', 3);

        $createOrder($secondProduct, 'delivered', 4);
        $createOrder($secondProduct, 'processing', 5);
        $createOrder($secondProduct, 'cancelled', 6);

        $res = $this->getJson('/api/v1/categories/popular');

        $res->assertOk()
            ->assertJsonCount(16, 'data')
            ->assertJsonPath('data.0.uuid', $topCategory->uuid)
            ->assertJsonPath('data.0.orders_count', 3)
            ->assertJsonPath('data.1.uuid', $secondCategory->uuid)
            ->assertJsonPath('data.1.orders_count', 2)
            ->assertJsonMissingPath('data.0.banner_url')
            ->assertJsonMissingPath('data.0.meta_title')
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['uuid', 'name', 'slug', 'image_url', 'icon', 'orders_count'],
                ],
            ]);
    }

    /** @test */
    public function guest_can_view_category_by_slug(): void
    {
        $response = $this->getJson("/api/v1/categories/{$this->category->slug}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Electronics');
    }

    /** @test */
    public function guest_gets_404_for_inactive_category(): void
    {
        $inactive = Category::factory()->create([
            'name'      => 'Hidden',
            'slug'      => 'hidden',
            'is_active' => false,
        ]);

        $response = $this->getJson("/api/v1/categories/{$inactive->slug}");

        $response->assertStatus(404);
    }

    // ─── Products ─────────────────────────────────────────────────

    /** @test */
    public function guest_can_list_active_products(): void
    {
        Product::factory()->create(['status' => 'inactive', 'slug' => 'hidden-product']);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    /** @test */
    public function guest_can_view_product_by_slug(): void
    {
        $response = $this->getJson("/api/v1/products/{$this->product->slug}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'iPhone 15 Pro');
    }

    /** @test */
    public function guest_gets_404_for_inactive_product(): void
    {
        $inactive = Product::factory()->create([
            'status' => 'inactive',
            'slug'   => 'inactive-product',
        ]);

        $response = $this->getJson("/api/v1/products/{$inactive->slug}");

        $response->assertStatus(404);
    }

    /** @test */
    public function guest_can_view_featured_products(): void
    {
        $response = $this->getJson('/api/v1/products/featured');

        $response->assertOk();
    }

    /** @test */
    public function guest_can_view_new_arrivals(): void
    {
        $response = $this->getJson('/api/v1/products/new-arrivals');

        $response->assertOk();
    }

    /** @test */
    public function guest_can_view_bestsellers(): void
    {
        $response = $this->getJson('/api/v1/products/bestsellers');

        $response->assertOk();
    }

    /** @test */
    public function guest_can_view_related_products(): void
    {
        $response = $this->getJson("/api/v1/products/{$this->product->slug}/related");

        $response->assertOk();
    }

    /** @test */
    public function guest_can_search_products(): void
    {
        $response = $this->getJson('/api/v1/search?q=iPhone');

        $response->assertOk();
    }

    /** @test */
    public function search_requires_minimum_query_length(): void
    {
        $response = $this->getJson('/api/v1/search?q=a');

        $response->assertStatus(422);
    }

    // ─── Brands (via product response) ────────────────────────────

    /** @test */
    public function product_response_includes_brand_data(): void
    {
        $response = $this->getJson("/api/v1/products/{$this->product->slug}");

        $response->assertOk()
            ->assertJsonPath('data.brand.name', 'Apple');
    }

    // ─── Vendor-Product Mapping (SRS Section 4.5) ─────────────────

    /** @test */
    public function product_can_have_vendor_pricing(): void
    {
        $vendorUser = User::factory()->create(['email' => 'vendor-price@example.com', 'status' => 'active']);
        $vendor = Vendor::factory()->create([
            'user_id'   => $vendorUser->id,
            'shop_name' => 'Test Vendor',
            'slug'      => 'test-vendor',
            'status'    => 'active',
        ]);

        $this->product->vendorProductPrices()->create([
            'vendor_id'      => $vendor->id,
            'price'          => 1099.00,
            'sale_price'     => 999.00,
            'stock_quantity' => 25,
            'is_active'      => true,
        ]);

        $this->assertDatabaseHas('vendor_product_prices', [
            'vendor_id'  => $vendor->id,
            'product_id' => $this->product->id,
            'price'      => 1099.00,
        ]);

        $this->assertCount(1, $this->product->fresh()->vendors);
    }

    // ─── Product Approval Workflow (SRS Section 4.5) ──────────────

    /** @test */
    public function pending_product_can_be_approved(): void
    {
        $pendingProduct = Product::factory()->create([
            'status' => 'pending',
            'slug'   => 'pending-product',
        ]);

        $this->assertEquals('pending', $pendingProduct->status);

        $pendingProduct->approve();

        $this->assertEquals('active', $pendingProduct->fresh()->status);
        $this->assertNotNull($pendingProduct->fresh()->published_at);
    }

    /** @test */
    public function pending_product_can_be_rejected(): void
    {
        $pendingProduct = Product::factory()->create([
            'status' => 'pending',
            'slug'   => 'rejected-product',
        ]);

        $pendingProduct->reject();

        $this->assertEquals('inactive', $pendingProduct->fresh()->status);
    }

    // ─── Admin & Vendor Catalog Management ────────────────────────

    /** @test */
    public function admin_can_crud_categories_and_brands_by_uuid(): void
    {
        $this->seed(RBACSeeder::class);

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $catCreate = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/categories/store', [
                'name'            => 'Gaming Accessories',
                'commission_rate' => 12.5,
                'status'          => 'approved',
                'is_active'       => true,
                'meta_keywords'   => 'gaming,accessories',
            ]);

        $catCreate->assertStatus(201)
            ->assertJsonPath('data.name', 'Gaming Accessories')
            ->assertJsonPath('data.meta_keywords', 'gaming,accessories');

        $catUuid = $catCreate->json('data.uuid');

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/admin/categories/{$catUuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $catUuid);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/admin/categories/{$catUuid}/update", [
                'name' => 'PC Gaming Gear',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'PC Gaming Gear');

        $brandCreate = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/brands/store', [
                'name'      => 'HyperGear',
                'status'    => 'approved',
                'is_active' => true,
            ]);

        $brandCreate->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'uuid', 'name']]);

        $brandUuid = $brandCreate->json('data.uuid');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/admin/brands/{$brandUuid}/update", [
                'name' => 'HyperGear Pro',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'HyperGear Pro');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/admin/brands/{$brandUuid}/delete")
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/admin/categories/{$catUuid}/delete")
            ->assertOk();
    }

    /** @test */
    public function active_vendor_can_list_catalog_and_submit_pending_proposals(): void
    {
        $this->seed(RBACSeeder::class);

        $vendorUser = User::factory()->create(['status' => 'active']);
        $vendorUser->assignRole('vendor');
        Vendor::factory()->create([
            'user_id'   => $vendorUser->id,
            'shop_name' => 'Vendor Catalog Shop',
            'slug'      => 'vendor-catalog-shop',
            'status'    => 'active',
        ]);

        $this->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/v1/vendor/categories')
            ->assertOk();

        $this->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/v1/vendor/brands')
            ->assertOk();

        $this->actingAs($vendorUser, 'sanctum')
            ->postJson('/api/v1/vendor/categories/store', [
                'name'   => 'Vendor Proposed Category',
                'status' => 'approved',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $this->actingAs($vendorUser, 'sanctum')
            ->postJson('/api/v1/vendor/brands/store', [
                'name'   => 'Vendor Proposed Brand',
                'status' => 'approved',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');
    }

    /** @test */
    public function vendor_category_proposal_and_admin_approve_reject_send_notifications(): void
    {
        $this->seed(RBACSeeder::class);

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $vendorUser = User::factory()->create(['status' => 'active']);
        $vendorUser->assignRole('vendor');
        Vendor::factory()->create([
            'user_id'   => $vendorUser->id,
            'shop_name' => 'Notified Shop',
            'slug'      => 'notified-shop',
            'status'    => 'active',
        ]);

        $proposal = $this->actingAs($vendorUser, 'sanctum')
            ->postJson('/api/v1/vendor/categories/store', [
                'name' => 'Proposed Audio Gear',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $categoryUuid = $proposal->json('data.uuid');

        $this->assertEquals(1, $admin->notifications()->where('type', CategoryProposedNotification::class)->count());

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/admin/categories/{$categoryUuid}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['created_at', 'updated_at', 'products']]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/categories/{$categoryUuid}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertEquals(1, $vendorUser->notifications()->where('type', CategoryStatusNotification::class)->count());
        $this->assertEquals('approved', $vendorUser->notifications()->latest()->first()->data['status']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/categories/{$categoryUuid}/reject", [
                'reason' => 'Duplicate category',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertEquals(2, $vendorUser->notifications()->where('type', CategoryStatusNotification::class)->count());
        $this->assertEquals('rejected', $vendorUser->notifications()->latest()->first()->data['status']);
    }

    /** @test */
    public function brand_can_be_created_when_cache_store_does_not_support_tagging(): void
    {
        $this->seed(RBACSeeder::class);

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        config(['cache.default' => 'file']);
        Cache::forgetDriver('file');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/brands/store', [
                'name'      => 'NonTaggableBrand',
                'status'    => 'approved',
                'is_active' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'NonTaggableBrand');
    }

    /** @test */
    public function duplicate_brand_or_category_name_returns_validation_error_on_name(): void
    {
        $this->seed(RBACSeeder::class);

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/brands/store', ['name' => 'apple'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/categories/store', ['name' => 'electronics'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function vendor_brand_proposal_and_admin_approve_reject_send_notifications(): void
    {
        $this->seed(RBACSeeder::class);

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $vendorUser = User::factory()->create(['status' => 'active']);
        $vendorUser->assignRole('vendor');
        Vendor::factory()->create([
            'user_id'   => $vendorUser->id,
            'shop_name' => 'Brand Proposal Shop',
            'slug'      => 'brand-proposal-shop',
            'status'    => 'active',
        ]);

        $proposal = $this->actingAs($vendorUser, 'sanctum')
            ->postJson('/api/v1/vendor/brands/store', [
                'name' => 'Proposed Studio Brand',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $brandUuid = $proposal->json('data.uuid');

        $this->assertEquals(1, $admin->notifications()->where('type', BrandProposedNotification::class)->count());

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/admin/brands/{$brandUuid}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['created_at', 'updated_at', 'products']]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/brands/{$brandUuid}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertEquals(1, $vendorUser->notifications()->where('type', BrandStatusNotification::class)->count());
        $this->assertEquals('approved', $vendorUser->notifications()->latest()->first()->data['status']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/brands/{$brandUuid}/reject", [
                'reason' => 'Trademark conflict',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertEquals(2, $vendorUser->notifications()->where('type', BrandStatusNotification::class)->count());
        $this->assertEquals('rejected', $vendorUser->notifications()->latest()->first()->data['status']);
    }

    /** @test */
    public function category_and_brand_return_default_banner_urls_when_banner_is_not_submitted(): void
    {
        $this->seed(RBACSeeder::class);

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');

        $catResponse = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/categories/store', ['name' => 'Default Banner Category'])
            ->assertStatus(201);

        $this->assertStringEndsWith(
            '/images/default-category-banner.jpg',
            $catResponse->json('data.banner_url')
        );

        $brandResponse = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/brands/store', ['name' => 'Default Banner Brand'])
            ->assertStatus(201);

        $this->assertStringEndsWith(
            '/images/default-brand-banner.jpg',
            $brandResponse->json('data.banner_url')
        );
    }
}
