<?php

use App\Models\Tenant;
use App\Models\User;

/**
 * GAP-10 MT-1 — 멀티테넌시 스키마 토대 (tenants, role enum, users.tenant_id).
 */
test('제약사(tenant)를 생성할 수 있다', function () {
    $tenant = Tenant::factory()->create(['name' => '한미약품']);

    expect($tenant->id)->toBeInt()
        ->and($tenant->name)->toBe('한미약품')
        ->and($tenant->isActive())->toBeTrue();

    $this->assertDatabaseHas('tenants', ['name' => '한미약품', 'status' => 'active']);
});

test('admin/sales 사용자는 제약사에 소속될 수 있다', function () {
    $tenant = Tenant::factory()->create();

    $admin = User::factory()->create(['role' => 'pharma', 'tenant_id' => $tenant->id]);
    $sales = User::factory()->create(['role' => 'cso', 'tenant_id' => $tenant->id]);

    expect($admin->tenant->is($tenant))->toBeTrue()
        ->and($tenant->users()->pluck('id'))->toContain($admin->id, $sales->id);
});

test('super_admin 역할을 저장할 수 있고 소속 테넌트는 null 이다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);

    expect($super->isPlatform())->toBeTrue()
        ->and($super->isPharma())->toBeFalse()
        ->and($super->isCso())->toBeFalse()
        ->and($super->tenant_id)->toBeNull();

    $this->assertDatabaseHas('users', ['id' => $super->id, 'role' => 'platform', 'tenant_id' => null]);
});

test('role 헬퍼가 올바르게 동작한다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $sales = User::factory()->create(['role' => 'cso']);

    expect($admin->isPharma())->toBeTrue()
        ->and($admin->isPlatform())->toBeFalse()
        ->and($sales->isCso())->toBeTrue()
        ->and(User::ROLES)->toContain('platform', 'pharma', 'cso');
});

test('제약사 삭제 시 소속 사용자의 tenant_id 는 null 로 풀린다', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'pharma', 'tenant_id' => $tenant->id]);

    $tenant->forceDelete();

    expect($user->fresh()->tenant_id)->toBeNull();
});
