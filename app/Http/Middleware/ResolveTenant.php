<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 현재 요청의 테넌트 컨텍스트 해석. (GAP-10 MT-3)
 *
 *  - admin/sales (tenant_id 보유) → 해당 제약사로 컨텍스트 설정 → 자동 격리.
 *  - super_admin → 컨텍스트 미설정(전역). (후속: 임퍼서네이션 시 세션 선택 테넌트 적용)
 *  - tenant_id 없는 사용자/게스트 → 미설정 (과도기 호환).
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $context = app(TenantContext::class);

        if ($user && ! $user->isSuperAdmin() && $user->tenant_id) {
            $context->set((int) $user->tenant_id);
        }

        // 후속(임퍼서네이션): super_admin 이 세션에서 테넌트를 선택했으면 그 값으로 설정.
        // if ($user && $user->isSuperAdmin() && $request->session()->has('impersonated_tenant_id')) {
        //     $context->set((int) $request->session()->get('impersonated_tenant_id'));
        // }

        return $next($request);
    }
}
