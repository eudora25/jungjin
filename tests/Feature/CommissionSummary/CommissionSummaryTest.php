<?php

use App\Models\Company;
use App\Models\Performance;
use App\Models\Product;
use App\Models\User;

// ── 접근 권한 ──────────────────────────────────────────────────────────────────

test('비로그인 사용자는 수수료 명세 페이지에 접근할 수 없다', function () {
    $this->get(route('commission-summary.index'))->assertRedirect(route('login'));
});

test('영업사원은 수수료 합계 페이지에 접근할 수 없다', function () {
    $sales = User::factory()->create(['role' => 'sales']);

    $this->actingAs($sales)
        ->get(route('commission-summary.index'))
        ->assertForbidden();
});

test('관리자는 수수료 합계 페이지에 접근할 수 있다', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('commission-summary.index'))
        ->assertOk();
});

test('영업사원은 Excel 내보내기에 접근할 수 없다', function () {
    $sales = User::factory()->create(['role' => 'sales']);

    $this->actingAs($sales)
        ->get(route('commission-summary.export.excel'))
        ->assertForbidden();
});

// ── 집계 정확성 ────────────────────────────────────────────────────────────────

test('관리자 페이지는 영업사원별 수수료 합계를 정확히 노출한다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $salesA = User::factory()->create(['role' => 'sales', 'name' => '영업A']);
    $salesB = User::factory()->create(['role' => 'sales', 'name' => '영업B']);
    $company = Company::factory()->create(['default_commission_grade' => 'a']);
    $product = Product::factory()->create(['price' => 1000]);

    // 영업A: 2건 (수수료 1000 + 2000)
    Performance::factory()->approved()->forCompany($company)->forProduct($product)->create([
        'performance_date' => '2026-04-10',
        'quantity' => 10,
        'unit_price' => 1000,
        'commission_rate' => 10,
        'created_by' => $salesA->id,
    ]);
    Performance::factory()->approved()->forCompany($company)->forProduct($product)->create([
        'performance_date' => '2026-04-20',
        'quantity' => 20,
        'unit_price' => 1000,
        'commission_rate' => 10,
        'created_by' => $salesA->id,
    ]);

    // 영업B: 1건 (수수료 500)
    Performance::factory()->approved()->forCompany($company)->forProduct($product)->create([
        'performance_date' => '2026-04-15',
        'quantity' => 5,
        'unit_price' => 1000,
        'commission_rate' => 10,
        'created_by' => $salesB->id,
    ]);

    // 다른 달 — 집계 제외
    Performance::factory()->approved()->forCompany($company)->forProduct($product)->create([
        'performance_date' => '2026-03-15',
        'quantity' => 100,
        'unit_price' => 1000,
        'commission_rate' => 10,
        'created_by' => $salesA->id,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('commission-summary.index', ['month' => '2026-04']))
        ->assertOk();

    $rows = $response->original->getData()['page']['props']['rows'];
    expect(count($rows))->toBe(2);

    $rowA = collect($rows)->firstWhere('user_id', $salesA->id);
    $rowB = collect($rows)->firstWhere('user_id', $salesB->id);

    expect($rowA['line_count'])->toBe(2)
        ->and((float) $rowA['total_subtotal'])->toBe(30000.0)
        ->and((float) $rowA['total_commission'])->toBe(3000.0);

    expect($rowB['line_count'])->toBe(1)
        ->and((float) $rowB['total_subtotal'])->toBe(5000.0)
        ->and((float) $rowB['total_commission'])->toBe(500.0);
});

test('승인되지 않은 실적은 합계에 포함되지 않는다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sales = User::factory()->create(['role' => 'sales']);
    $company = Company::factory()->create(['default_commission_grade' => 'a']);
    $product = Product::factory()->create(['price' => 1000]);

    foreach ([Performance::STATUS_DRAFT, Performance::STATUS_SUBMITTED, Performance::STATUS_REJECTED] as $status) {
        Performance::factory()->forCompany($company)->forProduct($product)->create([
            'performance_date' => '2026-04-10',
            'quantity' => 10,
            'unit_price' => 1000,
            'commission_rate' => 10,
            'status' => $status,
            'created_by' => $sales->id,
        ]);
    }

    $response = $this->actingAs($admin)
        ->get(route('commission-summary.index', ['month' => '2026-04']))
        ->assertOk();

    expect($response->original->getData()['page']['props']['rows'])->toBe([])
        ->and($response->original->getData()['page']['props']['totals']['total_commission'])->toBe(0.0);
});

// ── Excel 내보내기 ─────────────────────────────────────────────────────────────

test('관리자는 수수료 합계 Excel 을 다운로드할 수 있다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sales = User::factory()->create(['role' => 'sales']);
    $company = Company::factory()->create(['default_commission_grade' => 'a']);
    $product = Product::factory()->create(['price' => 1000]);

    Performance::factory()->approved()->forCompany($company)->forProduct($product)->create([
        'performance_date' => '2026-04-10',
        'quantity' => 2,
        'unit_price' => 1000,
        'commission_rate' => 10,
        'created_by' => $sales->id,
    ]);

    $res = $this->actingAs($admin)
        ->get(route('commission-summary.export.excel', ['month' => '2026-04']));

    $res->assertOk();
    $res->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($res->streamedContent())->toStartWith('PK'); // xlsx(zip) signature
});

// ── 기간 범위 필터 ─────────────────────────────────────────────────────────────

test('from/to 직접 입력 시 해당 범위의 실적만 합산한다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sales = User::factory()->create(['role' => 'sales']);
    $company = Company::factory()->create(['default_commission_grade' => 'a']);
    $product = Product::factory()->create(['price' => 1000]);

    Performance::factory()->approved()->forCompany($company)->forProduct($product)->create([
        'performance_date' => '2026-04-05',
        'quantity' => 1,
        'unit_price' => 1000,
        'commission_rate' => 10,
        'created_by' => $sales->id,
    ]);

    Performance::factory()->approved()->forCompany($company)->forProduct($product)->create([
        'performance_date' => '2026-04-20',
        'quantity' => 5,
        'unit_price' => 1000,
        'commission_rate' => 10,
        'created_by' => $sales->id,
    ]);

    // 5~10 범위만
    $response = $this->actingAs($admin)
        ->get(route('commission-summary.index', ['from' => '2026-04-01', 'to' => '2026-04-10']))
        ->assertOk();

    $rows = $response->original->getData()['page']['props']['rows'];
    expect(count($rows))->toBe(1)
        ->and((int) $rows[0]['line_count'])->toBe(1);
});
