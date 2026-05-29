<?php

namespace App\Policies;

use App\Models\Notice;
use App\Models\User;

class NoticePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Notice $notice): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Notice $notice): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Notice $notice): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Notice $notice): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Notice $notice): bool
    {
        return $user->isAdmin();
    }
}
