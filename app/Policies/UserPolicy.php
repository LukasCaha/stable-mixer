<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, User $mate): bool
    {
        return $user->stable_id === $mate->stable_id;
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, User $mate): bool
    {
        if ($user->stable_id !== $mate->stable_id) {
            return false;
        }

        return $user->isOwner() || $user->is($mate);
    }

    public function delete(User $user, User $mate): bool
    {
        if (! $user->isOwner() || $user->stable_id !== $mate->stable_id || $user->is($mate)) {
            return false;
        }

        if ($mate->role !== UserRole::Owner) {
            return true;
        }

        return User::query()
            ->where('stable_id', $mate->stable_id)
            ->where('role', UserRole::Owner)
            ->count() > 1;
    }
}
