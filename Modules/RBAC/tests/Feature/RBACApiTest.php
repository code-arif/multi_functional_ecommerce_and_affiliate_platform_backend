<?php

namespace Modules\RBAC\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\RBAC\Database\Seeders\RBACSeeder;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RBACApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RBACSeeder::class);

        $this->superAdmin = User::factory()->create(['status' => 'active']);
        $this->superAdmin->assignRole('super-admin');

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('admin');
    }

    public function test_super_admin_can_crud_admin_staff_and_assign_roles(): void
    {
        $create = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/admin/rbac/admins', [
                'name'                  => 'Ops Moderator',
                'email'                 => 'ops-mod@example.com',
                'role'                  => 'moderator',
                'password'              => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.role', 'moderator');

        $uuid = $create->json('data.uuid');

        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/v1/admin/rbac/admins/{$uuid}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Ops Moderator');

        $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/v1/admin/rbac/admins/{$uuid}", [
                'role' => 'support',
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'support');

        $this->actingAs($this->superAdmin, 'sanctum')
            ->patchJson("/api/v1/admin/rbac/admins/{$uuid}/status", ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->actingAs($this->superAdmin, 'sanctum')
            ->deleteJson("/api/v1/admin/rbac/admins/{$uuid}")
            ->assertOk();
    }

    public function test_non_super_admin_cannot_escalate_to_super_admin(): void
    {
        $this->admin->givePermissionTo('roles.manage');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/admin/rbac/users/assign', [
                'user_uuid' => $this->admin->uuid,
                'roles'     => ['super-admin'],
            ])
            ->assertForbidden();
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        $moderatorRole = Role::where('name', 'moderator')->firstOrFail();

        $this->actingAs($this->superAdmin, 'sanctum')
            ->deleteJson("/api/v1/admin/rbac/roles/{$moderatorRole->id}/destroy")
            ->assertStatus(422);
    }

    public function test_authenticated_user_can_fetch_my_permissions_outside_admin_prefix(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/auth/my-permissions')
            ->assertOk()
            ->assertJsonStructure(['data' => ['roles', 'permissions']]);
    }
}
