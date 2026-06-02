<?php

namespace App\Policies;

use App\Models\CodeGroup;
use App\Models\User;

/**
 * 코드 그룹/코드 정의 관리 — 플랫폼 운영자(platform) 전용. (GAP-10)
 */
class CodeGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatform();
    }

    public function view(User $user, CodeGroup $codeGroup): bool
    {
        return $user->isPlatform();
    }

    public function create(User $user): bool
    {
        return $user->isPlatform();
    }

    public function update(User $user, CodeGroup $codeGroup): bool
    {
        return $user->isPlatform();
    }

    public function delete(User $user, CodeGroup $codeGroup): bool
    {
        return $user->isPlatform();
    }

    /** 코드 정의(하위 코드) 추가·수정·삭제 */
    public function manageDefinitions(User $user, CodeGroup $codeGroup): bool
    {
        return $user->isPlatform();
    }
}
