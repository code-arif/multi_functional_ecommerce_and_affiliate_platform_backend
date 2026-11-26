<?php

namespace Modules\RBAC\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\ActivityLog;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RBACService
{
    /**
     * System roles seeded by RBACSeeder that must never be deleted.
     *
     * @var string[]
     */
    private const PROTECTED_ROLES = [
        'super-admin',
        'admin',
        'moderator',
        'finance',
        'support',
        'vendor',
        'vendor-staff',
        'customer',
    ];

    /**
     * Roles considered internal Admin/Staff accounts.
     *
     * @var string[]
     */
    private const STAFF_ROLES = [
        'super-admin',
        'admin',
        'moderator',
        'finance',
        'support',
    ];

    private function canCountRoleUsers(): bool
    {
        return (bool) getModelForGuard(Guard::getDefaultName(Role::class));
    }

    // ─── Roles ────────────────────────────────────────────────────

    public function listRoles(array $filters = []): LengthAwarePaginator
    {
        $query = Role::query()->withCount('permissions');

        if ($this->canCountRoleUsers()) {
            $query->withCount('users');
        }

        return $query
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where(function (Builder $sq) use ($s) {
                $sq->where('name', 'like', "%{$s}%")
                    ->orWhere('display_name', 'like', "%{$s}%");
            }))
            ->when($filters['guard'] ?? null, fn (Builder $q, string $g) => $q->where('guard_name', $g))
            ->orderBy($filters['sort'] ?? 'name', $filters['order'] ?? 'asc')
            ->paginate($filters['per_page'] ?? 50);
    }

    public function getRole(int $id): Role
    {
        $query = Role::with('permissions');

        if ($this->canCountRoleUsers()) {
            $query->withCount('users');
        }

        $role = $query->find($id);

        if (!$role) {
            throw new ModelNotFoundException("Role with ID {$id} not found.");
        }

        return $role;
    }

    public function createRole(array $data): Role
    {
        $roleData = [
            'name'       => $data['name'],
            'guard_name' => $data['guard_name'] ?? config('auth.defaults.guard', 'web'),
        ];

        if (isset($data['display_name'])) {
            $roleData['display_name'] = $data['display_name'];
        }
        if (isset($data['description'])) {
            $roleData['description'] = $data['description'];
        }

        $role = Role::create($roleData);

        if (!empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role->fresh()->load('permissions');
    }

    public function updateRole(int $id, array $data, ?User $actor = null): Role
    {
        $role = Role::findOrFail($id);

        if ($role->name === 'super-admin' && $actor && !$actor->hasRole('super-admin')) {
            abort(403, 'Only a super-admin can modify the super-admin role.');
        }

        $updateData = [];
        if (isset($data['name'])) {
            if (in_array($role->name, self::PROTECTED_ROLES, true) && $data['name'] !== $role->name) {
                abort(422, "System role '{$role->name}' slug cannot be renamed.");
            }
            $updateData['name'] = $data['name'];
        }
        if (array_key_exists('display_name', $data)) {
            $updateData['display_name'] = $data['display_name'];
        }
        if (array_key_exists('description', $data)) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['guard_name'])) {
            $updateData['guard_name'] = $data['guard_name'];
        }

        if (!empty($updateData)) {
            $role->update($updateData);
        }

        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role->fresh()->load('permissions');
    }

    public function deleteRole(int $id, ?User $actor = null): bool
    {
        $role = Role::find($id);

        if (!$role) {
            throw new ModelNotFoundException("Role with ID {$id} not found.");
        }

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            abort(422, "The '{$role->name}' role is a system role and cannot be deleted.");
        }

        $deleted = (bool) $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $deleted;
    }

    public function syncRolePermissions(int $roleId, array $permissions, ?User $actor = null): Role
    {
        $role = Role::findOrFail($roleId);

        if ($role->name === 'super-admin' && $actor && !$actor->hasRole('super-admin')) {
            abort(403, 'Only a super-admin can modify permissions of the super-admin role.');
        }

        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role->fresh()->load('permissions');
    }

    // ─── Permissions ──────────────────────────────────────────────

    public function listPermissions(array $filters = []): LengthAwarePaginator
    {
        return Permission::query()
            ->withCount('roles')
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where(function (Builder $sq) use ($s) {
                $sq->where('name', 'like', "%{$s}%")
                    ->orWhere('display_name', 'like', "%{$s}%");
            }))
            ->when($filters['group'] ?? null, fn (Builder $q, string $g) => $q->where('group', $g))
            ->when($filters['guard'] ?? null, fn (Builder $q, string $g) => $q->where('guard_name', $g))
            ->orderBy($filters['sort'] ?? 'group', $filters['order'] ?? 'asc')
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 50);
    }

    public function getPermission(int $id): Permission
    {
        return Permission::with('roles')->findOrFail($id);
    }

    public function createPermission(array $data): Permission
    {
        $permData = [
            'name'       => $data['name'],
            'guard_name' => $data['guard_name'] ?? config('auth.defaults.guard', 'web'),
        ];

        if (isset($data['display_name'])) {
            $permData['display_name'] = $data['display_name'];
        }
        if (isset($data['group'])) {
            $permData['group'] = $data['group'];
        }

        $permission = Permission::create($permData);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permission;
    }

    public function updatePermission(int $id, array $data): Permission
    {
        $permission = Permission::findOrFail($id);

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (array_key_exists('display_name', $data)) {
            $updateData['display_name'] = $data['display_name'];
        }
        if (array_key_exists('group', $data)) {
            $updateData['group'] = $data['group'];
        }
        if (isset($data['guard_name'])) {
            $updateData['guard_name'] = $data['guard_name'];
        }

        if (!empty($updateData)) {
            $permission->update($updateData);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permission->fresh();
    }

    public function deletePermission(int $id): bool
    {
        $permission = Permission::findOrFail($id);

        if ($permission->roles()->count() > 0) {
            abort(422, 'This permission is assigned to one or more roles and cannot be deleted. Remove it from all roles first.');
        }

        $deleted = (bool) $permission->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $deleted;
    }

    public function getAllPermissionGroups(): Collection
    {
        return Permission::query()
            ->select('group')
            ->whereNotNull('group')
            ->where('group', '!=', '')
            ->distinct()
            ->pluck('group')
            ->values();
    }

    public function getPermissionsByGroup(): Collection
    {
        $permissions = Permission::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get();

        return $permissions->groupBy(fn ($p) => $p->group ?: 'Uncategorized');
    }

    // ─── User-Role & Admin Staff Management ───────────────────────

    public function assignRolesToUser(int $userId, array $roleNames, ?User $actor = null): User
    {
        $user = User::findOrFail($userId);

        $this->guardSuperAdminTransition($user, $roleNames, $actor);

        $user->syncRoles($roleNames);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh()->load('roles');
    }

    public function getUserPermissions(int $userId): Collection
    {
        $user = User::findOrFail($userId);
        return $user->getAllPermissions();
    }

    public function listUsersWithRoles(array $filters = []): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where(function (Builder $sq) use ($s) {
                $sq->where('name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%");
            }))
            ->when($filters['role'] ?? null, fn (Builder $q, string $r) =>
                $q->whereHas('roles', fn (Builder $qr) => $qr->where('name', $r))
            )
            ->orderBy($filters['sort'] ?? 'created_at', $filters['order'] ?? 'desc')
            ->paginate($filters['per_page'] ?? 20);
    }

    public function listAdminStaff(array $filters = []): LengthAwarePaginator
    {
        $staffRoles = $this->getAssignableStaffRoleNames();

        return User::query()
            ->with('roles')
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', $staffRoles))
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where(function (Builder $sq) use ($s) {
                $sq->where('name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%");
            }))
            ->when($filters['role'] ?? null, fn (Builder $q, string $r) =>
                $q->whereHas('roles', fn (Builder $qr) => $qr->where('name', $r))
            )
            ->when($filters['status'] ?? null, fn (Builder $q, string $st) => $q->where('status', $st))
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function adminStaffStats(): array
    {
        $staffRoles = $this->getAssignableStaffRoleNames();
        $base = User::query()->whereHas('roles', fn (Builder $q) => $q->whereIn('name', $staffRoles));

        return [
            'total'       => (clone $base)->count(),
            'super_admin' => (clone $base)->whereHas('roles', fn (Builder $q) => $q->where('name', 'super-admin'))->count(),
            'admin'       => (clone $base)->whereHas('roles', fn (Builder $q) => $q->where('name', 'admin'))->count(),
            'moderator'   => (clone $base)->whereHas('roles', fn (Builder $q) => $q->where('name', 'moderator'))->count(),
            'finance'     => (clone $base)->whereHas('roles', fn (Builder $q) => $q->where('name', 'finance'))->count(),
            'support'     => (clone $base)->whereHas('roles', fn (Builder $q) => $q->where('name', 'support'))->count(),
        ];
    }

    public function findAdminStaff(string|int $identifier): User
    {
        $user = User::query()
            ->with(['roles.permissions'])
            ->when(
                is_numeric($identifier),
                fn (Builder $q) => $q->where('id', (int) $identifier),
                fn (Builder $q) => $q->where('uuid', (string) $identifier)
            )
            ->first();

        if (!$user) {
            throw new ModelNotFoundException('Admin user not found.');
        }

        return $user;
    }

    public function getAdminActivityLogs(User $admin, int $limit = 15): Collection
    {
        return ActivityLog::query()
            ->where('user_id', $admin->id)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function createAdminStaff(array $data, ?User $actor = null): User
    {
        $roles = isset($data['roles']) ? $data['roles'] : [$data['role']];

        if (in_array('super-admin', $roles, true) && $actor && !$actor->hasRole('super-admin')) {
            abort(403, 'Only a super-admin can create another super-admin.');
        }

        $user = User::create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'phone'             => $data['phone'] ?? null,
            'password'          => Hash::make($data['password']),
            'status'            => $data['status'] ?? 'active',
            'email_verified_at' => now(),
        ]);

        $user->syncRoles($roles);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->findAdminStaff($user->id);
    }

    public function updateAdminStaff(User $target, array $data, ?User $actor = null): User
    {
        if (isset($data['roles']) || isset($data['role'])) {
            $roles = isset($data['roles']) ? $data['roles'] : [$data['role']];
            $this->guardSuperAdminTransition($target, $roles, $actor);
            $target->syncRoles($roles);
        }

        if (isset($data['status'])) {
            $this->guardAdminSelfOrLastSuperAdmin($target, $data['status'], $actor, 'modify status');
        }

        $updates = array_filter([
            'name'   => $data['name'] ?? null,
            'email'  => $data['email'] ?? null,
            'phone'  => array_key_exists('phone', $data) ? $data['phone'] : null,
            'status' => $data['status'] ?? null,
        ], fn ($v) => $v !== null);

        if (array_key_exists('phone', $data) && $data['phone'] === null) {
            $updates['phone'] = null;
        }

        if (!empty($data['password'])) {
            $updates['password'] = Hash::make($data['password']);
        }

        if (!empty($updates)) {
            $target->update($updates);
        }

        if (($updates['status'] ?? null) === 'banned') {
            $target->tokens()->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->findAdminStaff($target->id);
    }

    public function updateAdminStatus(User $target, string $status, ?User $actor = null): User
    {
        $this->guardAdminSelfOrLastSuperAdmin($target, $status, $actor, 'change status');

        $target->update(['status' => $status]);
        if ($status === 'banned') {
            $target->tokens()->delete();
        }

        return $this->findAdminStaff($target->id);
    }

    public function deleteAdminStaff(User $target, ?User $actor = null): void
    {
        if ($actor && $actor->id === $target->id) {
            abort(422, 'You cannot delete your own admin account.');
        }

        if ($target->hasRole('super-admin')) {
            if ($actor && !$actor->hasRole('super-admin')) {
                abort(403, 'Only a super-admin can delete a super-admin account.');
            }
            if (User::role('super-admin')->count() <= 1) {
                abort(422, 'Cannot delete the last super-admin account.');
            }
        }

        $target->tokens()->delete();
        $target->delete();
    }

    private function guardSuperAdminTransition(User $target, array $newRoles, ?User $actor): void
    {
        $assigningSuperAdmin = in_array('super-admin', $newRoles, true);
        $wasSuperAdmin = $target->hasRole('super-admin');

        if (($assigningSuperAdmin || $wasSuperAdmin) && $actor && !$actor->hasRole('super-admin')) {
            abort(403, 'Only a super-admin can assign or modify the super-admin role.');
        }

        if ($wasSuperAdmin && !$assigningSuperAdmin && User::role('super-admin')->count() <= 1) {
            abort(422, 'Cannot remove super-admin role from the last super-admin.');
        }
    }

    private function guardAdminSelfOrLastSuperAdmin(User $target, string $status, ?User $actor, string $action): void
    {
        if ($actor && $actor->id === $target->id && $status !== 'active') {
            abort(422, "You cannot {$action} of your own account to {$status}.");
        }

        if ($target->hasRole('super-admin') && $status !== 'active') {
            if ($actor && !$actor->hasRole('super-admin')) {
                abort(403, 'Only a super-admin can change the status of a super-admin account.');
            }
            if (User::role('super-admin')->where('status', 'active')->count() <= 1) {
                abort(422, 'Cannot deactivate or ban the last active super-admin.');
            }
        }
    }

    /**
     * All non-customer/non-vendor roles so custom admin-panel roles appear in Admins list too.
     *
     * @return string[]
     */
    private function getAssignableStaffRoleNames(): array
    {
        $customRoles = Role::query()
            ->whereNotIn('name', ['customer', 'vendor', 'vendor-staff'])
            ->pluck('name')
            ->all();

        return array_values(array_unique(array_merge(self::STAFF_ROLES, $customRoles)));
    }
}
