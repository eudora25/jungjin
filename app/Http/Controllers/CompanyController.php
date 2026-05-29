<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use App\Models\CompanySalesAssignment;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Company::class);

        $search = trim((string) $request->input('search'));
        $status = $request->input('status');
        $approval = $request->input('approval_status');

        $companies = Company::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('company_name', 'like', "%{$search}%")
                        ->orWhere('business_registration_number', 'like', "%{$search}%")
                        ->orWhere('representative_name', 'like', "%{$search}%")
                        ->orWhere('contact_person_name', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, ['active', 'inactive'], true), fn ($q) => $q->where('status', $status))
            ->when(
                in_array($approval, ['pending', 'approved', 'rejected'], true),
                fn ($q) => $q->where('approval_status', $approval),
            )
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'approval_status' => $approval,
            ],
            'can' => [
                'create' => $request->user()->can('create', Company::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Company::class);

        return Inertia::render('Companies/Create');
    }

    /**
     * 거래처 자동완성 (제품 거래처 예외 등록 모달, 실적 등록 폼 등에서 사용).
     *
     * GAP-4: sales가 호출하면 본인 담당 거래처를 우선 정렬해 노출한다.
     * `assigned_to_me=1` (sales) 또는 `assigned_user_id=N` (admin) 으로 명시적 필터도 가능.
     */
    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Company::class);

        $q = trim((string) $request->input('q'));
        $user = $request->user();

        $assignedUserId = null;
        if ($request->boolean('assigned_to_me') && $user) {
            $assignedUserId = $user->id;
        } elseif ($user?->isAdmin() && $request->filled('assigned_user_id')) {
            $assignedUserId = $request->integer('assigned_user_id');
        }

        $items = Company::query()
            ->active()
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($qb) use ($q) {
                    $qb->where('company_name', 'like', "%{$q}%")
                        ->orWhere('business_registration_number', 'like', "%{$q}%")
                        ->orWhere('representative_name', 'like', "%{$q}%")
                        ->orWhere('contact_person_name', 'like', "%{$q}%");
                });
            })
            ->when(
                $assignedUserId !== null,
                fn ($qb) => $qb->whereHas('salesAssignments', fn ($s) => $s->where('user_id', $assignedUserId)),
            )
            ->when(
                // sales 가 자기 자신의 우선 노출만 원할 때(필터 X 단순 정렬): 담당 거래처 먼저
                $assignedUserId === null && $user?->isSales(),
                function ($qb) use ($user) {
                    $qb->leftJoin('company_sales_assignments as csa', function ($j) use ($user) {
                        $j->on('csa.company_id', '=', 'companies.id')->where('csa.user_id', '=', $user->id);
                    })->orderByRaw('CASE WHEN csa.id IS NULL THEN 1 ELSE 0 END');
                },
            )
            ->orderBy('company_name')
            ->limit(15)
            ->get(['companies.id', 'company_name', 'business_registration_number', 'default_commission_grade', 'partner_type']);

        return response()->json($items);
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $company = Company::create($data);

        return redirect()
            ->route('companies.show', $company)
            ->with('success', '업체를 등록했습니다.');
    }

    public function show(Request $request, Company $company): Response
    {
        $this->authorize('view', $company);

        $company->loadMissing(['creator:id,name,email', 'updater:id,name,email', 'approver:id,name,email']);

        $recentSettlements = Settlement::query()
            ->where('company_id', $company->id)
            ->orderByDesc('period_month')
            ->limit(6)
            ->get([
                'id',
                'settlement_no',
                'period_month',
                'status',
                'line_count',
                'total_quantity',
                'total_subtotal',
                'total_commission',
                'calculated_at',
            ]);

        // GAP-4: 담당 영업사원 배정 현황 + 배정 가능 영업사원 후보
        $salesAssignments = $company->salesAssignments()
            ->with(['salesUser:id,name,email,is_active', 'assigner:id,name'])
            ->orderByDesc('assigned_at')
            ->get()
            ->map(fn (CompanySalesAssignment $a) => [
                'id' => $a->id,
                'user_id' => $a->user_id,
                'user_name' => $a->salesUser?->name,
                'user_email' => $a->salesUser?->email,
                'user_active' => (bool) $a->salesUser?->is_active,
                'assigned_at' => $a->assigned_at?->toIso8601String(),
                'assigner_name' => $a->assigner?->name,
            ]);

        $assignedUserIds = $salesAssignments->pluck('user_id')->all();
        $availableSalesUsers = User::query()
            ->where('role', 'sales')
            ->where('is_active', true)
            ->whereNotIn('id', $assignedUserIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $assignmentPolicy = $request->user();

        return Inertia::render('Companies/Show', [
            'company' => $company,
            'recentSettlements' => $recentSettlements,
            'salesAssignments' => $salesAssignments,
            'availableSalesUsers' => $availableSalesUsers,
            'can' => [
                'update' => $request->user()->can('update', $company),
                'delete' => $request->user()->can('delete', $company),
                'manageSalesAssignments' => $assignmentPolicy->can('create', CompanySalesAssignment::class),
            ],
        ]);
    }

    public function edit(Company $company): Response
    {
        $this->authorize('update', $company);

        return Inertia::render('Companies/Edit', [
            'company' => $company,
        ]);
    }

    public function update(UpdateCompanyRequest $request, Company $company): RedirectResponse
    {
        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        if (isset($data['approval_status']) && $data['approval_status'] !== $company->approval_status) {
            if ($data['approval_status'] === 'approved') {
                $data['approved_at'] = now();
                $data['approved_by'] = $request->user()->id;
            } else {
                $data['approved_at'] = null;
                $data['approved_by'] = null;
            }
        }

        $company->update($data);

        return redirect()
            ->route('companies.show', $company)
            ->with('success', '업체 정보를 수정했습니다.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $this->authorize('delete', $company);

        $company->delete();

        return redirect()
            ->route('companies.index')
            ->with('success', '업체를 삭제했습니다.');
    }
}
