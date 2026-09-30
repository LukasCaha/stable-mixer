<?php

namespace App\Policies;

use App\Models\Stable;
use App\Models\User;

class StablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_super_admin;
    }

    public function view(User $user, Stable $stable): bool
    {
        return $user->is_super_admin || $user->stable_id === $stable->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Stable $stable): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        return $user->isOwner() && $user->stable_id === $stable->id;
    }

    public function delete(User $user, Stable $stable): bool
    {
        return false;
    }
}
