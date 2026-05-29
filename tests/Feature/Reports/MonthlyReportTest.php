<?php

use App\Models\Company;
use App\Models\Performance;
use App\Models\Product;
use App\Models\User;

// ── 접근 권한 ──────────────────────────────────────────────────────────────────

test('비로그인 사용자는 월간 보고서에 접근할 수 없다', function () {
    $this->get(route('reports.monthly'))->assertRedirect(route('login'));
});

test('영업사원은 월간 보고서에 접근할 수 없다', function () {
    $sales = User::factory()->create(['role' => 'cso']);

    $this->actingAs($sales)
        ->get(route('reports.monthly'))
        ->assertForbidden();
});

test('관리자는 월간 보고서에 접근할 수 있다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);

    $this->actingAs($admin)
        ->get(route('reports.monthly'))
        ->assertOk();
});

test('영업사원은 월간 보고서 Excel 내보내기에 접근할 수 없다', function () {
    $sales = User::factory()->create(['role' => 'cso']);

    $this->actingAs($sales)
        ->get(route('reports.monthly.export.excel'))
        ->assertForbidden();
});

// ── 집계 정확성 ────────────────────────────────────────────────────────────────

/**
 * 공통 샘플 데이터 (2026-05):
 *  P1 company1/product1/salesA qty10 @1000 rate10% → 매출 10000, 수수료 1000
 *  P2 company1/product2/salesA qty5  @2000 rate10% → 매출 10000, 수수료 1000
 *  P3 company2/product1/salesB qty3  @1000 rate20% → 매출  3000, 수수료  600
 *  + 다른 달(2026-04) approved 1건, 같은 달 미승인(draft) 1건 → 모두 제외
 */
function seedMonthlyReportSample(): array
{
    $salesA = User::factory()->create(['role' => 'cso', 'name' => '영업A']);
    $salesB = User::factory()->create(['role' => 'cso', 'name' => '영업B']);
    $company1 = Company::factory()->create(['company_name' => '거래처1', 'partner_type' => 'company']);
    $company2 = Company::factory()->create(['company_name' => '거래처2', 'partner_type' => 'pharmacy']);
    $product1 = Product::factory()->create(['product_name' => '제품1', 'insurance_code' => 'INS-1']);
    $product2 = Product::factory()->create(['product_name' => '제품2', 'insurance_code' => 'INS-2']);

    Performance::factory()->approved()->forCompany($company1)->forProduct($product1)->create([
        'performance_date' => '2026-05-10', 'quantity' => 10, 'unit_price' => 1000, 'commission_rate' => 10, 'created_by' => $salesA->id,
    ]);
    Performance::factory()->approved()->forCompany($company1)->forProduct($product2)->create([
        'performance_date' => '2026-05-12', 'quantity' => 5, 'unit_price' => 2000, 'commission_rate' => 10, 'created_by' => $salesA->id,
    ]);
    Performance::factory()->approved()->forCompany($company2)->forProduct($product1)->create([
        'performance_date' => '2026-05-15', 'quantity' => 3, 'unit_price' => 1000, 'commission_rate' => 20, 'created_by' => $salesB->id,
    ]);

    // 다른 달 (제외)
    Performance::factory()->approved()->forCompany($company1)->forProduct($product1)->create([
        'performance_date' => '2026-04-10', 'quantity' => 100, 'unit_price' => 1000, 'commission_rate' => 10, 'created_by' => $salesA->id,
    ]);
    // 미승인 (제외)
    Performance::factory()->forCompany($company1)->forProduct($product1)->create([
        'performance_date' => '2026-05-20', 'quantity' => 50, 'unit_price' => 1000, 'commission_rate' => 10, 'status' => Performance::STATUS_DRAFT, 'created_by' => $salesA->id,
    ]);

    return compact('salesA', 'salesB', 'company1', 'company2', 'product1', 'product2');
}

test('거래처별 요약을 매출 내림차순으로 정확히 집계한다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $s = seedMonthlyReportSample();

    $props = $this->actingAs($admin)
        ->get(route('reports.monthly', ['month' => '2026-05']))
        ->assertOk()
        ->original->getData()['page']['props'];

    $byCompany = collect($props['byCompany']);
    expect($byCompany)->toHaveCount(2);

    // 정렬: 거래처1(매출 20000) → 거래처2(매출 3000)
    expect((int) $byCompany[0]['company_id'])->toBe($s['company1']->id)
        ->and((int) $byCompany[0]['line_count'])->toBe(2)
        ->and((int) $byCompany[0]['total_quantity'])->toBe(15)
        ->and((float) $byCompany[0]['total_subtotal'])->toBe(20000.0)
        ->and((float) $byCompany[0]['total_commission'])->toBe(2000.0)
        ->and((float) $byCompany[0]['avg_commission_rate'])->toBe(10.0);

    expect((int) $byCompany[1]['company_id'])->toBe($s['company2']->id)
        ->and((float) $byCompany[1]['total_subtotal'])->toBe(3000.0)
        ->and((float) $byCompany[1]['avg_commission_rate'])->toBe(20.0);
});

