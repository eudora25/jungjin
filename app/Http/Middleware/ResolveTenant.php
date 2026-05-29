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

        if ($user) {
            if ($user->isPlatform()) {
                // 플랫폼 운영자: 특정 제약사로 진입(임퍼서네이션)한 경우에만 그 테넌트로 스코프
                $impersonated = $request->session()->get('impersonated_tenant_id');
                if ($impersonated) {
                    $context->set((int) $impersonated);
                }
            } elseif ($user->tenant_id) {
                // admin(pharma)/sales(cso): 소속 제약사로 격리
                $context->set((int) $user->tenant_id);
            }
        }

        return $next($request);
    }
}
