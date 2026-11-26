<?php

namespace Modules\Support\Policies;

use App\Models\User;
use Modules\Support\Models\Dispute;

class DisputePolicy
{
    public function viewAny(User $user): bool { return $user->isAdmin() || $user->hasPermissionTo('support.disputes.view') || $user->hasPermissionTo('orders.manage'); }
    public function view(User $user, Dispute $dispute): bool { return $user->isAdmin() || $user->hasPermissionTo('orders.manage') || $user->id === $dispute->customer_id; }
    public function create(User $user): bool { return true; }
    public function updateStatus(User $user): bool { return $user->isAdmin() || $user->hasPermissionTo('orders.manage'); }
    public function addMessage(User $user): bool { return $user->isAdmin() || $user->hasPermissionTo('orders.manage'); }
}