test('영업사원별 요약을 수수료 내림차순으로 정확히 집계한다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $s = seedMonthlyReportSample();

    $props = $this->actingAs($admin)
        ->get(route('reports.monthly', ['month' => '2026-05']))
        ->assertOk()
        ->original->getData()['page']['props'];

    $bySales = collect($props['bySales']);
    expect($bySales)->toHaveCount(2);

    expect((int) $bySales[0]['user_id'])->toBe($s['salesA']->id)
        ->and($bySales[0]['user_name'])->toBe('영업A')
        ->and((int) $bySales[0]['line_count'])->toBe(2)
        ->and((float) $bySales[0]['total_commission'])->toBe(2000.0);

    expect((int) $bySales[1]['user_id'])->toBe($s['salesB']->id)
        ->and((float) $bySales[1]['total_commission'])->toBe(600.0);
});

test('제품별 요약을 매출 내림차순으로 정확히 집계한다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $s = seedMonthlyReportSample();

    $props = $this->actingAs($admin)
        ->get(route('reports.monthly', ['month' => '2026-05']))
        ->assertOk()
        ->original->getData()['page']['props'];

    $byProduct = collect($props['byProduct']);
    expect($byProduct)->toHaveCount(2);

    // 제품1: 매출 10000+3000=13000 / 제품2: 10000
    expect((int) $byProduct[0]['product_id'])->toBe($s['product1']->id)
        ->and((int) $byProduct[0]['line_count'])->toBe(2)
        ->and((int) $byProduct[0]['total_quantity'])->toBe(13)
        ->and((float) $byProduct[0]['total_subtotal'])->toBe(13000.0)
        ->and((float) $byProduct[0]['total_commission'])->toBe(1600.0);

    expect((int) $byProduct[1]['product_id'])->toBe($s['product2']->id)
        ->and((float) $byProduct[1]['total_subtotal'])->toBe(10000.0);
});

test('전체 합계가 3종 리포트와 동일하게 계산된다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    seedMonthlyReportSample();

    $props = $this->actingAs($admin)
        ->get(route('reports.monthly', ['month' => '2026-05']))
        ->assertOk()
        ->original->getData()['page']['props'];

    $totals = $props['totals'];
    expect((int) $totals['line_count'])->toBe(3)
        ->and((int) $totals['total_quantity'])->toBe(18)
        ->and((float) $totals['total_subtotal'])->toBe(23000.0)
        ->and((float) $totals['total_commission'])->toBe(2600.0);
});

test('승인되지 않은 실적은 집계에서 제외된다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $sales = User::factory()->create(['role' => 'cso']);
    $company = Company::factory()->create();
    $product = Product::factory()->create();

    foreach ([Performance::STATUS_DRAFT, Performance::STATUS_SUBMITTED, Performance::STATUS_REJECTED] as $status) {
        Performance::factory()->forCompany($company)->forProduct($product)->create([
            'performance_date' => '2026-05-10', 'quantity' => 10, 'unit_price' => 1000, 'commission_rate' => 10,
            'status' => $status, 'created_by' => $sales->id,
        ]);
    }

    $props = $this->actingAs($admin)
        ->get(route('reports.monthly', ['month' => '2026-05']))
        ->assertOk()
        ->original->getData()['page']['props'];

    expect(collect($props['byCompany']))->toBeEmpty()
        ->and(collect($props['bySales']))->toBeEmpty()
        ->and(collect($props['byProduct']))->toBeEmpty()
        ->and((float) $props['totals']['total_commission'])->toBe(0.0);
});

// ── 기간 범위 필터 ─────────────────────────────────────────────────────────────

test('from/to 직접 입력 시 해당 범위의 실적만 집계한다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    seedMonthlyReportSample();

    // 2026-05-01 ~ 2026-05-11 → P1 만 (10일) 포함, P2(12일)·P3(15일) 제외
    $props = $this->actingAs($admin)
        ->get(route('reports.monthly', ['from' => '2026-05-01', 'to' => '2026-05-11']))
        ->assertOk()
        ->original->getData()['page']['props'];

    expect(collect($props['byCompany']))->toHaveCount(1)
        ->and((int) $props['totals']['line_count'])->toBe(1)
        ->and((float) $props['totals']['total_subtotal'])->toBe(10000.0);
});

// ── Excel 내보내기 ─────────────────────────────────────────────────────────────

test('관리자는 월간 보고서 Excel(3시트)을 다운로드할 수 있다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    seedMonthlyReportSample();

    $res = $this->actingAs($admin)
        ->get(route('reports.monthly.export.excel', ['month' => '2026-05']));

    $res->assertOk();
    $res->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($res->streamedContent())->toStartWith('PK'); // xlsx(zip) signature
});

test('Excel 내보내기 시 activity log 가 기록된다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    seedMonthlyReportSample();

    $this->actingAs($admin)
        ->get(route('reports.monthly.export.excel', ['month' => '2026-05']))
        ->assertOk()
        ->streamedContent();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'report',
        'description' => 'report.monthly.export',
    ]);
});
