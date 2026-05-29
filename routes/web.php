<?php

use App\Http\Controllers\CommissionSummaryController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyProductOverrideController;
use App\Http\Controllers\CompanySalesAssignmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\HospitalImportController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\NoticeFileController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\PerformanceFileController;
use App\Http\Controllers\PerformanceImportController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\PharmacyImportController;
use App\Http\Controllers\Platform\HospitalController as PlatformHospitalController;
use App\Http\Controllers\Platform\HospitalImportController as PlatformHospitalImportController;
use App\Http\Controllers\Platform\PharmacyController as PlatformPharmacyController;
use App\Http\Controllers\Platform\PharmacyImportController as PlatformPharmacyImportController;
use App\Http\Controllers\Platform\ProductController as PlatformProductController;
use App\Http\Controllers\Platform\TenantController as PlatformTenantController;
use App\Http\Controllers\Platform\UserController as PlatformUserController;
use App\Http\Controllers\ProductCommissionRateController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductFileController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ProductPriceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SalesDashboardController;
use App\Http\Controllers\SalesQuotaController;
use App\Http\Controllers\SalesRepController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\SettlementPaymentFileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // sales 전용 대시보드 (영업사원 개인화)
    Route::get('/sales/dashboard', [SalesDashboardController::class, 'index'])
        ->middleware('role:cso')
        ->name('sales.dashboard');

    Route::resource('notices', NoticeController::class);
    Route::get('/notices/{notice}/files/{file}/download', [NoticeFileController::class, 'download'])->name('notices.files.download');
    Route::resource('companies', CompanyController::class);

    // --- 영업사원-거래처 담당 배정 (GAP-4) ---
    Route::post('/companies/{company}/sales-assignments', [CompanySalesAssignmentController::class, 'store'])
        ->name('companies.sales-assignments.store');
    Route::delete('/companies/{company}/sales-assignments/{assignment}', [CompanySalesAssignmentController::class, 'destroy'])
        ->name('companies.sales-assignments.destroy');

    // --- 클라이언트 (약국/병원/영업사원) ---
    // CSV 일괄 등록 (resource 보다 먼저)
    Route::get('/pharmacies/import', [PharmacyImportController::class, 'form'])->name('pharmacies.import.form');
    Route::post('/pharmacies/import', [PharmacyImportController::class, 'handle'])->name('pharmacies.import.handle');
    Route::get('/hospitals/import', [HospitalImportController::class, 'form'])->name('hospitals.import.form');
    Route::post('/hospitals/import', [HospitalImportController::class, 'handle'])->name('hospitals.import.handle');

    Route::resource('pharmacies', PharmacyController::class)
        ->parameters(['pharmacies' => 'pharmacy']);
    Route::resource('hospitals', HospitalController::class);
    Route::get('/clients/sales', [SalesRepController::class, 'index'])
        ->middleware('role:pharma')
        ->name('sales.index');

    // 제품 검색 (자동완성용) — resource 보다 먼저 정의해 /products/{product} 와 충돌 방지
    Route::get('/products/search', [ProductController::class, 'search'])->name('products.search');

    // CSV 일괄 등록 (resource 보다 먼저)
    Route::get('/products/import', [ProductImportController::class, 'form'])->name('products.import.form');
    Route::post('/products/import', [ProductImportController::class, 'handle'])->name('products.import.handle');
    Route::get('/products/import/sample', [ProductImportController::class, 'sample'])->name('products.import.sample');

    Route::resource('products', ProductController::class);

    // 제품 상태 전이 액션 (admin 한정 — Policy에서 검사)
    Route::post('/products/{product}/submit', [ProductController::class, 'submit'])->name('products.submit');
    Route::post('/products/{product}/review', [ProductController::class, 'review'])->name('products.review');
    Route::post('/products/{product}/approve', [ProductController::class, 'approve'])->name('products.approve');
    Route::post('/products/{product}/reject', [ProductController::class, 'reject'])->name('products.reject');
    Route::post('/products/{product}/discontinue', [ProductController::class, 'discontinue'])->name('products.discontinue');

    // 제품 첨부 파일 (admin 업로드/삭제, 일반 사용자 다운로드)
    Route::post('/products/{product}/files', [ProductFileController::class, 'store'])->name('products.files.store');
    Route::delete('/products/{product}/files/{file}', [ProductFileController::class, 'destroy'])->name('products.files.destroy');
    Route::get('/products/{product}/files/{file}/download', [ProductFileController::class, 'download'])->name('products.files.download');

    Route::post('/products/{product}/prices', [ProductPriceController::class, 'store'])
        ->name('products.prices.store');
    Route::put('/products/{product}/prices/{price}', [ProductPriceController::class, 'update'])
        ->name('products.prices.update');
    Route::delete('/products/{product}/prices/{price}', [ProductPriceController::class, 'destroy'])
        ->name('products.prices.destroy');

    Route::post('/products/{product}/overrides', [CompanyProductOverrideController::class, 'store'])
        ->name('products.overrides.store');
    Route::put('/products/{product}/overrides/{override}', [CompanyProductOverrideController::class, 'update'])
        ->name('products.overrides.update');
    Route::delete('/products/{product}/overrides/{override}', [CompanyProductOverrideController::class, 'destroy'])
        ->name('products.overrides.destroy');

    // 거래처 자동완성 (예외 단가 등록 모달용) — companies resource 와 별도 라우트
    Route::get('/companies-search', [CompanyController::class, 'search'])->name('companies.search');

    Route::post('/products/{product}/commission-rates', [ProductCommissionRateController::class, 'store'])
        ->name('products.commission-rates.store');
    Route::put('/products/{product}/commission-rates/{rate}', [ProductCommissionRateController::class, 'update'])
        ->name('products.commission-rates.update');
    Route::delete('/products/{product}/commission-rates/{rate}', [ProductCommissionRateController::class, 'destroy'])
        ->name('products.commission-rates.destroy');

    // --- 실적(Performance) ---
    // resolve-preview / import 는 /performance/{performance} 와 충돌 방지 위해 resource 이전에 선언
    Route::post('/performance/resolve-preview', [PerformanceController::class, 'resolvePreview'])
        ->name('performance.resolve-preview');
    Route::get('/performance/import', [PerformanceImportController::class, 'form'])->name('performance.import.form');
    Route::post('/performance/import', [PerformanceImportController::class, 'handle'])->name('performance.import.handle');
    Route::get('/performance/import/sample', [PerformanceImportController::class, 'sample'])->name('performance.import.sample');
    Route::resource('performance', PerformanceController::class)
        ->parameters(['performance' => 'performance']);
    Route::post('/performance/{performance}/submit', [PerformanceController::class, 'submit'])
        ->name('performance.submit');
    Route::post('/performance/{performance}/review', [PerformanceController::class, 'review'])
        ->name('performance.review');
    Route::post('/performance/{performance}/approve', [PerformanceController::class, 'approve'])
        ->name('performance.approve');
    Route::post('/performance/{performance}/reject', [PerformanceController::class, 'reject'])
        ->name('performance.reject');
    Route::post('/performance/{performance}/cancel', [PerformanceController::class, 'cancel'])
        ->name('performance.cancel');
    Route::post('/performance/{performance}/files', [PerformanceFileController::class, 'store'])->name('performance.files.store');
    Route::delete('/performance/{performance}/files/{file}', [PerformanceFileController::class, 'destroy'])->name('performance.files.destroy');
    Route::get('/performance/{performance}/files/{file}/download', [PerformanceFileController::class, 'download'])->name('performance.files.download');

    Route::get('/settlements', [SettlementController::class, 'index'])->name('settlements.index');
    Route::post('/settlements', [SettlementController::class, 'store'])->name('settlements.store');
    Route::get('/settlements/{settlement}', [SettlementController::class, 'show'])->name('settlements.show');
    Route::get('/settlements/{settlement}/export.xlsx', [SettlementController::class, 'exportExcel'])->name('settlements.export.excel');
    Route::get('/settlements/{settlement}/export.pdf', [SettlementController::class, 'exportPdf'])->name('settlements.export.pdf');
    Route::post('/settlements/{settlement}/recalculate', [SettlementController::class, 'recalculate'])->name('settlements.recalculate');
    Route::post('/settlements/{settlement}/confirm', [SettlementController::class, 'confirm'])->name('settlements.confirm');
    Route::post('/settlements/{settlement}/pay', [SettlementController::class, 'pay'])->name('settlements.pay');
    Route::post('/settlements/{settlement}/cancel', [SettlementController::class, 'cancel'])->name('settlements.cancel');

    // --- 정산 지급 증빙 파일 (GAP-5-4) ---
    Route::post('/settlements/{settlement}/payment-files', [SettlementPaymentFileController::class, 'store'])
        ->name('settlements.payment-files.store');
    Route::delete('/settlements/{settlement}/payment-files/{file}', [SettlementPaymentFileController::class, 'destroy'])
        ->name('settlements.payment-files.destroy');
    Route::get('/settlements/{settlement}/payment-files/{file}/download', [SettlementPaymentFileController::class, 'download'])
        ->name('settlements.payment-files.download');

    // --- 영업사원별 수수료 명세 (GAP-3) ---
    Route::get('/commission-summary', [CommissionSummaryController::class, 'index'])->name('commission-summary.index');
    Route::get('/commission-summary/export.xlsx', [CommissionSummaryController::class, 'exportExcel'])->name('commission-summary.export.excel');
    Route::get('/commission-summary/users/{user}/statement', [CommissionSummaryController::class, 'statement'])->name('commission-summary.statement');
    Route::get('/commission-summary/users/{user}/statement.pdf', [CommissionSummaryController::class, 'statementPdf'])->name('commission-summary.statement.pdf');

    // --- 월간 보고서 (GAP-6) ---
    Route::get('/reports/monthly', [ReportsController::class, 'monthly'])->name('reports.monthly');
    Route::get('/reports/monthly/export.xlsx', [ReportsController::class, 'exportMonthly'])->name('reports.monthly.export.excel');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// GAP-10 MT-6: 플랫폼 운영자(super_admin) 전용 영역 (/platform/*)
