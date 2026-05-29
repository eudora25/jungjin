<?php

namespace App\Providers;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Policies\CommissionSummaryPolicy;
use App\Policies\MonthlyReportPolicy;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 테넌트 컨텍스트 — 요청 단위 싱글톤 (GAP-10 MT-3)
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // GAP-10 MT-5: 테넌트 권한 게이트 (모든 Policy 검사 이전에 실행)
        //  1) super_admin → 전체 통과 (플랫폼 운영자)
        //  2) admin/sales(테넌트 보유) → 다른 테넌트의 자원 접근 차단 (방어선; 1차 격리는 TenantScope)
        //  3) 그 외(null 테넌트/게스트) → 통과시켜 기존 Policy 로 위임 (과도기 호환)
        Gate::before(function (User $user, string $ability, array $arguments = []) {
            if ($user->isSuperAdmin()) {
                return true;
            }

            $model = $arguments[0] ?? null;

            if ($user->tenant_id
                && $model instanceof Model
                && in_array(BelongsToTenant::class, class_uses_recursive($model), true)
                && $model->tenant_id !== null
                && (int) $model->tenant_id !== (int) $user->tenant_id
            ) {
                return false; // 교차 테넌트 접근 거부
            }

            return null; // 기존 Policy 로 위임
        });

        // CommissionSummaryPolicy 는 모델에 묶이지 않으므로 Gate 로 명시 등록한다 (GAP-3).
        Gate::define('view-commission-summary', [CommissionSummaryPolicy::class, 'viewAny']);
        Gate::define('export-commission-summary', [CommissionSummaryPolicy::class, 'export']);
        Gate::define('view-commission-statement', [CommissionSummaryPolicy::class, 'viewStatement']);
        Gate::define('export-commission-statement', [CommissionSummaryPolicy::class, 'exportStatement']);

        // MonthlyReportPolicy 도 모델에 묶이지 않으므로 Gate 로 명시 등록한다 (GAP-6).
        Gate::define('view-monthly-report', [MonthlyReportPolicy::class, 'viewAny']);
        Gate::define('export-monthly-report', [MonthlyReportPolicy::class, 'export']);
    }
}
