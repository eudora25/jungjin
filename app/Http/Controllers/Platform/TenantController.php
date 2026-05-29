<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantAdminRequest;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 제약사(테넌트) 관리 — 플랫폼 운영자(super_admin) 전용. (GAP-10 MT-6)
 */
class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Tenant::class);

        $search = trim((string) $request->input('search', ''));

        $tenants = Tenant::query()
            ->withCount('users')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('business_registration_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Platform/Tenants/Index', [
            'tenants' => $tenants,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Tenant::class);

        return Inertia::render('Platform/Tenants/Create');
    }

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $tenant = Tenant::create($data);

        return redirect()
            ->route('platform.tenants.show', $tenant)
            ->with('success', "제약사 [{$tenant->name}] 를 등록했습니다.");
    }

    public function show(Request $request, Tenant $tenant): Response
    {
        $this->authorize('view', $tenant);

        $tenant->loadCount('users');

        $users = $tenant->users()
            ->orderByRaw("FIELD(role, 'admin', 'sales')")
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'is_active']);

        return Inertia::render('Platform/Tenants/Show', [
            'tenant' => $tenant,
            'users' => $users,
            'can' => [
                'update' => $request->user()->can('update', $tenant),
                'delete' => $request->user()->can('delete', $tenant),
                'manageAdmins' => $request->user()->can('manageAdmins', $tenant),
            ],
        ]);
    }

    public function edit(Tenant $tenant): Response
    {
        $this->authorize('update', $tenant);

        return Inertia::render('Platform/Tenants/Edit', [
            'tenant' => $tenant,
        ]);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update($request->validated());

        return redirect()
            ->route('platform.tenants.show', $tenant)
            ->with('success', '제약사 정보를 수정했습니다.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->authorize('delete', $tenant);

        $tenant->delete(); // soft delete

        return redirect()
            ->route('platform.tenants.index')
            ->with('success', "제약사 [{$tenant->name}] 를 삭제했습니다.");
    }

    /** 위임형(D-2) — 제약사 admin 계정 생성 */
    public function storeAdmin(StoreTenantAdminRequest $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validated();

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => User::ROLE_ADMIN,
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        return redirect()
            ->route('platform.tenants.show', $tenant)
            ->with('success', "제약사 [{$tenant->name}] 의 관리자 계정을 생성했습니다.");
    }
}
