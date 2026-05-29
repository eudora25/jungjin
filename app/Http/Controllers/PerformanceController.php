<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectPerformanceRequest;
use App\Http\Requests\StorePerformanceRequest;
use App\Http\Requests\UpdatePerformanceRequest;
use App\Models\ChangeReason;
use App\Models\Company;
use App\Models\Performance;
use App\Models\Product;
use App\Models\Settlement;
use App\Services\Performance\PerformanceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class PerformanceController extends Controller
{
    public function __construct(private readonly PerformanceResolver $resolver) {}

    /**
     * 실적 목록 (S3 에서 필터 확장).
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Performance::class);

        $user = $request->user();

        $status = $request->input('status');
        $from = $request->input('from');
        $to = $request->input('to');
        $companyId = $request->integer('company_id') ?: null;
        $productId = $request->integer('product_id') ?: null;

        $performances = Performance::query()
            ->with(['company:id,company_name', 'product:id,product_name,insurance_code', 'creator:id,name'])
            ->when(! $user->isPharma(), fn ($q) => $q->where('created_by', $user->id))
            ->when(in_array($status, Performance::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when($from, fn ($q) => $q->where('performance_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('performance_date', '<=', $to))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->orderByDesc('performance_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Performance/Index', [
            'performances' => $performances,
            'filters' => [
                'status' => $status,
                'from' => $from,
                'to' => $to,
                'company_id' => $companyId,
                'product_id' => $productId,
            ],
            'statuses' => Performance::STATUSES,
            'can' => [
                'create' => $user->can('create', Performance::class),
                'viewCreator' => $user->isPharma(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Performance::class);

        return Inertia::render('Performance/Create', [
            'today' => now()->toDateString(),
        ]);
    }

    /**
     * AJAX — 등록 폼에서 거래처/제품/일자를 선택하면 스냅샷 미리보기를 내려준다.
     * 실제 저장은 store() 에서 다시 해석한다 (방어적 일관성).
     */
    public function resolvePreview(Request $request): JsonResponse
    {
        $this->authorize('create', Performance::class);

        $data = $request->validate([
            'company_id' => ['required', 'integer'],
            'product_id' => ['required', 'integer'],
            'performance_date' => ['required', 'date'],
            'quantity' => ['nullable', 'integer'],
        ]);

        $company = Company::findOrFail($data['company_id']);
        $product = Product::findOrFail($data['product_id']);

        $resolved = $this->resolver->resolve($company, $product, $data['performance_date']);

        $qty = (int) ($data['quantity'] ?? 0);
        $unit = (float) $resolved['unit_price'];
        $rate = $resolved['commission_rate'];
        $subtotal = $qty * $unit;
        $commission = $rate !== null ? $subtotal * ((float) $rate) / 100 : null;

        return response()->json([
            ...$resolved,
            'preview' => [
                'quantity' => $qty,
                'unit_price' => $unit,
                'commission_rate' => $rate,
                'subtotal' => round($subtotal, 2),
                'commission_amount' => $commission !== null ? round($commission, 2) : null,
            ],
            'company' => [
                'id' => $company->id,
                'company_name' => $company->company_name,
                'default_commission_grade' => $company->default_commission_grade,
                'is_approved' => $company->isApproved(),
                'approval_status' => $company->approval_status,
            ],
            'product' => [
                'id' => $product->id,
                'product_name' => $product->product_name,
                'insurance_code' => $product->insurance_code,
                'is_salable' => $product->isSalable(),
                'approval_status' => $product->approval_status,
                'status' => $product->status,
            ],
        ]);
    }

    public function store(StorePerformanceRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $company = Company::findOrFail($data['company_id']);
        $product = Product::findOrFail($data['product_id']);

        if (! $company->isApproved()) {
            return back()
                ->withInput()
                ->withErrors(['company_id' => "선택한 거래처({$company->company_name})는 미승인 상태입니다."]);
        }

        if (! $product->isSalable()) {
            return back()
                ->withInput()
                ->withErrors(['product_id' => "선택한 제품({$product->product_name})은 등록할 수 없는 상태입니다. (단종 또는 미승인)"]);
        }

        $perf = DB::transaction(function () use ($company, $product, $data, $user): Performance {
            $perf = new Performance;
            $this->resolver->fill($perf, $company, $product, [
                'performance_no' => Performance::nextNumberFor($data['performance_date']),
                'performance_date' => $data['performance_date'],
                'quantity' => $data['quantity'],
                'note' => $data['note'] ?? null,
                'status' => Performance::STATUS_DRAFT,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
            ChangeReason::with('실적 등록', fn () => $perf->save());

            return $perf;
        });

        return redirect()
            ->route('performance.show', $perf)
            ->with('success', "실적 {$perf->performance_no} 이 등록되었습니다.");
    }

    public function show(Performance $performance): Response
    {
        $this->authorize('view', $performance);

        $performance->load([
            'company:id,company_name,business_registration_number,default_commission_grade',
            'product:id,product_name,insurance_code,product_code,approval_status,status',
            'priceOverride:id,override_unit_price,override_commission_rate,effective_from,effective_to',
            'priceRecord:id,price_type,amount,effective_from,effective_to',
            'commissionRateRecord:id,base_month,effective_from,effective_to',
            'creator:id,name',
            'updater:id,name',
            'submitter:id,name',
            'reviewer:id,name',
            'approver:id,name',
            'files:id,performance_id,original_name,size,extension',
        ]);

        $activities = Activity::query()
            ->where('subject_type', Performance::class)
            ->where('subject_id', $performance->id)
            ->with('causer:id,name')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (Activity $a) => [
                'id' => $a->id,
                'log_name' => $a->log_name,
                'description' => $a->description,
                'event' => $a->event,
                'causer' => $a->causer ? ['id' => $a->causer->id, 'name' => $a->causer->name] : null,
                'properties' => $a->properties->toArray(),
                'created_at' => $a->created_at,
            ]);

        $user = request()->user();

        return Inertia::render('Performance/Show', [
            'performance' => $performance,
            'activities' => $activities,
            'can' => [
                'update' => $user->can('update', $performance),
                'delete' => $user->can('delete', $performance),
                'submit' => $user->can('submit', $performance),
                'review' => $user->can('review', $performance),
                'approve' => $user->can('approve', $performance),
                'reject' => $user->can('reject', $performance),
                'cancel' => $user->can('cancel', $performance),
            ],
        ]);
    }

    public function edit(Performance $performance): Response
    {
        $this->authorize('update', $performance);

        $performance->load([
            'company:id,company_name,business_registration_number',
            'product:id,product_name,insurance_code',
        ]);

        return Inertia::render('Performance/Edit', [
            'performance' => $performance,
        ]);
    }

    public function update(UpdatePerformanceRequest $request, Performance $performance): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // 실적일이 바뀌면 스냅샷을 재해석한다 (거래처/제품은 고정)
        $dateChanged = $performance->performance_date->toDateString() !== (string) $data['performance_date'];

        ChangeReason::with('실적 수정', function () use ($performance, $data, $user, $dateChanged) {
            $performance->fill([
                'performance_date' => $data['performance_date'],
                'quantity' => $data['quantity'],
                'note' => $data['note'] ?? null,
                'updated_by' => $user->id,
            ]);

            if ($dateChanged) {
                $resolved = $this->resolver->resolve(
                    $performance->company,
                    $performance->product,
                    $data['performance_date'],
                );
                $performance->fill($resolved);
            }

            $performance->save();
        });

        return redirect()
            ->route('performance.show', $performance)
            ->with('success', '실적이 수정되었습니다.');
    }

    public function destroy(Performance $performance): RedirectResponse
    {
        $this->authorize('delete', $performance);

        ChangeReason::with('실적 삭제', fn () => $performance->delete());

        return redirect()
            ->route('performance.index')
            ->with('success', '실적이 삭제되었습니다.');
    }

    /**
     * draft|rejected → submitted
     */
    public function submit(Request $request, Performance $performance): RedirectResponse
    {
        $this->authorize('submit', $performance);

        ChangeReason::with('실적 제출', function () use ($performance, $request) {
            $performance->update([
                'status' => Performance::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'submitted_by' => $request->user()->id,
                'rejected_reason' => null,
                'reviewed_at' => null,
                'reviewed_by' => null,
                'approved_at' => null,
                'approved_by' => null,
                'updated_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', '실적을 제출했습니다.');
    }

    /**
     * submitted → reviewed
     */
    public function review(Request $request, Performance $performance): RedirectResponse
    {
        $this->authorize('review', $performance);

        ChangeReason::with('실적 검수', function () use ($performance, $request) {
            $performance->update([
                'status' => Performance::STATUS_REVIEWED,
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', '검수를 완료했습니다.');
    }

    /**
     * reviewed → approved
     */
    public function approve(Request $request, Performance $performance): RedirectResponse
    {
        $this->authorize('approve', $performance);

        ChangeReason::with('실적 승인', function () use ($performance, $request) {
            $performance->update([
                'status' => Performance::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        });

        $month = $performance->performance_date->format('Y-m');
        $lockedSettlement = Settlement::query()
            ->where('company_id', $performance->company_id)
            ->where('period_month', $month)
            ->whereIn('status', [Settlement::STATUS_CONFIRMED, Settlement::STATUS_PAID])
            ->first();

        if ($lockedSettlement) {
            return back()
                ->with('success', '실적을 승인했습니다.')
                ->with('warning', "{$month} 정산({$lockedSettlement->settlement_no})이 이미 확정되어 있습니다. 필요 시 재계산하세요.");
        }

        return back()->with('success', '실적을 승인했습니다.');
    }

    /**
     * submitted|reviewed → rejected
     */
    public function reject(RejectPerformanceRequest $request, Performance $performance): RedirectResponse
    {
        $reason = (string) $request->validated('reason');

        ChangeReason::with($reason, function () use ($performance, $request, $reason) {
            $performance->update([
                'status' => Performance::STATUS_REJECTED,
                'rejected_reason' => $reason,
                'submitted_at' => null,
                'submitted_by' => null,
                'reviewed_at' => null,
                'reviewed_by' => null,
                'approved_at' => null,
                'approved_by' => null,
                'updated_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', '실적을 반려했습니다.');
    }

    /**
     * 관리자 취소 (원장 무효화)
     */
    public function cancel(Request $request, Performance $performance): RedirectResponse
    {
        $this->authorize('cancel', $performance);

        ChangeReason::with('실적 취소', function () use ($performance, $request) {
            $performance->update([
                'status' => Performance::STATUS_CANCELLED,
                'updated_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', '실적을 취소했습니다.');
    }
}
