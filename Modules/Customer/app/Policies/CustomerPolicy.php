<?php

namespace Modules\Customer\Policies;

use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermissionTo('users.view');
    }

    public function view(User $user, User $targetUser): bool
    {
        return $user->isAdmin() || $user->hasPermissionTo('users.view');
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermissionTo('users.create');
    }

    public function update(User $user, User $targetUser): bool
    {
        return $user->isAdmin() || $user->hasPermissionTo('users.edit');
    }

    public function updateStatus(User $user, User $targetUser): bool
    {
        return $user->isAdmin() || $user->hasPermissionTo('users.ban');
    }

    public function delete(User $user, User $targetUser): bool
    {
        return $user->isAdmin() || $user->hasPermissionTo('users.delete');
    }
}
