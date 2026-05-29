<?php

use App\Models\Company;
use App\Models\Performance;
use App\Models\Product;
use App\Models\User;

// ── 접근 권한 ──────────────────────────────────────────────────────────────────

test('비로그인 사용자는 명세서에 접근할 수 없다', function () {
    $sales = User::factory()->create(['role' => 'cso']);

    $this->get(route('commission-summary.statement', $sales))
        ->assertRedirect(route('login'));
});

test('영업사원은 본인 명세서를 조회할 수 있다', function () {
    $sales = User::factory()->create(['role' => 'cso']);

    $this->actingAs($sales)
        ->get(route('commission-summary.statement', $sales))
        ->assertOk();
});

test('영업사원은 타인의 명세서를 조회할 수 없다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $other = User::factory()->create(['role' => 'cso']);

    $this->actingAs($sales)
        ->get(route('commission-summary.statement', $other))
        ->assertForbidden();
});

test('관리자는 임의의 영업사원 명세서를 조회할 수 있다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $sales = User::factory()->create(['role' => 'cso']);

    $this->actingAs($admin)
        ->get(route('commission-summary.statement', $sales))
        ->assertOk();
});

// ── 집계 정확성 ────────────────────────────────────────────────────────────────

test('명세서는 본인의 승인 실적 합계를 정확히 노출한다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $other = User::factory()->create(['role' => 'cso']);
    $company = Company::factory()->create(['default_commission_grade' => 'a']);
    $product = Product::factory()->create(['price' => 1000]);

    // 본인 실적 2건 (수수료 1000 + 2000)
    Performance::factory()->approved()->forCompany($company)->forProduct($product)->create([
        'performance_date' => '2026-04-10',
        'quantity' => 10,
        'unit_price' => 1000,
        'commission_rate' => 10,
        'created_by' => $sales->id,
    ]);
    Performance::factory()->approved()->forCompany($company)->forProduct($product)->create([
        'performance_date' => '2026-04-20',
        'quantity' => 20,
        'unit_price' => 1000,
        'commission_rate' => 10,
        'created_by' => $sales->id,
    ]);

    // 타인 실적 — 본인 명세에는 포함 안 됨
    Performance::factory()->approved()->forCompany($company)->forProduct($product)->create([
        'performance_date' => '2026-04-15',
        'quantity' => 100,
        'unit_price' => 1000,
        'commission_rate' => 10,
        'created_by' => $other->id,
    ]);

    $response = $this->actingAs($sales)
        ->get(route('commission-summary.statement', [$sales, 'month' => '2026-04']))
        ->assertOk();

    $totals = $response->original->getData()['page']['props']['totals'];
    expect($totals['line_count'])->toBe(2)
        ->and((float) $totals['total_subtotal'])->toBe(30000.0)
        ->and((float) $totals['total_commission'])->toBe(3000.0);
});

// ── PDF 다운로드 ───────────────────────────────────────────────────────────────

test('본인은 명세서 PDF 를 다운로드할 수 있다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $company = Company::factory()->create(['default_commission_grade' => 'a']);
    $product = Product::factory()->create(['price' => 1000]);

    Performance::factory()->approved()->forCompany($company)->forProduct($product)->create([
        'performance_date' => '2026-04-10',
        'quantity' => 1,
        'unit_price' => 1000,
        'commission_rate' => 10,
        'created_by' => $sales->id,
    ]);

    $res = $this->actingAs($sales)
        ->get(route('commission-summary.statement.pdf', [$sales, 'month' => '2026-04']));

    $res->assertOk();
    $res->assertHeader('content-type', 'application/pdf');
    expect($res->streamedContent())->toStartWith('%PDF-');
});

test('타인의 명세서 PDF 는 다운로드할 수 없다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $other = User::factory()->create(['role' => 'cso']);

    $this->actingAs($sales)
        ->get(route('commission-summary.statement.pdf', $other))
        ->assertForbidden();
});

// ── Sales 대시보드 카드 ────────────────────────────────────────────────────────

test('Sales 대시보드 props 에 thisMonthCommission 이 포함된다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $company = Company::factory()->create(['default_commission_grade' => 'a']);
    $product = Product::factory()->create(['price' => 1000]);

    Performance::factory()->approved()->forCompany($company)->forProduct($product)->create([
        'performance_date' => now()->format('Y-m-15'),
        'quantity' => 10,
        'unit_price' => 1000,
        'commission_rate' => 10,
        'created_by' => $sales->id,
    ]);

    $response = $this->actingAs($sales)
        ->get(route('sales.dashboard'))
        ->assertOk();

    $props = $response->original->getData()['page']['props'];
    expect(array_key_exists('thisMonthCommission', $props))->toBeTrue()
        ->and((float) $props['thisMonthCommission'])->toBe(1000.0);
});
