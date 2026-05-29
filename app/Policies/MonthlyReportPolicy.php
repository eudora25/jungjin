<?php

namespace App\Policies;

use App\Models\User;

/**
 * 월간 보고서 권한 (GAP-6).
 *
 * - viewAny / export : admin 만 (전사 집계 리포트·Excel)
 */
class MonthlyReportPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isPharma();
    }

    public function export(User $actor): bool
    {
        return $actor->isPharma();
    }
}
