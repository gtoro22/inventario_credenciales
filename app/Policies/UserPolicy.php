<?php

namespace App\Policies;

use App\Models\User;

/** Solo un superadministrador puede gestionar a otro superadministrador. */
class UserPolicy
{
    public function manage(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.manage') && (! $target->isSuperAdmin() || $actor->isSuperAdmin());
    }
}
