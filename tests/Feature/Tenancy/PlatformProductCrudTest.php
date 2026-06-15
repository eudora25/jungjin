<?php

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;

/**
 * GAP-10 후속-A (§6.8) — platform 의 의약품(제품) 전역 CRUD.
 * platform 은 모든 제약사 제품을 횡단 등록·수정·삭제하며, 생성 시 대상 제약사(tenant_id)를 명시 선택한다.
 */
function ppPlatformUser(): User
{
    return User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
}

// ---- 생성 ----

test('platform 은 대상 제약사를 지정해 제품을 등록한다', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs(ppPlatformUser())
        ->post(route('platform.products.store'), [
            'tenant_id' => $tenant->id,
            'insurance_code' => '650000010',
            'product_code' => 'PRD-0001',
            'product_name' => '플랫폼제품',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('products', [
        'product_name' => '플랫폼제품',
        'tenant_id' => $tenant->id,
        'approval_status' => Product::APPROVAL_APPROVED,
    ]);
});

test('제품 등록 시 tenant_id(대상 제약사)는 필수이며 실재해야 한다', function () {
    $platform = ppPlatformUser();

    $this->actingAs($platform)
        ->post(route('platform.products.store'), [
            'insurance_code' => '650000011',
            'product_code' => 'PRD-0002',
            'product_name' => '제약사없음',
        ])
        ->assertSessionHasErrors('tenant_id');

    $this->actingAs($platform)
        ->post(route('platform.products.store'), [
            'tenant_id' => 99999,
            'insurance_code' => '650000012',
            'product_code' => 'PRD-0003',
            'product_name' => '없는제약사',
        ])
        ->assertSessionHasErrors('tenant_id');
});

// ---- 수정/삭제 (교차 테넌트 포함) ----

test('platform 은 다른 제약사의 제품도 수정한다 (전역 접근)', function () {
    $tenantB = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenantB->id, 'product_name' => '원래제품']);

    $this->actingAs(ppPlatformUser())
        ->put(route('platform.products.update', $product), [
            'insurance_code' => $product->insurance_code,
            'product_code' => $product->product_code,
            'product_name' => '수정제품',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('products', ['id' => $product->id, 'product_name' => '수정제품']);
});

test('platform 은 제품을 소프트 삭제한다', function () {
    $product = Product::factory()->create();

    $this->actingAs(ppPlatformUser())
        ->delete(route('platform.products.destroy', $product))
        ->assertRedirect(route('platform.products.index'));

    $this->assertSoftDeleted('products', ['id' => $product->id]);
});

// ---- 임퍼서네이션 중 전역 조회 ----

test('제약사 진입 중에도 플랫폼 의약품 목록·상세는 전역이다', function () {
    $platform = ppPlatformUser();
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    Product::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
    Product::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

    $this->actingAs($platform)->post(route('platform.tenants.enter', $tenantA));

    $this->actingAs($platform)
        ->get(route('platform.products.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('products.data', 5));

    $productB = Product::factory()->create(['tenant_id' => $tenantB->id, 'product_name' => 'B사전용']);

    $this->actingAs($platform)
        ->get(route('platform.products.show', $productB))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('product.product_name', 'B사전용'));
});

// ---- 화면 렌더 ----

test('platform 은 등록/수정 화면에 접근한다', function () {
    $platform = ppPlatformUser();
    $product = Product::factory()->create();

    $this->actingAs($platform)->get(route('platform.products.create'))->assertOk();
    $this->actingAs($platform)->get(route('platform.products.edit', $product))->assertOk();
    $this->actingAs($platform)->get(route('platform.products.show', $product))->assertOk();
});

// ---- 권한 차단 ----

test('pharma 는 플랫폼 제품 CRUD 에 접근할 수 없다', function () {
    $pharma = User::factory()->create(['role' => 'pharma']);
    $product = Product::factory()->create();

    $this->actingAs($pharma)->get(route('platform.products.create'))->assertForbidden();
    $this->actingAs($pharma)->post(route('platform.products.store'), [
        'tenant_id' => $product->tenant_id,
        'insurance_code' => '650000099',
        'product_code' => 'PRD-9999',
        'product_name' => 'X',
    ])->assertForbidden();
    $this->actingAs($pharma)->put(route('platform.products.update', $product), [
        'insurance_code' => $product->insurance_code,
        'product_code' => $product->product_code,
        'product_name' => 'X',
    ])->assertForbidden();
    $this->actingAs($pharma)->delete(route('platform.products.destroy', $product))->assertForbidden();
});

test('cso 와 비로그인 은 플랫폼 제품 화면에 접근할 수 없다', function () {
    $cso = User::factory()->create(['role' => 'cso']);

    // 비로그인·cso 모두 platform 그룹(role:platform)에서 차단(403) — 기존 platform 라우트 공통 동작
    $this->actingAs($cso)->get(route('platform.products.create'))->assertForbidden();
    $this->get(route('platform.products.create'))->assertForbidden();
});
