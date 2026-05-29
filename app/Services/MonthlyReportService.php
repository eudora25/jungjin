<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Performance;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * 월간 보고서 집계 서비스 (GAP-6).
 *
 * 별도 테이블 없이 `performances` 의 approved 상태 행에서
 * 거래처 / 영업사원 / 제품 기준으로 매출(`subtotal`)·수수료(`commission_amount`)를 합산한다.
 *
 * 설계 결정(MONTHLY_REPORT.md §1):
 *  - 기준 상태: `status = approved` 만 (정산과 동일한 확정 수치)
 *  - 기간: `performance_date` 기준 [from, to]
 *  - 평균 수수료율은 합계 매출 기준으로 재계산 (단순 평균 아님)
 */
class MonthlyReportService
{
    /**
     * 거래처별 요약. 정렬: 매출액 desc.
     *
     * @return Collection<int, array{
     *     company_id: int, company_name: string, partner_type: ?string,
     *     line_count: int, total_quantity: int,
     *     total_subtotal: float, total_commission: float, avg_commission_rate: float,
     * }>
     */
    public function byCompany(string $from, string $to): Collection
    {
        $rows = $this->aggregate($from, $to, 'company_id');

        $companies = Company::whereIn('id', $rows->pluck('company_id')->filter()->all())
            ->get(['id', 'company_name', 'partner_type'])
            ->keyBy('id');

        return $rows
            ->map(function ($r) use ($companies) {
                $company = $companies[$r->company_id] ?? null;

                return [
                    'company_id' => (int) $r->company_id,
                    'company_name' => $company?->company_name ?? '(미지정)',
                    'partner_type' => $company?->partner_type,
                    'line_count' => (int) $r->line_count,
                    'total_quantity' => (int) $r->total_quantity,
                    'total_subtotal' => (float) $r->total_subtotal,
                    'total_commission' => (float) $r->total_commission,
                    'avg_commission_rate' => $this->rate((float) $r->total_subtotal, (float) $r->total_commission),
                ];
            })
            ->sortByDesc('total_subtotal')
            ->values();
    }

    /**
     * 영업사원별 요약. 정렬: 수수료액 desc.
     *
     * @return Collection<int, array{
     *     user_id: ?int, user_name: string,
     *     line_count: int, total_quantity: int,
     *     total_subtotal: float, total_commission: float, avg_commission_rate: float,
     * }>
     */
    public function bySalesUser(string $from, string $to): Collection
    {
        $rows = $this->aggregate($from, $to, 'created_by');

        $names = User::whereIn('id', $rows->pluck('created_by')->filter()->all())
            ->pluck('name', 'id');

        return $rows
            ->map(fn ($r) => [
                'user_id' => $r->created_by !== null ? (int) $r->created_by : null,
                'user_name' => $r->created_by !== null ? ($names[$r->created_by] ?? '(unknown)') : '(미지정)',
                'line_count' => (int) $r->line_count,
                'total_quantity' => (int) $r->total_quantity,
                'total_subtotal' => (float) $r->total_subtotal,
                'total_commission' => (float) $r->total_commission,
                'avg_commission_rate' => $this->rate((float) $r->total_subtotal, (float) $r->total_commission),
            ])
            ->sortByDesc('total_commission')
            ->values();
    }

    /**
     * 제품별 요약. 정렬: 매출액 desc. (전월 대비 MoM 미포함)
     *
     * @return Collection<int, array{
     *     product_id: int, product_name: string, insurance_code: ?string, manufacturer: ?string,
     *     line_count: int, total_quantity: int,
     *     total_subtotal: float, total_commission: float, avg_commission_rate: float,
     * }>
     */
    public function byProduct(string $from, string $to): Collection
    {
        $rows = $this->aggregate($from, $to, 'product_id');

        $products = Product::whereIn('id', $rows->pluck('product_id')->filter()->all())
            ->get(['id', 'product_name', 'insurance_code', 'manufacturer'])
            ->keyBy('id');

        return $rows
            ->map(function ($r) use ($products) {
                $product = $products[$r->product_id] ?? null;

                return [
                    'product_id' => (int) $r->product_id,
                    'product_name' => $product?->product_name ?? '(미지정)',
                    'insurance_code' => $product?->insurance_code,
                    'manufacturer' => $product?->manufacturer,
                    'line_count' => (int) $r->line_count,
                    'total_quantity' => (int) $r->total_quantity,
                    'total_subtotal' => (float) $r->total_subtotal,
                    'total_commission' => (float) $r->total_commission,
                    'avg_commission_rate' => $this->rate((float) $r->total_subtotal, (float) $r->total_commission),
                ];
            })
            ->sortByDesc('total_subtotal')
            ->values();
    }

    /**
     * 기간 전체 합계 (3종 리포트 공통 — 그룹 기준과 무관하게 동일).
     *
     * @return array{line_count: int, total_quantity: int, total_subtotal: float, total_commission: float}
     */
    public function totals(string $from, string $to): array
    {
        $row = Performance::query()
            ->where('status', Performance::STATUS_APPROVED)
            ->whereBetween('performance_date', [$from, $to])
            ->selectRaw('COUNT(*) as line_count, COALESCE(SUM(quantity), 0) as total_quantity, COALESCE(SUM(subtotal), 0) as total_subtotal, COALESCE(SUM(commission_amount), 0) as total_commission')
            ->first();

        return [
            'line_count' => (int) $row->line_count,
            'total_quantity' => (int) $row->total_quantity,
            'total_subtotal' => (float) $row->total_subtotal,
            'total_commission' => (float) $row->total_commission,
        ];
    }

    /**
     * approved 실적을 주어진 컬럼으로 그룹핑해 측정값을 합산한다.
     *
     * @param  'company_id'|'created_by'|'product_id'  $groupColumn  내부 고정값(사용자 입력 아님)
     */
    private function aggregate(string $from, string $to, string $groupColumn): Collection
    {
        return Performance::query()
            ->where('status', Performance::STATUS_APPROVED)
            ->whereBetween('performance_date', [$from, $to])
            ->selectRaw("{$groupColumn}, COUNT(*) as line_count, COALESCE(SUM(quantity), 0) as total_quantity, COALESCE(SUM(subtotal), 0) as total_subtotal, COALESCE(SUM(commission_amount), 0) as total_commission")
            ->groupBy($groupColumn)
            ->get();
    }

    /**
     * 평균 수수료율(%) = 수수료액 / 매출액 × 100 (소수 1자리). 매출 0 이면 0.
     */
    private function rate(float $subtotal, float $commission): float
    {
        return $subtotal > 0 ? round($commission / $subtotal * 100, 1) : 0.0;
    }

    /**
     * @return array{0: string, 1: string} [start, end]
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
}
