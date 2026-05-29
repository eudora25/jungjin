<?php

namespace App\Policies;

use App\Models\Performance;
use App\Models\SettlementLine;
use App\Models\User;

/**
 * 실적 권한.
 *
 *  - 조회: 로그인한 사용자 누구나.
 *  - 생성: 누구나 (draft 등록).
 *  - 수정/삭제: 본인(created_by) 또는 관리자, 단 `isLocked()` 상태면 불가.
 *  - 상태 전이:
 *      submit  : 본인(draft) 또는 관리자
 *      review  : 관리자
 *      approve : 관리자
 *      reject  : 관리자 (submitted/reviewed 상태에서만)
 *      cancel  : 관리자 (approved 는 정산 연결되어 있으면 불가 — TODO: SettlementLine 체크)
 */
class PerformancePolicy
{
    private function isInSettlement(Performance $performance): bool
    {
        return SettlementLine::query()
            ->where('performance_id', $performance->id)
            ->exists();
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Performance $performance): bool
    {
        if ($user->isPharma()) {
            return true;
        }

        return $performance->created_by === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Performance $performance): bool
    {
        if ($performance->isLocked()) {
            return false;
        }

        if ($this->isInSettlement($performance)) {
            return false;
        }

        if ($user->isPharma()) {
            return in_array($performance->status, [
                Performance::STATUS_DRAFT,
                Performance::STATUS_REJECTED,
            ], true);
        }

        return $performance->created_by === $user->id
            && in_array($performance->status, [
                Performance::STATUS_DRAFT,
                Performance::STATUS_REJECTED,
            ], true);
    }

    public function delete(User $user, Performance $performance): bool
    {
        if ($performance->isLocked()) {
            return false;
        }

        if ($this->isInSettlement($performance)) {
            return false;
        }

        return $user->isPharma()
            || ($performance->created_by === $user->id
                && in_array($performance->status, [
                    Performance::STATUS_DRAFT,
                    Performance::STATUS_REJECTED,
                ], true));
    }

    public function uploadFile(User $user, Performance $performance): bool
    {
        if ($user->isPharma()) {
            return ! in_array($performance->status, [
                Performance::STATUS_CANCELLED,
            ], true);
        }

        return $performance->created_by === $user->id
            && in_array($performance->status, [
                Performance::STATUS_DRAFT,
                Performance::STATUS_REJECTED,
            ], true);
    }

    public function submit(User $user, Performance $performance): bool
    {
        if (! in_array($performance->status, [
            Performance::STATUS_DRAFT,
            Performance::STATUS_REJECTED,
        ], true)) {
            return false;
        }

        return $user->isPharma() || $performance->created_by === $user->id;
    }

    public function review(User $user, Performance $performance): bool
    {
        return $user->isPharma()
            && $performance->status === Performance::STATUS_SUBMITTED;
    }

    public function approve(User $user, Performance $performance): bool
    {
        return $user->isPharma()
            && $performance->status === Performance::STATUS_REVIEWED;
    }

    public function reject(User $user, Performance $performance): bool
    {
        return $user->isPharma() && $performance->isRejectable();
    }

    public function cancel(User $user, Performance $performance): bool
    {
        if (! $user->isPharma()) {
            return false;
        }

        if ($this->isInSettlement($performance)) {
            return false;
        }

        return in_array($performance->status, [
            Performance::STATUS_DRAFT,
            Performance::STATUS_SUBMITTED,
            Performance::STATUS_REVIEWED,
            Performance::STATUS_APPROVED,
        ], true);
    }
}
