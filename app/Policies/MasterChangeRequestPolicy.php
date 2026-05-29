<?php

namespace App\Policies;

use App\Models\MasterChangeRequest;
use App\Models\User;

/**
 * 공유 마스터 변경요청 권한. (GAP-10 MT-8)
 *  - 제출(create): 제약사 admin(pharma)
 *  - 조회: pharma(자사) / platform(전역)
 *  - 승인·반려(review): platform 전용
 */
class MasterChangeRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPharma() || $user->isPlatform();
    }

    public function create(User $user): bool
    {
        return $user->isPharma();
    }

    public function review(User $user, MasterChangeRequest $request): bool
    {
        return $user->isPlatform();
    }
}
