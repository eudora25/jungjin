<?php

use App\Models\Tenant;
use App\Models\User;

/**
 * GAP-10 후속-B (§6.9) — pharma `/users` 테넌트 스코프.
 * pharma 는 **자사 테넌트의 cso** 만 조회·생성·관리한다. 타 테넌트 사용자·자사 pharma·platform 은 보이거나 관리되지 않는다.
 * (pharma 계정 생성/관리는 platform 전용 — §6.10/C)
 */
function tenantPharma(Tenant $tenant): User
{
    return User::factory()->create(['role' => 'pharma', 'tenant_id' => $tenant->id]);
}

function tenantCso(Tenant $tenant, array $attrs = []): User
{
    return User::factory()->create(array_merge(['role' => 'cso', 'tenant_id' => $tenant->id], $attrs));
}

// ---- 목록 격리 ----

test('pharma 목록에는 자사 cso 만 보인다 (타 테넌트·자사 pharma·platform 제외)', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $pharmaA = tenantPharma($a);
    tenantCso($a);                 // 자사 cso 1
    tenantCso($a);                 // 자사 cso 2
    tenantPharma($a);              // 자사 pharma — 제외
    tenantCso($b);                 // 타 테넌트 cso — 제외
    User::factory()->create(['role' => 'platform', 'tenant_id' => null]); // platform — 제외

    $this->actingAs($pharmaA)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('users.total', 2));
});

// ---- 교차 테넌트 접근 차단 ----

test('pharma 는 타 테넌트 cso 상세/수정/삭제/토글/비번재설정에 접근할 수 없다', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $pharmaA = tenantPharma($a);
    $csoB = tenantCso($b);

    $this->actingAs($pharmaA)->get(route('users.show', $csoB))->assertForbidden();
    $this->actingAs($pharmaA)->put(route('users.update', $csoB), [
        'name' => 'X', 'email' => $csoB->email, 'role' => 'cso',
    ])->assertForbidden();
    $this->actingAs($pharmaA)->delete(route('users.destroy', $csoB))->assertForbidden();
    $this->actingAs($pharmaA)->post(route('users.toggle-active', $csoB))->assertForbidden();
    $this->actingAs($pharmaA)->post(route('users.reset-password', $csoB), [
        'password' => 'NewPass123!', 'password_confirmation' => 'NewPass123!',
    ])->assertForbidden();
});

test('pharma 는 자사 pharma 사용자를 관리할 수 없다 (cso 만 관리)', function () {
    $a = Tenant::factory()->create();
    $pharmaA = tenantPharma($a);
    $otherPharmaA = tenantPharma($a);

    $this->actingAs($pharmaA)->get(route('users.show', $otherPharmaA))->assertForbidden();
    $this->actingAs($pharmaA)->post(route('users.toggle-active', $otherPharmaA))->assertForbidden();
});

// ---- 생성 시 테넌트 자동 주입 + role 고정 ----

test('pharma 가 사용자를 생성하면 tenant_id 가 자사로 자동 주입되고 cso 로 고정된다', function () {
    $a = Tenant::factory()->create();
    $pharmaA = tenantPharma($a);

    $this->actingAs($pharmaA)
        ->post(route('users.store'), [
            'name' => '신규영업',
            'email' => 'newcso@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'cso',
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', [
        'email' => 'newcso@example.com',
        'tenant_id' => $a->id,
        'role' => 'cso',
    ]);
});

test('pharma 는 pharma 권한으로 사용자를 생성할 수 없다', function () {
    $a = Tenant::factory()->create();
    $pharmaA = tenantPharma($a);

    $this->actingAs($pharmaA)
        ->post(route('users.store'), [
            'name' => '관리자생성시도',
            'email' => 'newpharma@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'pharma',
        ])
        ->assertSessionHasErrors('role');

    $this->assertDatabaseMissing('users', ['email' => 'newpharma@example.com']);
});

// ---- 역할 분리 ----

test('platform 은 pharma 의 /users 에 접근할 수 없다 (role:pharma — platform 은 /platform/users 사용)', function () {
    $platform = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);

    $this->actingAs($platform)->get(route('users.index'))->assertForbidden();
});

test('pharma 가 생성한 cso 는 자사 목록에 반영된다 (자동 주입 tenant 일치)', function () {
    $a = Tenant::factory()->create();
    $pharmaA = tenantPharma($a);

    $this->actingAs($pharmaA)->post(route('users.store'), [
        'name' => '영업원',
        'email' => 'listcso@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'role' => 'cso',
    ])->assertRedirect();

    $this->actingAs($pharmaA)
        ->get(route('users.index'))
        ->assertInertia(fn ($page) => $page->where('users.total', 1));
});
