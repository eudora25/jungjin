<?php

use App\Models\Performance;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * GAP-10 MT-3 — 테넌트 격리 엔진 (TenantScope + ResolveTenant + 자동 주입).
 */
function ctx(): TenantContext
{
    return app(TenantContext::class);
}

test('현재 테넌트가 설정되면 해당 제약사 데이터만 조회된다', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    Product::factory()->count(2)->create(['tenant_id' => $a->id]);
    Product::factory()->count(3)->create(['tenant_id' => $b->id]);

    ctx()->set($a->id);
    expect(Product::count())->toBe(2);

    ctx()->set($b->id);
    expect(Product::count())->toBe(3);
});

test('컨텍스트 미설정(super_admin/콘솔)이면 전역 조회된다', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    Product::factory()->count(2)->create(['tenant_id' => $a->id]);
    Product::factory()->count(3)->create(['tenant_id' => $b->id]);

    ctx()->clear();
    expect(Product::count())->toBe(5);
});

test('생성 시 tenant_id 가 비어 있으면 현재 테넌트로 자동 주입된다', function () {
    $a = Tenant::factory()->create();

    ctx()->set($a->id);
    $product = Product::factory()->create(['tenant_id' => null]);

    expect($product->fresh()->tenant_id)->toBe($a->id);
});

test('실적번호 채번은 테넌트 스코프를 우회해 전역 기준으로 +1 된다 (교차 테넌트 중복 방지)', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    // B 테넌트에 20260501-0001 존재
    Performance::factory()->create([
        'tenant_id' => $b->id,
        'performance_no' => '20260501-0001',
        'performance_date' => '2026-05-01',
    ]);

    // A 컨텍스트에서도 같은 날짜 채번은 0002 (B 것을 전역으로 인식)
    ctx()->set($a->id);
    expect(Performance::nextNumberFor('2026-05-01'))->toBe('20260501-0002');
});

test('HTTP — admin 은 ResolveTenant 로 자기 제약사 제품만 본다', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $adminA = User::factory()->create(['role' => 'admin', 'tenant_id' => $a->id]);
    Product::factory()->count(2)->create(['tenant_id' => $a->id]);
    Product::factory()->count(3)->create(['tenant_id' => $b->id]);

    $this->actingAs($adminA)
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products.data', 2));
});

test('HTTP — super_admin 은 전 제약사 제품을 전역으로 본다', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $super = User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]);
    Product::factory()->count(2)->create(['tenant_id' => $a->id]);
    Product::factory()->count(3)->create(['tenant_id' => $b->id]);

    $this->actingAs($super)
        ->get(route('platform.products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products.data', 5));
});
