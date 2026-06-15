<?php

namespace App\Policies;

use App\Models\User;

/**
 * 사용자 관리 권한 — pharma 는 **자사 테넌트의 cso** 만 관리한다. (GAP-10 후속-B §6.9)
 * pharma 계정·platform 계정·타 테넌트 사용자는 이 경로(`/users`)로 관리하지 않는다(platform `/platform/users`).
 */
class UserPolicy
{
    /**
     * 관리 대상 자격: actor 는 pharma(테넌트 보유)이고, 대상은 **같은 테넌트의 cso** 여야 한다.
     */
    private function manages(User $actor, User $user): bool
    {
        return $actor->isPharma()
            && $actor->tenant_id !== null
            && $actor->tenant_id === $user->tenant_id
            && $user->role === User::ROLE_CSO;
    }

    public function viewAny(User $actor): bool
    {
        return $actor->isPharma();
    }

    public function view(User $actor, User $user): bool
    {
        return $this->manages($actor, $user);
    }

    public function create(User $actor): bool
    {
        return $actor->isPharma();
    }

    public function update(User $actor, User $user): bool
    {
        return $this->manages($actor, $user);
    }

    public function delete(User $actor, User $user): bool
    {
        // 자기 자신 삭제 불가 (manages 가 cso 만 허용하므로 pharma 자신은 이미 제외되지만 명시 유지)
        return $this->manages($actor, $user) && $actor->id !== $user->id;
    }

    public function toggleActive(User $actor, User $user): bool
    {
        return $this->manages($actor, $user) && $actor->id !== $user->id;
    }

    public function resetPassword(User $actor, User $user): bool
    {
        return $this->manages($actor, $user);
    }
}
