<?php

namespace App\Services\Settlement;

use App\Models\Company;
use App\Models\Performance;
use App\Models\Settlement;
use App\Models\SettlementLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 월·거래처 단위로 승인된 실적을 모아 정산 헤더 + 라인을 구성한다.
 * 동일 (company_id, period_month) 가 있으면 라인을 전부 갈아 끼우고 합계를 다시 쓴다.
 */
class SettlementBuilder
{
    public function createOrRebuild(Company $company, string $periodMonth, User $user): Settlement
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $periodMonth)) {
            throw new RuntimeException('period_month 은 YYYY-MM 형식이어야 합니다.');
        }

        return DB::transaction(function () use ($company, $periodMonth, $user) {
            $settlement = Settlement::query()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'period_month' => $periodMonth,
                ],
                [
                    'settlement_no' => Settlement::settlementNoFor($periodMonth, $company->id),
                    'status' => Settlement::STATUS_DRAFT,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ],
            );

            if ($settlement->status !== Settlement::STATUS_DRAFT) {
                throw new RuntimeException('draft 상태의 정산만 재계산할 수 있습니다.');
            }

            $this->replaceLines($settlement, $user);

            return $settlement->fresh(['lines']);
        });
    }

    public function replaceLines(Settlement $settlement, User $user): void
    {
        $performances = Performance::query()
            ->forCompany($settlement->company_id)
            ->forMonth($settlement->period_month)
            ->approved()
            ->orderBy('id')
            ->get();

        SettlementLine::query()->where('settlement_id', $settlement->id)->delete();

        $totalQty = 0;
        $totalSub = 0.0;
        $totalComm = 0.0;

        foreach ($performances as $p) {
            SettlementLine::query()->create([
                'settlement_id' => $settlement->id,
                'performance_id' => $p->id,
                'snapshot_unit_price' => $p->unit_price,
                'snapshot_commission_rate' => $p->commission_rate,
                'quantity' => $p->quantity,
                'subtotal' => $p->subtotal,
                'commission_amount' => $p->commission_amount,
            ]);
            $totalQty += (int) $p->quantity;
            $totalSub += (float) $p->subtotal;
            $totalComm += (float) ($p->commission_amount ?? 0);
        }

        $settlement->update([
            'line_count' => $performances->count(),
            'total_quantity' => $totalQty,
            'total_subtotal' => round($totalSub, 2),
            'total_commission' => round($totalComm, 2),
            'calculated_at' => now(),
            'updated_by' => $user->id,
        ]);
    }
}
