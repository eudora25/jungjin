<?php

use App\Models\Company;
use App\Models\Performance;
use App\Models\Product;
use App\Models\SalesQuota;
use App\Models\User;
use App\Services\QuotaAchievementService;

// ── 서비스 유닛 테스트 ────────────────────────────────────────────────────────

test('월별 달성액을 올바르게 계산한다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $company = Company::factory()->create(['default_commission_grade' => 'a']);
    $product = Product::factory()->create(['price' => 1000]);

    Performance::factory()->create([
        'performance_date' => '2026-04-10',
        'quantity' => 10,
        'unit_price' => 1000,
        'status' => Performance::STATUS_APPROVED,
        'created_by' => $sales->id,
        'updated_by' => $sales->id,
        'company_id' => $company->id,
        'product_id' => $product->id,
    ]);

    // 다른 달 실적 (집계 제외)
    Performance::factory()->create([
        'performance_date' => '2026-03-15',
        'quantity' => 5,
        'unit_price' => 1000,
        'status' => Performance::STATUS_APPROVED,
        'created_by' => $sales->id,
        'updated_by' => $sales->id,
        'company_id' => $company->id,
        'product_id' => $product->id,
    ]);

    $service = app(QuotaAchievementService::class);
    $result = $service->calculate($sales->id, 'monthly', '2026-04');

    expect($result['achieved_amount'])->toBe(10000.0);
});

test('승인되지 않은 실적은 달성액에 포함되지 않는다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $company = Company::factory()->create(['default_commission_grade' => 'a']);
    $product = Product::factory()->create(['price' => 1000]);

    foreach ([Performance::STATUS_DRAFT, Performance::STATUS_SUBMITTED, Performance::STATUS_REJECTED] as $status) {
        Performance::factory()->create([
            'performance_date' => '2026-04-10',
            'quantity' => 10,
            'unit_price' => 1000,
            'status' => $status,
            'created_by' => $sales->id,
            'updated_by' => $sales->id,
            'company_id' => $company->id,
            'product_id' => $product->id,
        ]);
    }

    $service = app(QuotaAchievementService::class);
    $result = $service->calculate($sales->id, 'monthly', '2026-04');

    expect($result['achieved_amount'])->toBe(0.0);
});

test('분기별 달성액을 올바르게 계산한다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $company = Company::factory()->create(['default_commission_grade' => 'a']);
    $product = Product::factory()->create(['price' => 1000]);

    // Q2: 4월 ~ 6월
    foreach (['2026-04-10', '2026-05-20', '2026-06-30'] as $date) {
        Performance::factory()->create([
            'performance_date' => $date,
            'quantity' => 10,
            'unit_price' => 1000,
            'status' => Performance::STATUS_APPROVED,
            'created_by' => $sales->id,
            'updated_by' => $sales->id,
            'company_id' => $company->id,
            'product_id' => $product->id,
        ]);
    }

    // Q1 실적 (제외)
    Performance::factory()->create([
        'performance_date' => '2026-03-15',
        'quantity' => 100,
        'unit_price' => 1000,
        'status' => Performance::STATUS_APPROVED,
        'created_by' => $sales->id,
        'updated_by' => $sales->id,
        'company_id' => $company->id,
        'product_id' => $product->id,
    ]);

    $service = app(QuotaAchievementService::class);
    $result = $service->calculate($sales->id, 'quarterly', '2026-Q2');

    expect($result['achieved_amount'])->toBe(30000.0);
});

test('연간 달성액을 올바르게 계산한다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $company = Company::factory()->create(['default_commission_grade' => 'a']);
    $product = Product::factory()->create(['price' => 1000]);

    foreach (['2026-01-10', '2026-06-15', '2026-12-31'] as $date) {
        Performance::factory()->create([
            'performance_date' => $date,
            'quantity' => 10,
            'unit_price' => 1000,
            'status' => Performance::STATUS_APPROVED,
            'created_by' => $sales->id,
            'updated_by' => $sales->id,
            'company_id' => $company->id,
            'product_id' => $product->id,
        ]);
    }

    // 다른 연도 (제외)
    Performance::factory()->create([
        'performance_date' => '2025-12-31',
        'quantity' => 999,
        'unit_price' => 1000,
        'status' => Performance::STATUS_APPROVED,
        'created_by' => $sales->id,
        'updated_by' => $sales->id,
        'company_id' => $company->id,
        'product_id' => $product->id,
    ]);

    $service = app(QuotaAchievementService::class);
    $result = $service->calculate($sales->id, 'yearly', '2026');

    expect($result['achieved_amount'])->toBe(30000.0);
});

test('제품별 목표는 해당 제품 실적만 집계한다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $company = Company::factory()->create(['default_commission_grade' => 'a']);
    $product1 = Product::factory()->create(['price' => 1000]);
    $product2 = Product::factory()->create(['price' => 1000]);

    Performance::factory()->create([
        'performance_date' => '2026-04-10',
        'quantity' => 10,
        'unit_price' => 1000,
        'status' => Performance::STATUS_APPROVED,
        'created_by' => $sales->id,
        'updated_by' => $sales->id,
        'company_id' => $company->id,
        'product_id' => $product1->id,
    ]);

    Performance::factory()->create([
        'performance_date' => '2026-04-15',
        'quantity' => 5,
        'unit_price' => 1000,
        'status' => Performance::STATUS_APPROVED,
        'created_by' => $sales->id,
        'updated_by' => $sales->id,
        'company_id' => $company->id,
        'product_id' => $product2->id,
    ]);

    $service = app(QuotaAchievementService::class);
    $result = $service->calculate($sales->id, 'monthly', '2026-04', $product1->id);

    expect($result['achieved_amount'])->toBe(10000.0);
});

// ── HTTP 응답에 달성 정보가 포함되는지 확인 ───────────────────────────────────

test('목표 목록 페이지에 달성액과 달성률이 포함된다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $sales = User::factory()->create(['role' => 'cso']);

    SalesQuota::factory()->create([
        'user_id' => $sales->id,
        'period_type' => 'monthly',
        'period' => '2026-04',
        'target_amount' => 5000000,
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('sales-quotas.index'))
        ->assertOk();

    $data = $response->original->getData()['page']['props']['quotas']['data'][0] ?? null;
    expect($data)->not->toBeNull()
        ->and(array_key_exists('achieved_amount', $data))->toBeTrue()
        ->and(array_key_exists('achievement_rate', $data))->toBeTrue();
});
