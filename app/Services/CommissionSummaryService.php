<?php

namespace App\Services;

use App\Models\Performance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * 영업사원별 수수료 집계 서비스 (GAP-3).
 *
 * 별도 테이블 없이 `performances` 의 approved 상태 행에서
 * `created_by`(영업사원) 기준으로 `subtotal` / `commission_amount` 를 합산한다.
 */
class CommissionSummaryService
{
    /**
     * 기간 내 영업사원별 수수료 합계.
     *
     * @return Collection<int, array{
     *     user_id: int,
     *     user_name: string,
     *     line_count: int,
     *     total_quantity: int,
     *     total_subtotal: float,
     *     total_commission: float,
     * }>
     */
    public function summaryByUser(string $from, string $to, ?int $userId = null): Collection
    {
        $rows = Performance::query()
            ->where('status', Performance::STATUS_APPROVED)
            ->whereBetween('performance_date', [$from, $to])
            ->whereNotNull('created_by')
            ->when($userId, fn ($q) => $q->where('created_by', $userId))
            ->selectRaw('created_by, COUNT(*) as line_count, COALESCE(SUM(quantity), 0) as total_quantity, COALESCE(SUM(subtotal), 0) as total_subtotal, COALESCE(SUM(commission_amount), 0) as total_commission')
            ->groupBy('created_by')
            ->get();

        $names = User::whereIn('id', $rows->pluck('created_by')->all())
            ->pluck('name', 'id');

        return $rows
            ->map(fn ($row) => [
                'user_id' => (int) $row->created_by,
                'user_name' => $names[$row->created_by] ?? '(unknown)',
                'line_count' => (int) $row->line_count,
                'total_quantity' => (int) $row->total_quantity,
                'total_subtotal' => (float) $row->total_subtotal,
                'total_commission' => (float) $row->total_commission,
            ])
            ->sortByDesc('total_commission')
            ->values();
    }

    /**
     * 단일 영업사원의 기간 내 실적 라인 + 합계 (개인 수수료 명세).
     *
     * @return array{
     *     lines: \Illuminate\Database\Eloquent\Collection<int, Performance>,
     *     totals: array{
     *         line_count: int,
     *         total_quantity: int,
     *         total_subtotal: float,
     *         total_commission: float,
     *     },
     * }
     */
    public function statementFor(int $userId, string $from, string $to): array
    {
        $lines = Performance::query()
            ->with([
                'company:id,company_name',
                'product:id,product_name,insurance_code',
            ])
            ->where('status', Performance::STATUS_APPROVED)
            ->where('created_by', $userId)
            ->whereBetween('performance_date', [$from, $to])
            ->orderBy('performance_date')
            ->orderBy('id')
            ->get();

        $totalCommission = $lines->reduce(
            fn (float $carry, Performance $p) => $carry + (float) ($p->commission_amount ?? 0),
            0.0,
        );

        return [
            'lines' => $lines,
            'totals' => [
                'line_count' => $lines->count(),
                'total_quantity' => (int) $lines->sum('quantity'),
                'total_subtotal' => (float) $lines->sum(fn (Performance $p) => (float) $p->subtotal),
                'total_commission' => $totalCommission,
            ],
        ];
    }

    /**
     * 본인의 특정 월 수수료 합계 (Sales 대시보드 카드용).
     */
    public function monthlyCommissionFor(int $userId, string $month): float
    {
        [$from, $to] = $this->monthRange($month);

        return (float) Performance::query()
            ->where('status', Performance::STATUS_APPROVED)
            ->where('created_by', $userId)
            ->whereBetween('performance_date', [$from, $to])
            ->sum('commission_amount');
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function monthRange(string $month): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new InvalidArgumentException("Invalid month format: {$month} (expected YYYY-MM)");
        }

        $start = Carbon::parse($month.'-01')->startOfMonth()->toDateString();
        $end = Carbon::parse($month.'-01')->endOfMonth()->toDateString();

        return [$start, $end];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function resolveRange(?string $from, ?string $to, ?string $month): array
    {
        if ($month) {
            return $this->monthRange($month);
        }

        if ($from && $to) {
            return [$from, $to];
        }

        return $this->monthRange(now()->format('Y-m'));
    }
}
