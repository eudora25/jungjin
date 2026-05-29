<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Models\Performance;
use App\Models\SalesQuota;
use App\Models\Settlement;
use App\Models\User;
use App\Services\QuotaAchievementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly QuotaAchievementService $achievement) {}

    public function index(Request $request): Response|RedirectResponse
    {
        // 플랫폼 운영자(super_admin)는 플랫폼 영역으로 진입 (GAP-10 MT-6)
        if ($request->user()->isPlatform()) {
            return redirect()->route('platform.tenants.index');
        }

        $year = now()->year;

        $stats = [
            'total_performance' => Performance::where('status', Performance::STATUS_APPROVED)->sum('subtotal'),
            'pending_settlements' => Settlement::where('status', Settlement::STATUS_DRAFT)->count(),
            'notice_count' => Notice::count(),
            'user_count' => User::count(),
        ];

        $monthlyPerformance = Performance::query()
            ->where('status', Performance::STATUS_APPROVED)
            ->whereYear('performance_date', $year)
            ->select(
                DB::raw('MONTH(performance_date) as month'),
                DB::raw('SUM(subtotal) as amount'),
            )
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('amount', 'month');

        $monthlyPoints = collect(range(1, 12))->map(fn ($m) => [
            'month' => sprintf('%02d월', $m),
            'amount' => (float) ($monthlyPerformance[$m] ?? 0),
        ]);

        $topProducts = Performance::query()
            ->where('performances.status', Performance::STATUS_APPROVED)
            ->whereYear('performances.performance_date', $year)
            ->join('products', 'products.id', '=', 'performances.product_id')
            ->select('products.product_name as name', DB::raw('SUM(performances.subtotal) as amount'))
            ->groupBy('products.id', 'products.product_name')
            ->orderByDesc('amount')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'amount' => (float) $r->amount]);

        $recentNotices = Notice::query()
            ->with('author:id,name,email')
            ->pinnedFirst()
            ->limit(5)
            ->get()
            ->map(fn (Notice $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'author' => $n->author?->name ?? '-',
                'created_at' => $n->created_at->format('Y-m-d'),
            ]);

        $currentMonth = now()->format('Y-m');
        $quotaAchievements = SalesQuota::query()
            ->with('user:id,name')
            ->where('period_type', 'monthly')
            ->where('period', $currentMonth)
            ->whereNull('product_id')
            ->get()
            ->map(function (SalesQuota $q) {
                $result = $this->achievement->calculate($q->user_id, 'monthly', $q->period);

                return [
                    'user_name' => $q->user?->name,
                    'target_amount' => (float) $q->target_amount,
                    'achieved_amount' => $result['achieved_amount'],
                    'achievement_rate' => $q->target_amount > 0
                        ? round($result['achieved_amount'] / (float) $q->target_amount * 100, 1)
                        : 0,
                ];
            });

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'monthlyPerformance' => $monthlyPoints,
            'topProducts' => $topProducts,
            'recentNotices' => $recentNotices,
            'quotaAchievements' => $quotaAchievements,
        ]);
    }
}
