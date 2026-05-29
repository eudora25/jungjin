<?php

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;

/**
 * GAP-10 MT-5 — 테넌트 권한 게이트 (Gate::before).
 *  - super_admin 전체 통과 / 교차 테넌트 거부 / 동일 테넌트 허용 / null 테넌트 과도기 통과.
 */
test('admin 은 자기 제약사 자원에 대한 권한이 허용된다', function () {
    $a = Tenant::factory()->create();
    $adminA = User::factory()->create(['role' => 'pharma', 'tenant_id' => $a->id]);
    $productA = Product::factory()->create(['tenant_id' => $a->id]);

    expect($adminA->can('update', $productA))->toBeTrue()
        ->and($adminA->can('delete', $productA))->toBeTrue();
});

test('admin 은 다른 제약사 자원에 대한 권한이 거부된다 (Gate::before)', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $adminA = User::factory()->create(['role' => 'pharma', 'tenant_id' => $a->id]);
    $productB = Product::factory()->create(['tenant_id' => $b->id]);

    expect($adminA->can('update', $productB))->toBeFalse()
        ->and($adminA->can('view', $productB))->toBeFalse()
        ->and($adminA->can('delete', $productB))->toBeFalse();
});

test('super_admin 은 모든 제약사 자원에 대한 권한이 통과된다', function () {
    $b = Tenant::factory()->create();
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $productB = Product::factory()->create(['tenant_id' => $b->id]);

    expect($super->can('update', $productB))->toBeTrue()
        ->and($super->can('delete', $productB))->toBeTrue();
});

test('tenant_id 없는 admin(과도기)은 기존 Policy 로 위임된다', function () {
    $a = Tenant::factory()->create();
    $adminNull = User::factory()->create(['role' => 'pharma', 'tenant_id' => null]);
    $productA = Product::factory()->create(['tenant_id' => $a->id]);

    // 교차 테넌트 거부 로직을 타지 않고 ProductPolicy(admin 허용)로 위임
    expect($adminNull->can('update', $productA))->toBeTrue();
});
