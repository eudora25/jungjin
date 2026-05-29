<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 영업사원 목록. 내부적으로 `users` 테이블에서 role=sales 를 조회한다.
 * 가입·권한 변경·비활성화 같은 관리 기능은 `UserController`(P0-2)에서 다룬다.
 */
class SalesRepController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search'));

        $sales = User::query()
            ->where('role', 'sales')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Clients/Sales/Index', [
            'sales' => $sales,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }
}
