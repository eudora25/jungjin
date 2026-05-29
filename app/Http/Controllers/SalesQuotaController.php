<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesQuotaRequest;
use App\Models\Product;
use App\Models\SalesQuota;
use App\Models\User;
use App\Services\QuotaAchievementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesQuotaController extends Controller
{
    public function __construct(private readonly QuotaAchievementService $achievement)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SalesQuota::class);

        $userId = $request->integer('user_id') ?: null;
        $periodType = $request->input('period_type');
        $period = $request->input('period');

        $quotas = SalesQuota::query()
            ->with(['user:id,name', 'product:id,product_name'])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(in_array($periodType, ['monthly', 'quarterly', 'yearly'], true), fn ($q) => $q->where('period_type', $periodType))
            ->when($period, fn ($q) => $q->where('period', $period))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (SalesQuota $quota) {
                $result = $this->achievement->calculate(
                    $quota->user_id,
                    $quota->period_type,
                    $quota->period,
                    $quota->product_id,
                );

                return [
                    'id' => $quota->id,
                    'user_id' => $quota->user_id,
                    'user_name' => $quota->user?->name,
                    'product_id' => $quota->product_id,
                    'product_name' => $quota->product?->product_name,
                    'period_type' => $quota->period_type,
                    'period' => $quota->period,
                    'target_amount' => (float) $quota->target_amount,
                    'achieved_amount' => $result['achieved_amount'],
                    'achievement_rate' => $quota->target_amount > 0
                        ? round($result['achieved_amount'] / (float) $quota->target_amount * 100, 1)
                        : 0,
                ];
            });

        $salesUsers = User::where('role', 'sales')->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $products = Product::orderBy('product_name')
            ->get(['id', 'product_name']);

        return Inertia::render('SalesQuotas/Index', [
            'quotas' => $quotas,
            'salesUsers' => $salesUsers,
            'products' => $products,
            'filters' => [
                'user_id' => $userId,
                'period_type' => $periodType,
                'period' => $period,
            ],
        ]);
    }

    public function store(StoreSalesQuotaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        SalesQuota::create($data);

        return back()->with('success', '목표를 등록했습니다.');
    }

    public function update(StoreSalesQuotaRequest $request, SalesQuota $salesQuota): RedirectResponse
    {
        $this->authorize('update', $salesQuota);

        $salesQuota->update($request->validated());

        return back()->with('success', '목표를 수정했습니다.');
    }

    public function destroy(SalesQuota $salesQuota): RedirectResponse
    {
        $this->authorize('delete', $salesQuota);

        $salesQuota->delete();

        return back()->with('success', '목표를 삭제했습니다.');
    }
}
