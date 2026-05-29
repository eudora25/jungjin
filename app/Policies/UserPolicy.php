<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->isAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->isAdmin();
    }

    public function delete(User $actor, User $user): bool
    {
        // 자기 자신은 삭제 불가
        return $actor->isAdmin() && $actor->id !== $user->id;
    }

    public function toggleActive(User $actor, User $user): bool
    {
        // 자기 자신의 활성 상태 변경 불가 (자가 잠금 방지)
        return $actor->isAdmin() && $actor->id !== $user->id;
    }

    public function resetPassword(User $actor, User $user): bool
    {
        return $actor->isAdmin();
    }
}
