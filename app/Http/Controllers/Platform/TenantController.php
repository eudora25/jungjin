<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantAdminRequest;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantAdminRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
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
            // 제약사별 관리자(pharma) 계정 — 목록에 노출
            ->with(['users' => fn ($q) => $q->where('role', User::ROLE_PHARMA)
                ->orderBy('name')
                ->select(['id', 'name', 'email', 'is_active', 'tenant_id'])])
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

        // 제약사(tenant) + 초기 관리자(pharma) 계정을 한 트랜잭션으로 동시 생성 (D-2)
        $tenant = DB::transaction(function () use ($data, $request) {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'business_registration_number' => $data['business_registration_number'] ?? null,
                'representative_name' => $data['representative_name'] ?? null,
                'postcode' => $data['postcode'] ?? null,
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'status' => $data['status'],
                'created_by' => $request->user()->id,
            ]);

            User::create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($data['admin_password']),
                'role' => User::ROLE_PHARMA,
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]);

            return $tenant;
        });

        return redirect()
            ->route('platform.tenants.show', $tenant)
            ->with('success', "제약사 [{$tenant->name}] 와 관리자 계정을 등록했습니다.");
    }

    public function show(Request $request, Tenant $tenant): Response
    {
        $this->authorize('view', $tenant);

        $tenant->loadCount('users');

        $search = trim((string) $request->input('search', ''));

        $users = $tenant->users()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByRaw("FIELD(role, 'pharma', 'cso')")
            ->orderBy('name')
            ->paginate(10, ['id', 'name', 'email', 'role', 'is_active'])
            ->withQueryString();

        return Inertia::render('Platform/Tenants/Show', [
            'tenant' => $tenant,
            'users' => $users,
            'filters' => ['search' => $search],
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

    /** 임퍼서네이션 — 특정 제약사로 진입 (GAP-10) */
    public function enter(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($request->user()->isPlatform(), 403);

        $request->session()->put('impersonated_tenant_id', $tenant->id);

        return redirect()
            ->route('dashboard')
            ->with('success', "제약사 [{$tenant->name}] (으)로 진입했습니다. 이제 해당 제약사 화면을 봅니다.");
    }

    /** 임퍼서네이션 종료 */
    public function exit(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isPlatform(), 403);

        $request->session()->forget('impersonated_tenant_id');

        return redirect()
            ->route('platform.tenants.index')
            ->with('success', '제약사 진입을 종료했습니다.');
    }

    /** 위임형(D-2) — 제약사 admin 계정 생성 */
    public function storeAdmin(StoreTenantAdminRequest $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validated();

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => User::ROLE_PHARMA,
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        return redirect()
            ->route('platform.tenants.show', $tenant)
            ->with('success', "제약사 [{$tenant->name}] 의 관리자 계정을 생성했습니다.");
    }

    /** 위임형(D-2) — 제약사 소속 계정(관리자·영업사원) 수정 */
    public function updateAdmin(UpdateTenantAdminRequest $request, Tenant $tenant, User $user): RedirectResponse
    {
        $this->authorizeTenantMember($tenant, $user);

        $data = $request->validated();

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        return redirect()
            ->route('platform.tenants.show', $tenant)
            ->with('success', $this->memberLabel($user)." 계정 [{$user->name}] 정보를 수정했습니다. (비밀번호는 '비밀번호 재설정'에서 변경)");
    }

    /** 위임형(D-2) — 제약사 소속 계정(관리자·영업사원) 삭제 */
    public function destroyAdmin(Request $request, Tenant $tenant, User $user): RedirectResponse
    {
        $this->authorize('manageAdmins', $tenant);
        $this->authorizeTenantMember($tenant, $user);

        $user->delete();

        return redirect()
            ->route('platform.tenants.show', $tenant)
            ->with('success', $this->memberLabel($user)." 계정 [{$user->name}] 을(를) 삭제했습니다.");
    }

    /** 위임형(D-2) — 제약사 소속 계정(관리자·영업사원) 비밀번호 재설정 */
    public function resetAdminPassword(Request $request, Tenant $tenant, User $user): RedirectResponse
    {
        $this->authorize('manageAdmins', $tenant);
        $this->authorizeTenantMember($tenant, $user);

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        return redirect()
            ->route('platform.tenants.show', $tenant)
            ->with('success', $this->memberLabel($user)." 계정 [{$user->name}] 의 비밀번호를 재설정했습니다.");
    }

    /** 대상 사용자가 해당 제약사 소속 계정(관리자·영업사원)인지 확인 — 아니면 404 */
    protected function authorizeTenantMember(Tenant $tenant, User $user): void
    {
        abort_unless($user->tenant_id === $tenant->id && ! $user->isPlatform(), 404);
    }

    /** 소속 계정의 역할 한글 라벨 */
    protected function memberLabel(User $user): string
    {
        return $user->isPharma() ? '관리자' : '영업사원';
    }
}
