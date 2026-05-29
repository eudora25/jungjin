<?php

namespace App\Providers;

use App\Policies\CommissionSummaryPolicy;
use App\Policies\MonthlyReportPolicy;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

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
