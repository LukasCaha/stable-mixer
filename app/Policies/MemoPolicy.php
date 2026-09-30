<?php

namespace App\Policies;

use App\Models\Memo;
use App\Models\User;

class MemoPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Memo $memo): bool
    {
        return $user->is_super_admin || $user->stable_id === $memo->stable_id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Memo $memo): bool
    {
        return false;
    }

    public function delete(User $user, Memo $memo): bool
    {
        return false;
    }
}
