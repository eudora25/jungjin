<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $search = trim((string) $request->input('search'));
        $active = $request->input('active');

        // 테넌트 격리: pharma 는 **자사 테넌트의 cso** 만 본다(§6.9). User 는 BelongsToTenant 미사용 → 컨트롤러 스코프.
        $users = User::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('role', User::ROLE_CSO)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($active === '1', fn ($q) => $q->where('is_active', true))
            ->when($active === '0', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'active' => $active,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $data['is_active'] ?? true;

        // 테넌트·역할 서버 주입(클라이언트 입력 무시): 자사 테넌트의 cso 로 고정(§6.9)
        $data['tenant_id'] = $request->user()->tenant_id;
        $data['role'] = User::ROLE_CSO;

        $user = User::create($data);

        return redirect()
            ->route('users.show', $user)
            ->with('success', "사용자 {$user->name} 을(를) 등록했습니다.");
    }

    public function show(Request $request, User $user): Response
    {
        $this->authorize('view', $user);

        return Inertia::render('Users/Show', [
            'user' => $user,
            'can' => [
                'update' => $request->user()->can('update', $user),
                'delete' => $request->user()->can('delete', $user),
                'toggleActive' => $request->user()->can('toggleActive', $user),
                'resetPassword' => $request->user()->can('resetPassword', $user),
            ],
        ]);
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('Users/Edit', [
            'user' => $user,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // 자기 자신의 is_active/role 변경 차단
        if ($request->user()->id === $user->id) {
            unset($data['is_active'], $data['role']);
        }

        $user->update($data);

        return redirect()
            ->route('users.show', $user)
            ->with('success', '사용자 정보를 수정했습니다.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', '사용자를 삭제했습니다.');
    }

    /**
     * is_active 토글 — 활성/비활성 전환.
     */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->authorize('toggleActive', $user);

        $user->is_active = ! $user->is_active;
        $user->save();

        $msg = $user->is_active ? '사용자를 활성화했습니다.' : '사용자를 비활성화했습니다.';

        return back()->with('success', $msg);
    }

    /**
     * 관리자가 사용자의 비밀번호를 재설정.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorize('resetPassword', $user);

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', '비밀번호를 재설정했습니다.');
    }
}