Route::middleware(['auth', 'role:platform'])->prefix('platform')->name('platform.')->group(function () {
    // 제약사(테넌트) 관리 — 전체 CRUD
    Route::resource('tenants', PlatformTenantController::class);
    Route::post('/tenants/{tenant}/admins', [PlatformTenantController::class, 'storeAdmin'])->name('tenants.admins.store');

    // 임퍼서네이션 — 특정 제약사로 진입/종료 (GAP-10)
    Route::post('/tenants/{tenant}/enter', [PlatformTenantController::class, 'enter'])->name('tenants.enter');
    Route::post('/exit', [PlatformTenantController::class, 'exit'])->name('exit');

    // 공유 마스터(약국·병의원) CSV 일괄 등록 — resource 보다 먼저 (/{pharmacy} 충돌 방지)
    Route::get('/pharmacies/import', [PlatformPharmacyImportController::class, 'form'])->name('pharmacies.import.form');
    Route::post('/pharmacies/import', [PlatformPharmacyImportController::class, 'handle'])->name('pharmacies.import.handle');
    Route::get('/hospitals/import', [PlatformHospitalImportController::class, 'form'])->name('hospitals.import.form');
    Route::post('/hospitals/import', [PlatformHospitalImportController::class, 'handle'])->name('hospitals.import.handle');

    // 공유 마스터(약국·병의원) 전역 CRUD — super_admin 이 직접 관리 (D-6)
    Route::resource('pharmacies', PlatformPharmacyController::class)
        ->parameters(['pharmacies' => 'pharmacy']);
    Route::resource('hospitals', PlatformHospitalController::class);

    // 전역 조회 (의약품·사용자) — CRUD 는 후속 단계
    Route::get('/products', [PlatformProductController::class, 'index'])->name('products.index');
    Route::get('/users', [PlatformUserController::class, 'index'])->name('users.index');
});

Route::middleware(['auth', 'role:pharma'])->group(function () {
    // GAP-9: 기준정보 마스터 허브 (병의원·약국·의약품)
    Route::get('/master-data', [MasterDataController::class, 'index'])->name('master-data.index');

    Route::resource('sales-quotas', SalesQuotaController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('users', UserController::class);
    Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
});

require __DIR__.'/auth.php';
