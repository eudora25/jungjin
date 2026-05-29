<?php

use App\Models\Company;
use App\Models\Performance;
use App\Models\Product;
use App\Models\User;

test('로그인 사용자는 실적 목록·등록 폼에 접근할 수 있다', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('performance.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('performance.create'))
        ->assertOk();
});

test('실적을 등록하면 draft 로 저장되고 스냅샷이 채워진다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $company = Company::factory()->create(['default_commission_grade' => 'a']);
    $product = Product::factory()->create(['price' => 8000]);

    $this->actingAs($sales)
        ->post(route('performance.store'), [
            'performance_date' => '2026-04-10',
            'company_id' => $company->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'note' => '테스트',
        ])
        ->assertRedirect();

    $perf = Performance::query()->first();
    expect($perf)->not->toBeNull()
        ->and($perf->status)->toBe(Performance::STATUS_DRAFT)
        ->and($perf->created_by)->toBe($sales->id)
        ->and((float) $perf->unit_price)->toBe(8000.0)
        ->and($perf->quantity)->toBe(3);
});

test('resolve-preview 는 JSON 으로 미리보기를 반환한다', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create(['default_commission_grade' => 'a']);
    $product = Product::factory()->create(['price' => 12000]);

    $this->actingAs($user)
        ->postJson(route('performance.resolve-preview'), [
            'company_id' => $company->id,
            'product_id' => $product->id,
            'performance_date' => '2026-04-10',
            'quantity' => 2,
        ])
        ->assertOk()
        ->assertJsonPath('preview.subtotal', 24000);
});

test('실적 제출 → 검수 → 승인 워크플로가 동작한다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $admin = User::factory()->create(['role' => 'pharma']);
    $company = Company::factory()->create(['default_commission_grade' => 'a']);
    $product = Product::factory()->create(['price' => 1000]);

    $perf = Performance::factory()
        ->forCompany($company)
        ->forProduct($product)
        ->create([
            'performance_date' => '2026-04-01',
            'quantity' => 1,
            'unit_price' => 1000,
            'commission_rate' => 10,
            'price_source' => Performance::PRICE_SOURCE_PRODUCTS_PRICE,
            'commission_source' => Performance::COMMISSION_SOURCE_MATRIX,
            'status' => Performance::STATUS_DRAFT,
            'created_by' => $sales->id,
            'updated_by' => $sales->id,
        ]);

    $this->actingAs($sales)
        ->post(route('performance.submit', $perf))
        ->assertRedirect();

    $perf->refresh();
    expect($perf->status)->toBe(Performance::STATUS_SUBMITTED)
        ->and($perf->submitted_by)->toBe($sales->id);

    $this->actingAs($admin)
        ->post(route('performance.review', $perf))
        ->assertRedirect();

    $perf->refresh();
    expect($perf->status)->toBe(Performance::STATUS_REVIEWED)
        ->and($perf->reviewed_by)->toBe($admin->id);

    $this->actingAs($admin)
        ->post(route('performance.approve', $perf))
        ->assertRedirect();

    $perf->refresh();
    expect($perf->status)->toBe(Performance::STATUS_APPROVED)
        ->and($perf->approved_by)->toBe($admin->id);
});

test('영업사원은 검수·승인을 할 수 없다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $perf = Performance::factory()->submitted()->create();

    $this->actingAs($sales)
        ->post(route('performance.review', $perf))
        ->assertForbidden();

    $this->actingAs($sales)
        ->post(route('performance.approve', $perf))
        ->assertForbidden();
});

test('반려 시 사유가 저장되고 파이프라인 타임스탬프가 초기화된다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $perf = Performance::factory()->submitted()->create([
        'submitted_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('performance.reject', $perf), [
            'reason' => '금액 불일치',
        ])
        ->assertRedirect();

    $perf->refresh();
    expect($perf->status)->toBe(Performance::STATUS_REJECTED)
        ->and($perf->rejected_reason)->toBe('금액 불일치')
        ->and($perf->submitted_at)->toBeNull()
        ->and($perf->submitted_by)->toBeNull();
});

test('반려 사유 없이 반려하면 검증 실패한다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $perf = Performance::factory()->submitted()->create();

    $this->actingAs($admin)
        ->post(route('performance.reject', $perf), [])
        ->assertSessionHasErrors('reason');
});

test('미승인 거래처에 실적을 등록하면 검증 오류가 반환된다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $company = Company::factory()->create(['approval_status' => 'pending']);
    $product = Product::factory()->create(['price' => 1000]);

    $this->actingAs($sales)
        ->post(route('performance.store'), [
            'performance_date' => '2026-04-10',
            'company_id' => $company->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ])
        ->assertSessionHasErrors('company_id');

    expect(Performance::count())->toBe(0);
});

test('미승인(rejected) 거래처에도 실적 등록이 차단된다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $company = Company::factory()->create(['approval_status' => 'rejected']);
    $product = Product::factory()->create(['price' => 1000]);

    $this->actingAs($sales)
        ->post(route('performance.store'), [
            'performance_date' => '2026-04-10',
            'company_id' => $company->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ])
        ->assertSessionHasErrors('company_id');
});

test('목록에서 거래처·제품 필터 쿼리가 적용된다', function () {
    $user = User::factory()->create();
    $c1 = Company::factory()->create();
    $c2 = Company::factory()->create();
    $p1 = Product::factory()->create();
    $p2 = Product::factory()->create();

    Performance::factory()->forCompany($c1)->forProduct($p1)->create();
    Performance::factory()->forCompany($c2)->forProduct($p2)->create();

    $this->actingAs($user)
        ->get(route('performance.index', ['company_id' => $c1->id]))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('performance.index', ['product_id' => $p2->id]))
        ->assertOk();
});
