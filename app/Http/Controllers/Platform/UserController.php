<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 플랫폼 — 전역 사용자 조회 (모든 제약사 계정). (GAP-10 MT-6, super_admin)
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

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
            ->orderByRaw("FIELD(role, 'super_admin', 'admin', 'sales')")
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
}
