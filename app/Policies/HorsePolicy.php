<?php

namespace App\Policies;

use App\Models\Horse;
use App\Models\User;

class HorsePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Horse $horse): bool
    {
        return $user->is_super_admin || $user->stable_id === $horse->stable_id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Horse $horse): bool
    {
        return false;
    }

    public function delete(User $user, Horse $horse): bool
    {
        return false;
    }
}
