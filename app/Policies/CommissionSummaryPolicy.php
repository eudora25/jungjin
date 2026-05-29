<?php

namespace App\Policies;

use App\Models\User;

/**
 * 영업사원별 수수료 명세 권한 (GAP-3).
 *
 * - viewAny / export : admin 만 (전사 집계 페이지·Excel)
 * - viewStatement / exportStatement : admin OR 본인 (개인 수수료 명세서)
 */
class CommissionSummaryPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isPharma();
    }

    public function export(User $actor): bool
    {
        return $actor->isPharma();
    }

    public function viewStatement(User $actor, User $target): bool
    {
        return $actor->isPharma() || $actor->id === $target->id;
    }

    public function exportStatement(User $actor, User $target): bool
    {
        return $this->viewStatement($actor, $target);
    }
}
