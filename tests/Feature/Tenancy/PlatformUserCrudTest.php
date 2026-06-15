<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * GAP-10 후속-C (§6.10) — platform 의 사용자 전역 CRUD.
 * platform 은 모든 제약사의 **pharma·cso 계정**을 횡단 등록·수정·삭제한다(생성 시 제약사 tenant 선택).
 * platform 역할 계정 자체는 UI 로 만들지 않는다(artisan 승격 — C-5 기본 비허용).
 */
function pucPlatformAdmin(): User
{
    return User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
}

// ---- 생성 ----

test('platform 은 제약사를 지정해 pharma 계정을 등록한다', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs(pucPlatformAdmin())
        ->post(route('platform.users.store'), [
            'name' => '제약사관리자',
            'email' => 'pharma-new@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'pharma',
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', [
        'email' => 'pharma-new@example.com',
        'role' => 'pharma',
        'tenant_id' => $tenant->id,
    ]);
});

test('platform 은 cso 계정도 등록한다', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs(pucPlatformAdmin())
        ->post(route('platform.users.store'), [
            'name' => '영업원',
            'email' => 'cso-new@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'cso',
            'tenant_id' => $tenant->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', ['email' => 'cso-new@example.com', 'role' => 'cso', 'tenant_id' => $tenant->id]);
});

test('등록 시 tenant_id 는 필수이고 role 은 pharma/cso 만 허용한다 (platform UI 생성 불가)', function () {
    $platform = pucPlatformAdmin();

    // tenant 없음
    $this->actingAs($platform)
        ->post(route('platform.users.store'), [
            'name' => 'X', 'email' => 'a@example.com',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'role' => 'pharma',
        ])
        ->assertSessionHasErrors('tenant_id');

    // role=platform 거부
    $tenant = Tenant::factory()->create();
    $this->actingAs($platform)
        ->post(route('platform.users.store'), [
            'name' => 'X', 'email' => 'b@example.com',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'role' => 'platform', 'tenant_id' => $tenant->id,
        ])
        ->assertSessionHasErrors('role');

    $this->assertDatabaseMissing('users', ['email' => 'b@example.com']);
});

// ---- 수정/삭제 (교차 테넌트) ----

test('platform 은 다른 제약사의 사용자도 수정한다', function () {
    $b = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'cso', 'tenant_id' => $b->id, 'name' => '원래이름']);

    $this->actingAs(pucPlatformAdmin())
        ->put(route('platform.users.update', $user), [
            'name' => '수정이름',
            'email' => $user->email,
            'role' => 'cso',
            'tenant_id' => $b->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => '수정이름']);
});

test('platform 은 사용자를 하드 삭제한다', function () {
    $user = User::factory()->create(['role' => 'cso']);

    $this->actingAs(pucPlatformAdmin())
        ->delete(route('platform.users.destroy', $user))
        ->assertRedirect(route('platform.users.index'));

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

test('platform 은 사용자를 토글·비밀번호 재설정한다', function () {
    $platform = pucPlatformAdmin();
    $user = User::factory()->create(['role' => 'cso', 'is_active' => true]);

    $this->actingAs($platform)->post(route('platform.users.toggle-active', $user))->assertRedirect();
    expect($user->fresh()->is_active)->toBeFalse();

    $this->actingAs($platform)->post(route('platform.users.reset-password', $user), [
        'password' => 'NewPass123!', 'password_confirmation' => 'NewPass123!',
    ])->assertRedirect();
    expect(Hash::check('NewPass123!', $user->fresh()->password))->toBeTrue();
});

// ---- 가드: platform 계정은 UI 로 관리 불가 (C-5) ----

test('platform 계정은 UI 로 수정·삭제·토글할 수 없다 (artisan 전용)', function () {
    $platform = pucPlatformAdmin();
    $other = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);

    $this->actingAs($platform)->get(route('platform.users.edit', $other))->assertForbidden();
    $this->actingAs($platform)->put(route('platform.users.update', $other), [
        'name' => 'X', 'email' => $other->email, 'role' => 'pharma', 'tenant_id' => Tenant::factory()->create()->id,
    ])->assertForbidden();
    $this->actingAs($platform)->delete(route('platform.users.destroy', $other))->assertForbidden();
    $this->actingAs($platform)->post(route('platform.users.toggle-active', $other))->assertForbidden();
});

// ---- 화면 렌더 + 권한 차단 ----

test('platform 은 등록/수정/상세 화면에 접근한다', function () {
    $platform = pucPlatformAdmin();
    $user = User::factory()->create(['role' => 'cso']);

    $this->actingAs($platform)->get(route('platform.users.create'))->assertOk();
    $this->actingAs($platform)->get(route('platform.users.edit', $user))->assertOk();
    $this->actingAs($platform)->get(route('platform.users.show', $user))->assertOk();
});

test('pharma·cso 는 플랫폼 사용자 CRUD 에 접근할 수 없다', function () {
    $pharma = User::factory()->create(['role' => 'pharma']);
    $cso = User::factory()->create(['role' => 'cso']);
    $target = User::factory()->create(['role' => 'cso']);

    $this->actingAs($pharma)->get(route('platform.users.create'))->assertForbidden();
    $this->actingAs($pharma)->post(route('platform.users.store'), [
        'name' => 'X', 'email' => 'x@example.com',
        'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        'role' => 'cso', 'tenant_id' => $target->tenant_id,
    ])->assertForbidden();
    $this->actingAs($cso)->get(route('platform.users.index'))->assertForbidden();
    $this->actingAs($pharma)->delete(route('platform.users.destroy', $target))->assertForbidden();
});
