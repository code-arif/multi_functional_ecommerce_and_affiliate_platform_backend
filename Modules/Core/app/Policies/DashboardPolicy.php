<?php

namespace Modules\Core\Policies;

use App\Models\User;

class DashboardPolicy
{
    public function view(User $user): bool
    {
        return $user->isAdmin() || $user->isModerator();
    }
}
