<?php

namespace Modules\Customer\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Orders\Models\Order;
use Modules\RBAC\Database\Seeders\RBACSeeder;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RBACSeeder::class);

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('super-admin');
    }

    public function test_admin_can_filter_verified_customers_and_free_users(): void
    {
        $verifiedCustomer = User::factory()->create(['name' => 'Buyer One']);
        Order::factory()->create([
            'user_id'      => $verifiedCustomer->id,
            'status'       => 'delivered',
            'total_amount' => 1500,
        ]);

        $freeUser = User::factory()->create(['name' => 'Free Browser']);
        $cancelledOnlyUser = User::factory()->create(['name' => 'Cancelled Buyer']);
        Order::factory()->create([
            'user_id'      => $cancelledOnlyUser->id,
            'status'       => 'cancelled',
            'total_amount' => 900,
        ]);

        $verifiedResp = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/customers?customer_type=verified');

        $verifiedResp->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Buyer One')
            ->assertJsonPath('data.0.is_verified_customer', true)
            ->assertJsonPath('data.0.customer_type', 'verified_customer');

        $freeResp = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/customers?customer_type=unverified');

        $freeResp->assertOk()
            ->assertJsonCount(2, 'data');

        $statsResp = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/customers/stats');

        $statsResp->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.verified_customers', 1)
            ->assertJsonPath('data.free_users', 2);
    }

    public function test_admin_can_create_update_ban_and_delete_customer(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/customers', [
                'name'  => 'New Shopper',
                'email' => 'shopper@example.com',
                'phone' => '+8801700000123',
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.customer_type', 'free_user');

        $uuid = $create->json('data.uuid');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/customers/{$uuid}", ['name' => 'Updated Shopper'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Shopper');

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/admin/customers/{$uuid}/status", ['status' => 'banned'])
            ->assertOk()
            ->assertJsonPath('data.status', 'banned');

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/admin/customers/{$uuid}")
            ->assertOk();
    }

    public function test_non_admin_cannot_access_customer_endpoints(): void
    {
        $regularUser = User::factory()->create();

        $this->actingAs($regularUser, 'sanctum')
            ->getJson('/api/v1/admin/customers')
            ->assertForbidden();
    }
}
