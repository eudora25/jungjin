<?php

namespace App\Policies;

use App\Models\Settlement;
use App\Models\SettlementLine;
use App\Models\User;

class SettlementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Settlement $settlement): bool
    {
        if ($user->isPharma()) {
            return true;
        }

        // sales: 본인 실적이 한 건 이상 포함된 정산만 조회 가능
        return SettlementLine::query()
            ->where('settlement_id', $settlement->id)
            ->whereHas('performance', fn ($q) => $q->where('created_by', $user->id))
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->isPharma();
    }

    public function export(User $user, Settlement $settlement): bool
    {
        if ($user->isPharma()) {
            return true;
        }

        // sales: 본인 실적이 포함된 정산만 내보내기 가능
        return SettlementLine::query()
            ->where('settlement_id', $settlement->id)
            ->whereHas('performance', fn ($q) => $q->where('created_by', $user->id))
            ->exists();
    }

    public function recalculate(User $user, Settlement $settlement): bool
    {
        return $user->isPharma() && $settlement->status === Settlement::STATUS_DRAFT;
    }

    public function confirm(User $user, Settlement $settlement): bool
    {
        return $user->isPharma() && $settlement->status === Settlement::STATUS_DRAFT;
    }

    public function pay(User $user, Settlement $settlement): bool
    {
        return $user->isPharma() && $settlement->status === Settlement::STATUS_CONFIRMED;
    }

    public function cancel(User $user, Settlement $settlement): bool
    {
        return $user->isPharma() && in_array($settlement->status, [
            Settlement::STATUS_DRAFT,
            Settlement::STATUS_CONFIRMED,
        ], true);
    }

    /**
     * GAP-5-4: 지급 증빙 파일 업로드/삭제.
     * confirmed 또는 paid 상태에서만 가능 (지급 처리 전후 모두 첨부 허용).
     */
    public function uploadPaymentFile(User $user, Settlement $settlement): bool
    {
        return $user->isPharma() && in_array($settlement->status, [
            Settlement::STATUS_CONFIRMED,
            Settlement::STATUS_PAID,
        ], true);
    }
}
