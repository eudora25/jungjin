<?php

namespace App\Http\Controllers;

use App\Models\Performance;
use App\Models\SalesQuota;
use App\Models\Settlement;
use App\Services\CommissionSummaryService;
use App\Services\QuotaAchievementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SalesDashboardController extends Controller
{
    public function __construct(
        private readonly QuotaAchievementService $achievement,
        private readonly CommissionSummaryService $commission,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $month = now()->format('Y-m');
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $statusCounts = Performance::query()
            ->where('created_by', $user->id)
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $thisMonthSubtotal = Performance::query()
            ->where('created_by', $user->id)
            ->whereBetween('performance_date', [$monthStart, $monthEnd])
            ->sum(DB::raw('quantity * unit_price'));

        // 이번달 내 수수료 합계 (GAP-3) — approved 실적의 commission_amount 합산
        $thisMonthCommission = $this->commission->monthlyCommissionFor($user->id, $month);

        // "내 거래처" 정의: 이번달 내가 등록한 실적의 company_id 집합
        $myCompanyIdsThisMonth = Performance::query()
            ->where('created_by', $user->id)
            ->whereBetween('performance_date', [$monthStart, $monthEnd])
            ->whereNotNull('company_id')
            ->distinct()
            ->pluck('company_id')
            ->all();

        $settlementStatusCounts = collect();
        $settlementTotals = (object) ['total_subtotal' => 0, 'total_commission' => 0];
        $recentSettlements = collect();

        if ($myCompanyIdsThisMonth !== []) {
            $settlementStatusCounts = Settlement::query()
                ->where('period_month', $month)
                ->whereIn('company_id', $myCompanyIdsThisMonth)
                ->select('status', DB::raw('COUNT(*) as cnt'))
                ->groupBy('status')
                ->pluck('cnt', 'status');

            $settlementTotals = Settlement::query()
                ->where('period_month', $month)
                ->whereIn('company_id', $myCompanyIdsThisMonth)
                ->selectRaw('COALESCE(SUM(total_subtotal), 0) as total_subtotal, COALESCE(SUM(total_commission), 0) as total_commission')
                ->first();

            $recentSettlements = Settlement::query()
                ->where('period_month', $month)
                ->whereIn('company_id', $myCompanyIdsThisMonth)
                ->with(['company:id,company_name'])
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(fn (Settlement $s) => [
                    'id' => $s->id,
                    'settlement_no' => $s->settlement_no,
                    'company_name' => $s->company?->company_name,
                    'status' => $s->status,
                    'total_subtotal' => (string) $s->total_subtotal,
                    'total_commission' => (string) $s->total_commission,
                ]);
        }

        // P2-8-1: 최근 12개월 본인 승인 실적 월별 합계 (차트)
        $chartStart = now()->startOfMonth()->subMonths(11)->toDateString();
        $monthlySums = Performance::query()
            ->where('created_by', $user->id)
            ->where('status', Performance::STATUS_APPROVED)
            ->where('performance_date', '>=', $chartStart)
            ->selectRaw("DATE_FORMAT(performance_date, '%Y-%m') as ym, COALESCE(SUM(subtotal), 0) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $chartLabels = [];
        $chartData = [];
        $cursor = now()->startOfMonth()->subMonths(11);
        for ($i = 0; $i < 12; $i++) {
            $ym = $cursor->format('Y-m');
            $chartLabels[] = $ym;
            $chartData[] = (float) ($monthlySums[$ym] ?? 0);
            $cursor->addMonth();
        }

        // P2-8-2: 최근 반려된 실적 (최대 5건, 사유 포함)
        $recentRejected = Performance::query()
            ->where('created_by', $user->id)
            ->where('status', Performance::STATUS_REJECTED)
            ->with([
                'company:id,company_name',
                'product:id,product_name',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Performance $p) => [
                'id' => $p->id,
                'performance_no' => $p->performance_no,
                'performance_date' => $p->performance_date?->toDateString(),
                'company_name' => $p->company?->company_name,
                'product_name' => $p->product?->product_name,
                'subtotal' => (string) $p->subtotal,
                'rejected_reason' => $p->rejected_reason,
                'updated_at' => $p->updated_at?->toDateTimeString(),
            ]);

        $recentPerformances = Performance::query()
            ->where('created_by', $user->id)
            ->with([
                'company:id,company_name',
                'product:id,product_name',
            ])
            ->orderByDesc('performance_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (Performance $p) => [
                'id' => $p->id,
                'performance_no' => $p->performance_no,
                'performance_date' => $p->performance_date?->toDateString(),
                'company_name' => $p->company?->company_name,
                'product_name' => $p->product?->product_name,
                'quantity' => (int) $p->quantity,
                'unit_price' => (string) $p->unit_price,
                'subtotal' => (string) $p->subtotal,
                'status' => $p->status,
            ]);

        // GAP-4: 내 담당 거래처 (최대 5건 + 총 카운트)
        $myAssignedCompanyTotal = $user->assignedCompanies()->count();
        $myAssignedCompanies = $user->assignedCompanies()
            ->orderBy('company_name')
            ->limit(5)
            ->get(['companies.id', 'company_name', 'default_commission_grade'])
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'company_name' => $c->company_name,
                'default_commission_grade' => $c->default_commission_grade,
            ]);

        $myQuota = SalesQuota::query()
            ->where('user_id', $user->id)
            ->where('period_type', 'monthly')
            ->where('period', $month)
            ->whereNull('product_id')
            ->first();

        $myQuotaData = null;
        if ($myQuota) {
            $result = $this->achievement->calculate($user->id, 'monthly', $month);
            $myQuotaData = [
                'period' => $myQuota->period,
                'target_amount' => (float) $myQuota->target_amount,
                'achieved_amount' => $result['achieved_amount'],
                'achievement_rate' => $myQuota->target_amount > 0
                    ? round($result['achieved_amount'] / (float) $myQuota->target_amount * 100, 1)
                    : 0,
            ];
        }

        return Inertia::render('Sales/Dashboard', [
            'month' => $month,
            'statusCounts' => [
                'draft' => (int) ($statusCounts[Performance::STATUS_DRAFT] ?? 0),
                'submitted' => (int) ($statusCounts[Performance::STATUS_SUBMITTED] ?? 0),
                'reviewed' => (int) ($statusCounts[Performance::STATUS_REVIEWED] ?? 0),
                'approved' => (int) ($statusCounts[Performance::STATUS_APPROVED] ?? 0),
                'rejected' => (int) ($statusCounts[Performance::STATUS_REJECTED] ?? 0),
                'cancelled' => (int) ($statusCounts[Performance::STATUS_CANCELLED] ?? 0),
            ],
            'thisMonthSubtotal' => (float) $thisMonthSubtotal,
            'thisMonthCommission' => (float) $thisMonthCommission,
            'settlementSummary' => [
                'companyCount' => count($myCompanyIdsThisMonth),
                'statusCounts' => [
                    'draft' => (int) ($settlementStatusCounts[Settlement::STATUS_DRAFT] ?? 0),
                    'confirmed' => (int) ($settlementStatusCounts[Settlement::STATUS_CONFIRMED] ?? 0),
                    'paid' => (int) ($settlementStatusCounts[Settlement::STATUS_PAID] ?? 0),
                    'cancelled' => (int) ($settlementStatusCounts[Settlement::STATUS_CANCELLED] ?? 0),
                ],
                'totalSubtotal' => (float) ($settlementTotals->total_subtotal ?? 0),
                'totalCommission' => (float) ($settlementTotals->total_commission ?? 0),
            ],
            'recentSettlements' => $recentSettlements,
            'recentPerformances' => $recentPerformances,
            'myMonthlyChart' => [
                'labels' => $chartLabels,
                'data' => $chartData,
            ],
            'recentRejected' => $recentRejected,
            'myQuota' => $myQuotaData,
            'myAssignedCompanies' => $myAssignedCompanies,
            'myAssignedCompanyTotal' => $myAssignedCompanyTotal,
        ]);
    }
}
