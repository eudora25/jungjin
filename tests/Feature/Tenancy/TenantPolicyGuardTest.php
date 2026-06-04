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

// (제거) "tenant_id 없는 admin(과도기)" — MT-4-finalize 이후 비-platform 사용자는 항상 테넌트에 소속되므로
//        null 테넌트 pharma 시나리오는 더 이상 유효하지 않다. Gate::before 의 null 위임 분기는 방어용으로 유지.
