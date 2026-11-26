<?php

namespace Modules\RBAC\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        // Prevent non-super-admins from modifying super-admin role
        if ($role->name === 'super-admin' && !$user->hasRole('super-admin')) {
            return false;
        }

        return $user->hasRole('super-admin') || $user->hasPermissionTo('roles.manage');
    }

    public function delete(User $user, Role $role): bool
    {
        // Prevent deleting system roles
        if (in_array($role->name, ['super-admin', 'admin', 'moderator', 'finance', 'support', 'vendor', 'vendor-staff', 'customer'], true)) {
            return false;
        }

        return $user->hasRole('super-admin') || $user->hasPermissionTo('roles.manage');
    }

    public function syncPermissions(User $user, Role $role): bool
    {
        if ($role->name === 'super-admin' && !$user->hasRole('super-admin')) {
            return false;
        }

        return $user->hasRole('super-admin') || $user->hasPermissionTo('roles.manage');
    }
}
