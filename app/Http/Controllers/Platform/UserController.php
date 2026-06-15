<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreUserRequest;
use App\Http\Requests\Platform\UpdateUserRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 플랫폼 — 전역 사용자 CRUD (모든 제약사의 pharma·cso 계정). (GAP-10 후속-C §6.10, platform)
 * platform 역할 계정 자체는 UI 로 만들거나 수정하지 않는다(artisan 승격 — C-5). 대상이 platform 이면 차단.
 */
class UserController extends Controller
{
    private function ensurePlatform(Request $request): void
    {
        abort_unless($request->user()->isPlatform(), 403);
    }

    /** platform 계정은 UI 관리 대상이 아님(artisan 전용) — 수정/삭제/토글 대상에서 차단 */
    private function ensureManageable(User $user): void
    {
        abort_if($user->isPlatform(), 403, 'platform 계정은 콘솔(artisan)에서 관리합니다.');
    }

    /** 제약사 Select 옵션 (활성 입주 우선, 이름순) */
    private function tenantOptions(): array
    {
        return Tenant::query()
            ->orderByDesc('status')
            ->orderBy('name')
            ->get(['id', 'name', 'status'])
            ->map(fn (Tenant $t) => ['value' => $t->id, 'label' => $t->name, 'status' => $t->status])
            ->all();
    }

    public function index(Request $request): Response
    {
        $this->ensurePlatform($request);

        $search = trim((string) $request->input('search', ''));
        $role = $request->input('role');

        $users = User::query()
            ->with('tenant:id,name')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(in_array($role, User::ROLES, true), fn ($q) => $q->where('role', $role))
            ->orderByRaw("FIELD(role, 'platform', 'pharma', 'cso')")
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'is_active' => (bool) $u->is_active,
                'tenant_name' => $u->tenant?->name,
            ]);

        return Inertia::render('Platform/Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search, 'role' => $role],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensurePlatform($request);

        return Inertia::render('Platform/Users/Create', [
            'tenants' => $this->tenantOptions(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $data['is_active'] ?? true;

        $user = User::create($data);

        return redirect()
            ->route('platform.users.show', $user)
            ->with('success', "사용자 {$user->name} 을(를) 등록했습니다.");
    }

    public function show(Request $request, User $user): Response
    {
        $this->ensurePlatform($request);

        $user->loadMissing('tenant:id,name');

        return Inertia::render('Platform/Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => (bool) $user->is_active,
                'tenant_name' => $user->tenant?->name,
                'created_at' => $user->created_at?->toDateTimeString(),
            ],
            'manageable' => ! $user->isPlatform(),
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        $this->ensurePlatform($request);
        $this->ensureManageable($user);

        $user->loadMissing('tenant:id,name');

        return Inertia::render('Platform/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tenant_id' => $user->tenant_id,
                'is_active' => (bool) $user->is_active,
            ],
            'tenants' => $this->tenantOptions(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->ensureManageable($user);

        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()
            ->route('platform.users.show', $user)
            ->with('success', '사용자 정보를 수정했습니다.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->ensurePlatform($request);
        $this->ensureManageable($user);
        abort_if($request->user()->id === $user->id, 403);

        $user->delete();

        return redirect()
            ->route('platform.users.index')
            ->with('success', '사용자를 삭제했습니다.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->ensurePlatform($request);
        $this->ensureManageable($user);
        abort_if($request->user()->id === $user->id, 403);

        $user->is_active = ! $user->is_active;
        $user->save();

        return back()->with('success', $user->is_active ? '사용자를 활성화했습니다.' : '사용자를 비활성화했습니다.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->ensurePlatform($request);
        $this->ensureManageable($user);

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', '비밀번호를 재설정했습니다.');
    }
}
