<?php

use App\Models\Product;
use App\Models\SalesQuota;
use App\Models\User;

// ── 인증/권한 ────────────────────────────────────────────────────────────────

test('비로그인 사용자는 목표 목록에 접근할 수 없다', function () {
    $this->get(route('sales-quotas.index'))->assertRedirect(route('login'));
});

test('영업사원은 목표 목록에 접근할 수 없다', function () {
    $sales = User::factory()->create(['role' => 'cso']);

    $this->actingAs($sales)
        ->get(route('sales-quotas.index'))
        ->assertForbidden();
});

test('관리자는 목표 목록에 접근할 수 있다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);

    $this->actingAs($admin)
        ->get(route('sales-quotas.index'))
        ->assertOk();
});

// ── 등록 ─────────────────────────────────────────────────────────────────────

test('관리자는 월별 목표를 등록할 수 있다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $sales = User::factory()->create(['role' => 'cso']);

    $this->actingAs($admin)
        ->post(route('sales-quotas.store'), [
            'user_id' => $sales->id,
            'product_id' => null,
            'period_type' => 'monthly',
            'period' => '2026-04',
            'target_amount' => 5000000,
        ])
        ->assertRedirect();

    expect(SalesQuota::count())->toBe(1)
        ->and(SalesQuota::first()->created_by)->toBe($admin->id);
});

test('관리자는 분기별 목표를 등록할 수 있다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $sales = User::factory()->create(['role' => 'cso']);

    $this->actingAs($admin)
        ->post(route('sales-quotas.store'), [
            'user_id' => $sales->id,
            'product_id' => null,
            'period_type' => 'quarterly',
            'period' => '2026-Q2',
            'target_amount' => 15000000,
        ])
        ->assertRedirect();

    expect(SalesQuota::count())->toBe(1);
});

test('관리자는 연간 목표를 등록할 수 있다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $sales = User::factory()->create(['role' => 'cso']);

    $this->actingAs($admin)
        ->post(route('sales-quotas.store'), [
            'user_id' => $sales->id,
            'product_id' => null,
            'period_type' => 'yearly',
            'period' => '2026',
            'target_amount' => 60000000,
        ])
        ->assertRedirect();

    expect(SalesQuota::count())->toBe(1);
});

test('제품별 목표를 별도로 등록할 수 있다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $sales = User::factory()->create(['role' => 'cso']);
    $product = Product::factory()->create();

    $this->actingAs($admin)
        ->post(route('sales-quotas.store'), [
            'user_id' => $sales->id,
            'product_id' => $product->id,
            'period_type' => 'monthly',
            'period' => '2026-04',
            'target_amount' => 2000000,
        ])
        ->assertRedirect();

    expect(SalesQuota::count())->toBe(1)
        ->and(SalesQuota::first()->product_id)->toBe($product->id);
});

test('영업사원은 목표를 등록할 수 없다', function () {
    $sales = User::factory()->create(['role' => 'cso']);
    $other = User::factory()->create(['role' => 'cso']);

    $this->actingAs($sales)
        ->post(route('sales-quotas.store'), [
            'user_id' => $other->id,
            'period_type' => 'monthly',
            'period' => '2026-04',
            'target_amount' => 1000000,
        ])
        ->assertForbidden();
});

// ── 중복 검증 ─────────────────────────────────────────────────────────────────

test('동일 조건의 목표는 중복 등록할 수 없다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $sales = User::factory()->create(['role' => 'cso']);

    SalesQuota::factory()->create([
        'user_id' => $sales->id,
        'product_id' => null,
        'period_type' => 'monthly',
        'period' => '2026-04',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('sales-quotas.store'), [
            'user_id' => $sales->id,
            'product_id' => null,
            'period_type' => 'monthly',
            'period' => '2026-04',
            'target_amount' => 9999999,
        ])
        ->assertSessionHasErrors('period');
});

test('다른 사용자는 같은 기간 조건으로 등록 가능하다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $sales1 = User::factory()->create(['role' => 'cso']);
    $sales2 = User::factory()->create(['role' => 'cso']);

    SalesQuota::factory()->create([
        'user_id' => $sales1->id,
        'product_id' => null,
        'period_type' => 'monthly',
        'period' => '2026-04',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('sales-quotas.store'), [
            'user_id' => $sales2->id,
            'product_id' => null,
            'period_type' => 'monthly',
            'period' => '2026-04',
            'target_amount' => 3000000,
        ])
        ->assertRedirect();

    expect(SalesQuota::count())->toBe(2);
});

test('제품 있는 목표와 없는 목표는 같은 기간에 공존 가능하다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $sales = User::factory()->create(['role' => 'cso']);
    $product = Product::factory()->create();

    SalesQuota::factory()->create([
        'user_id' => $sales->id,
        'product_id' => null,
        'period_type' => 'monthly',
        'period' => '2026-04',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('sales-quotas.store'), [
            'user_id' => $sales->id,
            'product_id' => $product->id,
            'period_type' => 'monthly',
            'period' => '2026-04',
            'target_amount' => 1000000,
        ])
        ->assertRedirect();

    expect(SalesQuota::count())->toBe(2);
});

// ── 기간 형식 검증 ────────────────────────────────────────────────────────────

test('잘못된 월별 기간 형식은 거부된다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $sales = User::factory()->create(['role' => 'cso']);

    $this->actingAs($admin)
        ->post(route('sales-quotas.store'), [
            'user_id' => $sales->id,
            'period_type' => 'monthly',
            'period' => '2026-4',
            'target_amount' => 1000000,
        ])
        ->assertSessionHasErrors('period');
});

test('잘못된 분기 형식은 거부된다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $sales = User::factory()->create(['role' => 'cso']);

    $this->actingAs($admin)
        ->post(route('sales-quotas.store'), [
            'user_id' => $sales->id,
            'period_type' => 'quarterly',
            'period' => '2026-Q5',
            'target_amount' => 1000000,
        ])
        ->assertSessionHasErrors('period');
});

// ── 수정/삭제 ─────────────────────────────────────────────────────────────────

test('관리자는 목표를 수정할 수 있다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $sales = User::factory()->create(['role' => 'cso']);

    $quota = SalesQuota::factory()->create([
        'user_id' => $sales->id,
        'period_type' => 'monthly',
        'period' => '2026-04',
        'target_amount' => 3000000,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->put(route('sales-quotas.update', $quota), [
            'user_id' => $sales->id,
            'product_id' => null,
            'period_type' => 'monthly',
            'period' => '2026-04',
            'target_amount' => 5000000,
        ])
        ->assertRedirect();

    expect((float) $quota->fresh()->target_amount)->toBe(5000000.0);
});

test('관리자는 목표를 삭제할 수 있다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $sales = User::factory()->create(['role' => 'cso']);

    $quota = SalesQuota::factory()->create([
        'user_id' => $sales->id,
        'period_type' => 'monthly',
        'period' => '2026-04',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('sales-quotas.destroy', $quota))
        ->assertRedirect();

    expect(SalesQuota::count())->toBe(0);
});

test('영업사원은 목표를 삭제할 수 없다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $sales = User::factory()->create(['role' => 'cso']);

    $quota = SalesQuota::factory()->create([
        'user_id' => $sales->id,
        'period_type' => 'monthly',
        'period' => '2026-04',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($sales)
        ->delete(route('sales-quotas.destroy', $quota))
        ->assertForbidden();

    expect(SalesQuota::count())->toBe(1);
});
