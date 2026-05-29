<?php

use App\Models\Company;
use App\Models\CompanyProductOverride;
use App\Models\Performance;
use App\Models\Product;
use App\Models\ProductCommissionRate;
use App\Models\ProductPrice;
use App\Models\Settlement;
use App\Models\SettlementLine;
use App\Models\User;

/**
 * Phase 4 E2E: 마스터(예외 단가·예외 수수료·매출가·매트릭스) → 실적 스냅샷 → 정산 라인 복사 일관성.
 */
test('실적 등록(HTTP) → 승인 → 정산 재계산 시 라인이 실적 스냅샷과 동일하다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $sales = User::factory()->create(['role' => 'cso']);

    $company = Company::factory()->create(['default_commission_grade' => 'b']);
    $product = Product::factory()->create(['price' => 10000]);

    ProductPrice::create([
        'product_id' => $product->id,
        'price_type' => ProductPrice::TYPE_SALE,
        'amount' => 9500,
        'effective_from' => '2026-01-01',
    ]);

    ProductCommissionRate::create([
        'product_id' => $product->id,
        'base_month' => '2026-04',
        'commission_rate_a' => 10,
        'commission_rate_b' => 12.5,
        'commission_rate_c' => 15,
        'commission_rate_d' => 18,
        'commission_rate_e' => 20,
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    $override = CompanyProductOverride::factory()->create([
        'company_id' => $company->id,
        'product_id' => $product->id,
        'override_unit_price' => 8800,
        'override_commission_rate' => 7.5,
        'effective_from' => '2026-04-01',
    ]);

    $this->actingAs($sales)
        ->post(route('performance.store'), [
            'performance_date' => '2026-04-15',
            'company_id' => $company->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'note' => 'E2E',
        ])
        ->assertRedirect();

    $perf = Performance::query()->first();
    expect($perf)->not->toBeNull()
        ->and((float) $perf->unit_price)->toBe(8800.0)
        ->and((float) $perf->commission_rate)->toBe(7.5)
        ->and($perf->price_source)->toBe(Performance::PRICE_SOURCE_OVERRIDE)
        ->and($perf->commission_source)->toBe(Performance::COMMISSION_SOURCE_OVERRIDE)
        ->and($perf->price_override_id)->toBe($override->id)
        ->and($perf->status)->toBe(Performance::STATUS_DRAFT);

    $this->actingAs($sales)
        ->post(route('performance.submit', $perf))
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('performance.review', $perf))
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('performance.approve', $perf))
        ->assertRedirect();

    $perf->refresh();
    expect($perf->status)->toBe(Performance::STATUS_APPROVED);

    $this->actingAs($admin)
        ->post(route('settlements.store'), [
            'company_id' => $company->id,
            'period_month' => '2026-04',
        ])
        ->assertRedirect();

    $settlement = Settlement::query()
        ->where('company_id', $company->id)
        ->where('period_month', '2026-04')
        ->first();

    expect($settlement)->not->toBeNull()
        ->and($settlement->line_count)->toBe(1);

    $line = SettlementLine::query()->where('settlement_id', $settlement->id)->first();
    expect($line)->not->toBeNull()
        ->and($line->performance_id)->toBe($perf->id)
        ->and((float) $line->snapshot_unit_price)->toBe((float) $perf->unit_price)
        ->and((float) $line->snapshot_commission_rate)->toBe((float) $perf->commission_rate)
        ->and((int) $line->quantity)->toBe((int) $perf->quantity)
        ->and((float) $line->subtotal)->toBe((float) $perf->subtotal)
        ->and((float) $line->commission_amount)->toBe((float) $perf->commission_amount);

    expect((float) $perf->subtotal)->toBe(35200.0)
        ->and((float) $perf->commission_amount)->toBe(2640.0)
        ->and((float) $settlement->total_subtotal)->toBe(35200.0)
        ->and((float) $settlement->total_commission)->toBe(2640.0)
        ->and((int) $settlement->total_quantity)->toBe(4);
});

test('같은 월에 승인 실적이 두 건이면 정산 합계가 실적 합과 같다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $company = Company::factory()->create(['default_commission_grade' => 'a']);
    $product = Product::factory()->create(['price' => 2000]);

    $p1 = Performance::factory()->approved()->forCompany($company)->forProduct($product)->create([
        'performance_date' => '2026-05-01',
        'quantity' => 3,
        'unit_price' => 2000,
        'commission_rate' => 10,
        'price_source' => Performance::PRICE_SOURCE_PRODUCTS_PRICE,
        'commission_source' => Performance::COMMISSION_SOURCE_MATRIX,
    ]);
    $p2 = Performance::factory()->approved()->forCompany($company)->forProduct($product)->create([
        'performance_date' => '2026-05-20',
        'quantity' => 1,
        'unit_price' => 2000,
        'commission_rate' => 10,
        'price_source' => Performance::PRICE_SOURCE_PRODUCTS_PRICE,
        'commission_source' => Performance::COMMISSION_SOURCE_MATRIX,
    ]);

    $this->actingAs($admin)
        ->post(route('settlements.store'), [
            'company_id' => $company->id,
            'period_month' => '2026-05',
        ])
        ->assertRedirect();

    $settlement = Settlement::query()
        ->where('company_id', $company->id)
        ->where('period_month', '2026-05')
        ->with('lines')
        ->first();

    $sumSub = (float) $p1->fresh()->subtotal + (float) $p2->fresh()->subtotal;
    $sumComm = (float) ($p1->fresh()->commission_amount ?? 0) + (float) ($p2->fresh()->commission_amount ?? 0);

    expect($settlement->line_count)->toBe(2)
        ->and((float) $settlement->total_subtotal)->toBe($sumSub)
        ->and((float) $settlement->total_commission)->toBe($sumComm);

    $ids = $settlement->lines->pluck('performance_id')->sort()->values()->all();
    expect($ids)->toBe([$p1->id, $p2->id]);
});
