<?php

use App\Models\Company;
use App\Models\Performance;
use App\Models\Product;
use App\Models\Settlement;
use App\Models\User;
use App\Services\Settlement\SettlementBuilder;

test('승인된 실적만 정산 라인으로 집계된다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $company = Company::factory()->create();
    $product = Product::factory()->create(['price' => 5000]);

    Performance::factory()->approved()->forCompany($company)->forProduct($product)->create([
        'performance_date' => '2026-04-10',
        'quantity' => 2,
        'unit_price' => 5000,
        'commission_rate' => 10,
        'price_source' => Performance::PRICE_SOURCE_PRODUCTS_PRICE,
        'commission_source' => Performance::COMMISSION_SOURCE_MATRIX,
    ]);

    Performance::factory()->forCompany($company)->forProduct($product)->create([
        'performance_date' => '2026-04-11',
        'quantity' => 1,
        'unit_price' => 5000,
        'commission_rate' => 10,
        'status' => Performance::STATUS_DRAFT,
    ]);

    $builder = app(SettlementBuilder::class);
    $settlement = $builder->createOrRebuild($company, '2026-04', $admin);

    expect($settlement->line_count)->toBe(1)
        ->and((float) $settlement->total_subtotal)->toBe(10000.0)
        ->and((float) $settlement->total_commission)->toBe(1000.0);

    $line = $settlement->lines->first();
    expect($line)->not->toBeNull()
        ->and((int) $line->quantity)->toBe(2)
        ->and((float) $line->subtotal)->toBe(10000.0);
});

test('draft 가 아닌 정산은 재계산할 수 없다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $company = Company::factory()->create();

    Settlement::factory()->create([
        'company_id' => $company->id,
        'period_month' => '2026-05',
        'status' => Settlement::STATUS_CONFIRMED,
    ]);

    $builder = app(SettlementBuilder::class);

    $builder->createOrRebuild($company, '2026-05', $admin);
})->throws(\RuntimeException::class);

test('관리자만 정산 생성 API 를 호출할 수 있다', function () {
    $sales = User::factory()->create(['role' => 'sales']);
    $company = Company::factory()->create();

    $this->actingAs($sales)
        ->post(route('settlements.store'), [
            'company_id' => $company->id,
            'period_month' => '2026-04',
        ])
        ->assertForbidden();
});
