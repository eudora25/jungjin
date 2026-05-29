<?php

use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;

/**
 * GAP-10 — super_admin(platform) 임퍼서네이션: 특정 제약사로 진입해 그 테넌트 화면을 본다.
 */
test('platform 은 제약사로 진입하면 세션에 기록되고 대시보드로 이동한다', function () {
    $platform = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $tenant = Tenant::factory()->create(['name' => '한미약품']);

    $this->actingAs($platform)
        ->post(route('platform.tenants.enter', $tenant))
        ->assertRedirect(route('dashboard'));

    $this->assertEquals($tenant->id, session('impersonated_tenant_id'));
});

test('진입 중 platform 은 해당 제약사 데이터만 본다 (거래처)', function () {
    $platform = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    Company::factory()->count(2)->create(['tenant_id' => $a->id]);
    Company::factory()->count(3)->create(['tenant_id' => $b->id]);

    // A 로 진입
    $this->actingAs($platform)->post(route('platform.tenants.enter', $a));

    $this->actingAs($platform)
        ->get(route('companies.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('companies.data', 2)); // A 것만
});

test('진입 종료하면 세션이 비고 제약사 목록으로 돌아간다', function () {
    $platform = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $tenant = Tenant::factory()->create();

    $this->actingAs($platform)->post(route('platform.tenants.enter', $tenant));
    $this->actingAs($platform)
        ->post(route('platform.exit'))
        ->assertRedirect(route('platform.tenants.index'));

    expect(session('impersonated_tenant_id'))->toBeNull();
});

test('진입 중 platform 은 대시보드 리다이렉트 없이 제약사 대시보드를 본다', function () {
    $platform = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $tenant = Tenant::factory()->create();

    // 진입 전: 대시보드 → 플랫폼으로 리다이렉트
    $this->actingAs($platform)->get(route('dashboard'))->assertRedirect(route('platform.tenants.index'));

    // 진입 후: 대시보드 그대로 노출
    $this->actingAs($platform)->post(route('platform.tenants.enter', $tenant));
    $this->actingAs($platform)->get(route('dashboard'))->assertOk();
});

test('비-platform(pharma)은 임퍼서네이션 라우트에 접근할 수 없다', function () {
    $pharma = User::factory()->create(['role' => 'pharma']);
    $tenant = Tenant::factory()->create();

    $this->actingAs($pharma)->post(route('platform.tenants.enter', $tenant))->assertForbidden();
    $this->actingAs($pharma)->post(route('platform.exit'))->assertForbidden();
});
