<?php

use App\Models\Tenant;
use App\Models\User;

/**
 * GAP-10 MT-6 — super_admin 제약사 관리 페이지.
 */
test('super_admin 은 제약사 목록을 조회한다', function () {
    $super = User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]);
    Tenant::factory()->count(2)->create();

    $this->actingAs($super)
        ->get(route('platform.tenants.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Platform/Tenants/Index')->has('tenants.data'));
});

test('super_admin 은 제약사를 등록한다', function () {
    $super = User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]);

    $this->actingAs($super)
        ->post(route('platform.tenants.store'), [
            'name' => '한미약품',
            'code' => 'HANMI',
            'status' => 'active',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tenants', ['name' => '한미약품', 'code' => 'HANMI', 'created_by' => $super->id]);
});

test('제약사 등록 시 제약사명은 필수', function () {
    $super = User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]);

    $this->actingAs($super)
        ->post(route('platform.tenants.store'), ['name' => '', 'status' => 'active'])
        ->assertSessionHasErrors('name');
});

test('super_admin 은 제약사 admin 계정을 생성한다 (위임형)', function () {
    $super = User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]);
    $tenant = Tenant::factory()->create();

    $this->actingAs($super)
        ->post(route('platform.tenants.admins.store', $tenant), [
            'name' => '김관리',
            'email' => 'admin@hanmi.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertRedirect(route('platform.tenants.show', $tenant));

    $this->assertDatabaseHas('users', [
        'email' => 'admin@hanmi.test',
        'role' => 'admin',
        'tenant_id' => $tenant->id,
    ]);
});

test('admin 은 제약사 관리에 접근할 수 없다 (role:super_admin)', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('platform.tenants.index'))->assertForbidden();
    $this->actingAs($admin)->post(route('platform.tenants.store'), ['name' => 'X', 'status' => 'active'])->assertForbidden();
});

test('sales 는 제약사 관리에 접근할 수 없다', function () {
    $sales = User::factory()->create(['role' => 'sales']);

    $this->actingAs($sales)->get(route('platform.tenants.index'))->assertForbidden();
});

test('super_admin 이 대시보드 진입 시 제약사 관리로 리다이렉트된다', function () {
    $super = User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]);

    $this->actingAs($super)
        ->get(route('dashboard'))
        ->assertRedirect(route('platform.tenants.index'));
});

test('artisan tenancy:make-super-admin 이 기존 사용자를 승격한다', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $this->artisan('tenancy:make-super-admin', ['email' => $user->email])
        ->assertExitCode(0);

    expect($user->fresh()->role)->toBe('super_admin')
        ->and($user->fresh()->tenant_id)->toBeNull();
});
