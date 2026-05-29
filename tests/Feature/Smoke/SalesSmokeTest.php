<?php

use App\Models\Company;
use App\Models\Performance;
use App\Models\Product;
use App\Models\Settlement;
use App\Models\SettlementLine;
use App\Models\User;

test('sales 는 대시보드에 접근할 수 있다', function () {
    $sales = User::factory()->create(['role' => 'cso']);

    $this->actingAs($sales)
        ->get(route('dashboard'))
        ->assertOk();
});

test('sales 는 기준정보(제품/업체/약국/병원) 목록/상세를 조회할 수 있다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $product = Product::factory()->create();
    $company = Company::factory()->create();

    $this->actingAs($sales)->get(route('products.index'))->assertOk();
    $this->actingAs($sales)->get(route('products.show', $product))->assertOk();

    $this->actingAs($sales)->get(route('companies.index'))->assertOk();
    $this->actingAs($sales)->get(route('companies.show', $company))->assertOk();

    $this->actingAs($sales)->get(route('pharmacies.index'))->assertOk();
    $this->actingAs($sales)->get(route('hospitals.index'))->assertOk();
});

test('sales 는 본인 실적만 조회/등록할 수 있고 review/approve 는 할 수 없다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $admin = User::factory()->create(['role' => 'pharma']);

    $myPerf = Performance::factory()->submitted()->create([
        'created_by' => $sales->id,
        'updated_by' => $sales->id,
    ]);
    $otherPerf = Performance::factory()->submitted()->create([
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);

    $this->actingAs($sales)->get(route('performance.index'))->assertOk();
    $this->actingAs($sales)->get(route('performance.create'))->assertOk();

    // 본인 실적 → 조회 가능
    $this->actingAs($sales)->get(route('performance.show', $myPerf))->assertOk();

    // 타인 실적 → 403
    $this->actingAs($sales)->get(route('performance.show', $otherPerf))->assertForbidden();

    $this->actingAs($sales)->post(route('performance.review', $myPerf))->assertForbidden();
    $this->actingAs($sales)->post(route('performance.approve', $myPerf))->assertForbidden();

    // 관리자라면 review 는 가능 (권한 가드가 실제로 동작하는지 sanity check)
    $this->actingAs($admin)->post(route('performance.review', $myPerf))->assertRedirect();
});

test('sales 는 본인 실적이 포함된 정산만 조회할 수 있다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $admin = User::factory()->create(['role' => 'pharma']);

    // sales 실적이 포함된 정산
    $myPerf = Performance::factory()->approved()->create([
        'created_by' => $sales->id,
        'updated_by' => $sales->id,
    ]);
    $myPerf->refresh(); // virtual stored columns (subtotal, commission_amount) 로드
    $mySettlement = Settlement::factory()->create([
        'company_id' => $myPerf->company_id,
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);
    SettlementLine::create([
        'settlement_id' => $mySettlement->id,
        'performance_id' => $myPerf->id,
        'snapshot_unit_price' => $myPerf->unit_price,
        'snapshot_commission_rate' => $myPerf->commission_rate,
        'quantity' => $myPerf->quantity,
        'subtotal' => $myPerf->subtotal ?? 0,
        'commission_amount' => $myPerf->commission_amount,
    ]);

    // 타인 실적으로만 구성된 정산
    $otherSettlement = Settlement::factory()->create([
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);

    // 목록은 항상 200 (필터 적용)
    $this->actingAs($sales)->get(route('settlements.index'))->assertOk();

    // 본인 실적 포함 → 200
    $this->actingAs($sales)->get(route('settlements.show', $mySettlement))->assertOk();

    // 타인 실적만 → 403
    $this->actingAs($sales)->get(route('settlements.show', $otherSettlement))->assertForbidden();
});

test('sales 는 정산 생성/상태전이는 할 수 없다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $admin = User::factory()->create(['role' => 'pharma']);
    $settlement = Settlement::factory()->create([
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);

    $this->actingAs($sales)->post(route('settlements.store'), [
        'company_id' => $settlement->company_id,
        'period_month' => $settlement->period_month,
    ])->assertForbidden();

    $this->actingAs($sales)->post(route('settlements.recalculate', $settlement))->assertForbidden();
    $this->actingAs($sales)->post(route('settlements.confirm', $settlement))->assertForbidden();
    $this->actingAs($sales)->post(route('settlements.pay', $settlement))->assertForbidden();
    $this->actingAs($sales)->post(route('settlements.cancel', $settlement))->assertForbidden();
});

test('sales 는 본인 실적 포함 정산만 내보내기할 수 있다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $admin = User::factory()->create(['role' => 'pharma']);

    // 본인 실적이 포함된 정산
    $myPerf = Performance::factory()->approved()->create([
        'created_by' => $sales->id,
        'updated_by' => $sales->id,
    ]);
    $myPerf->refresh();
    $mySettlement = Settlement::factory()->create([
        'company_id' => $myPerf->company_id,
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);
    SettlementLine::create([
        'settlement_id' => $mySettlement->id,
        'performance_id' => $myPerf->id,
        'snapshot_unit_price' => $myPerf->unit_price,
        'snapshot_commission_rate' => $myPerf->commission_rate,
        'quantity' => $myPerf->quantity,
        'subtotal' => $myPerf->subtotal ?? 0,
        'commission_amount' => $myPerf->commission_amount,
    ]);

    // 타인 실적만 있는 정산
    $otherSettlement = Settlement::factory()->create([
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);

    // 본인 실적 포함 → 내보내기 허용
    $this->actingAs($sales)->get(route('settlements.export.excel', $mySettlement))->assertOk();
    $this->actingAs($sales)->get(route('settlements.export.pdf', $mySettlement))->assertOk();

    // 타인 실적만 → 403
    $this->actingAs($sales)->get(route('settlements.export.excel', $otherSettlement))->assertForbidden();
    $this->actingAs($sales)->get(route('settlements.export.pdf', $otherSettlement))->assertForbidden();
});

test('sales 는 사용자 관리에 접근할 수 없다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $admin = User::factory()->create(['role' => 'pharma']);

    $this->actingAs($sales)->get(route('users.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('users.index'))->assertOk();
});
