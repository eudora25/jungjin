<?php

use App\Models\Company;
use App\Models\Performance;
use App\Models\Product;
use App\Models\Settlement;
use App\Models\User;

test('관리자는 정산 Excel 을 다운로드할 수 있다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $company = Company::factory()->create();
    $product = Product::factory()->create(['price' => 1000]);

    Performance::factory()->approved()->forCompany($company)->forProduct($product)->create([
        'performance_date' => '2026-04-10',
        'quantity' => 2,
        'unit_price' => 1000,
        'commission_rate' => 10,
        'price_source' => Performance::PRICE_SOURCE_PRODUCTS_PRICE,
        'commission_source' => Performance::COMMISSION_SOURCE_MATRIX,
    ]);

    $this->actingAs($admin)
        ->post(route('settlements.store'), [
            'company_id' => $company->id,
            'period_month' => '2026-04',
        ])
        ->assertRedirect();

    $settlement = Settlement::query()->where('company_id', $company->id)->where('period_month', '2026-04')->firstOrFail();

    $res = $this->actingAs($admin)->get(route('settlements.export.excel', $settlement));
    $res->assertOk();
    $res->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($res->streamedContent())->toStartWith('PK'); // xlsx(zip) signature
});

test('영업사원은 정산 Excel 을 다운로드할 수 없다', function () {
    $sales = User::factory()->create(['role' => 'sales']);
    $admin = User::factory()->create(['role' => 'admin']);
    $company = Company::factory()->create();

    $this->actingAs($admin)
        ->post(route('settlements.store'), [
            'company_id' => $company->id,
            'period_month' => '2026-04',
        ])
        ->assertRedirect();

    $settlement = Settlement::query()->where('company_id', $company->id)->where('period_month', '2026-04')->firstOrFail();

    $this->actingAs($sales)
        ->get(route('settlements.export.excel', $settlement))
        ->assertForbidden();
});

