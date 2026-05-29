<?php

namespace App\Policies;

use App\Models\CompanySalesAssignment;
use App\Models\User;

/**
 * 영업사원-거래처 담당 배정 권한 (GAP-4).
 *
 * - assign/revoke: admin 전용
 * - viewAny: 인증 사용자 누구나(거래처 상세에서 배정 현황 확인)
 */
class CompanySalesAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isPharma();
    }

    public function delete(User $user, CompanySalesAssignment $assignment): bool
    {
        return $user->isPharma();
    }
}
