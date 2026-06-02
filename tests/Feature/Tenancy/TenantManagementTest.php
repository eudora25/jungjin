<?php

use App\Models\Tenant;
use App\Models\User;

/**
 * GAP-10 MT-6 — super_admin 제약사 관리 페이지.
 */
test('super_admin 은 제약사 목록을 조회한다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    Tenant::factory()->count(2)->create();

    $this->actingAs($super)
        ->get(route('platform.tenants.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Platform/Tenants/Index')->has('tenants.data'));
});

test('제약사 목록에 관리자(pharma) 계정이 함께 표시된다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $tenant = Tenant::factory()->create();
    User::factory()->create(['role' => 'pharma', 'tenant_id' => $tenant->id, 'name' => '대표관리자']);
    // cso 는 관리자 컬럼에 노출되지 않아야 한다
    User::factory()->create(['role' => 'cso', 'tenant_id' => $tenant->id]);

    $this->actingAs($super)
        ->get(route('platform.tenants.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Platform/Tenants/Index')
            // 기본(시드) 제약사가 함께 있으므로 생성한 제약사 행을 찾아 검증
            ->where('tenants.data', function ($data) use ($tenant) {
                $row = collect($data)->firstWhere('id', $tenant->id);

                // pharma 1명만 노출 (cso 제외)
                return $row !== null
                    && count($row['users']) === 1
                    && $row['users'][0]['name'] === '대표관리자';
            })
        );
});

test('제약사 상세 화면에 소속 사용자(관리자·영업사원)가 pharma 우선으로 표시된다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $tenant = Tenant::factory()->create();
    User::factory()->create(['role' => 'cso', 'tenant_id' => $tenant->id, 'name' => '영업원']);
    User::factory()->create(['role' => 'pharma', 'tenant_id' => $tenant->id, 'name' => '대표관리자']);

    $this->actingAs($super)
        ->get(route('platform.tenants.show', $tenant))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Platform/Tenants/Show')
            ->where('tenant.id', $tenant->id)
            ->has('users.data', 2)
            // FIELD(role,'pharma','cso') 정렬 — 관리자(pharma)가 먼저
            ->where('users.data.0.role', 'pharma')
            ->where('users.data.0.name', '대표관리자')
            ->where('users.data.1.role', 'cso')
        );
});

test('제약사 상세의 소속 사용자 목록은 이름·이메일로 검색된다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $tenant = Tenant::factory()->create();
    User::factory()->create(['role' => 'pharma', 'tenant_id' => $tenant->id, 'name' => '김철수']);
    User::factory()->create(['role' => 'cso', 'tenant_id' => $tenant->id, 'name' => '박영희']);

    $this->actingAs($super)
        ->get(route('platform.tenants.show', ['tenant' => $tenant, 'search' => '김철수']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Platform/Tenants/Show')
            ->has('users.data', 1)
            ->where('users.data.0.name', '김철수')
            ->where('filters.search', '김철수')
        );
});

test('제약사 상세의 소속 사용자 목록은 페이지네이션된다 (per_page 10)', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $tenant = Tenant::factory()->create();
    User::factory()->count(12)->create(['role' => 'cso', 'tenant_id' => $tenant->id]);

    $this->actingAs($super)
        ->get(route('platform.tenants.show', $tenant))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Platform/Tenants/Show')
            ->has('users.data', 10)
            ->where('users.total', 12)
            ->where('users.per_page', 10)
        );
});

test('super_admin 은 제약사 등록 시 관리자(pharma) 계정을 함께 생성한다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);

    $this->actingAs($super)
        ->post(route('platform.tenants.store'), [
            'name' => '한미약품',
            'code' => 'HANMI',
            'status' => 'active',
            'admin_name' => '김대표',
            'admin_email' => 'owner@hanmi.test',
            'admin_password' => 'password123',
            'admin_password_confirmation' => 'password123',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tenants', ['name' => '한미약품', 'code' => 'HANMI', 'created_by' => $super->id]);

    $tenant = Tenant::where('code', 'HANMI')->firstOrFail();
    $this->assertDatabaseHas('users', [
        'email' => 'owner@hanmi.test',
        'name' => '김대표',
        'role' => 'pharma',
        'tenant_id' => $tenant->id,
        'is_active' => true,
    ]);
});

test('사업자등록번호는 하이픈을 제거하고 숫자만 저장한다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);

    $this->actingAs($super)
        ->post(route('platform.tenants.store'), [
            'name' => '대웅제약',
            'business_registration_number' => '123-45-67890',
            'status' => 'active',
            'admin_name' => '김대표',
            'admin_email' => 'owner@daewoong.test',
            'admin_password' => 'password123',
            'admin_password_confirmation' => 'password123',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tenants', [
        'name' => '대웅제약',
        'business_registration_number' => '1234567890',
    ]);
});

test('제약사 수정 시에도 사업자등록번호 하이픈을 제거한다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $tenant = Tenant::factory()->create(['business_registration_number' => '1112223333']);

    $this->actingAs($super)
        ->put(route('platform.tenants.update', $tenant), [
            'name' => $tenant->name,
            'business_registration_number' => '999-88-77777',
            'status' => 'active',
        ])
        ->assertRedirect();

    expect($tenant->fresh()->business_registration_number)->toBe('9998877777');
});

test('제약사 등록 시 제약사명은 필수', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);

    $this->actingAs($super)
        ->post(route('platform.tenants.store'), ['name' => '', 'status' => 'active'])
        ->assertSessionHasErrors('name');
});

test('제약사 등록 시 관리자 이메일은 필수이며 중복 불가', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    User::factory()->create(['email' => 'dup@hanmi.test']);

    // 관리자 정보 누락 → 검증 실패 + tenant 미생성(트랜잭션)
    $this->actingAs($super)
        ->post(route('platform.tenants.store'), [
            'name' => '계정없는약품',
            'status' => 'active',
        ])
        ->assertSessionHasErrors(['admin_name', 'admin_email', 'admin_password']);
    $this->assertDatabaseMissing('tenants', ['name' => '계정없는약품']);

    // 이메일 중복
    $this->actingAs($super)
        ->post(route('platform.tenants.store'), [
            'name' => '중복약품',
            'status' => 'active',
            'admin_name' => '김중복',
            'admin_email' => 'dup@hanmi.test',
            'admin_password' => 'password123',
            'admin_password_confirmation' => 'password123',
        ])
        ->assertSessionHasErrors('admin_email');
});

test('super_admin 은 제약사 admin 계정을 생성한다 (위임형)', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
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
        'role' => 'pharma',
        'tenant_id' => $tenant->id,
    ]);
});

test('super_admin 은 제약사 관리자(pharma) 계정을 수정한다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['role' => 'pharma', 'tenant_id' => $tenant->id, 'name' => '구이름']);

    $this->actingAs($super)
        ->put(route('platform.tenants.admins.update', [$tenant, $admin]), [
            'name' => '새이름',
            'email' => 'new@hanmi.test',
        ])
        ->assertRedirect(route('platform.tenants.show', $tenant));

    $this->assertDatabaseHas('users', [
        'id' => $admin->id,
        'name' => '새이름',
        'email' => 'new@hanmi.test',
    ]);
});

test('관리자 계정 수정(성명·이메일)은 비밀번호를 변경하지 않는다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['role' => 'pharma', 'tenant_id' => $tenant->id]);
    $originalPassword = $admin->password;

    $this->actingAs($super)
        ->put(route('platform.tenants.admins.update', [$tenant, $admin]), [
            'name' => '바뀐이름',
            'email' => $admin->email,
        ])
        ->assertRedirect();

    expect($admin->fresh()->name)->toBe('바뀐이름')
        ->and($admin->fresh()->password)->toBe($originalPassword);
});

test('관리자 계정 이메일은 다른 사용자와 중복될 수 없다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['role' => 'pharma', 'tenant_id' => $tenant->id]);
    User::factory()->create(['email' => 'taken@hanmi.test']);

    $this->actingAs($super)
        ->put(route('platform.tenants.admins.update', [$tenant, $admin]), [
            'name' => $admin->name,
            'email' => 'taken@hanmi.test',
        ])
        ->assertSessionHasErrors('email');
});

test('super_admin 은 제약사 관리자(pharma) 계정을 삭제한다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['role' => 'pharma', 'tenant_id' => $tenant->id]);

    $this->actingAs($super)
        ->delete(route('platform.tenants.admins.destroy', [$tenant, $admin]))
        ->assertRedirect(route('platform.tenants.show', $tenant));

    $this->assertDatabaseMissing('users', ['id' => $admin->id]);
});

test('super_admin 은 제약사 관리자(pharma) 비밀번호를 재설정한다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['role' => 'pharma', 'tenant_id' => $tenant->id]);
    $originalPassword = $admin->password;

    $this->actingAs($super)
        ->post(route('platform.tenants.admins.reset-password', [$tenant, $admin]), [
            'password' => 'newpass1234',
            'password_confirmation' => 'newpass1234',
        ])
        ->assertRedirect(route('platform.tenants.show', $tenant));

    expect($admin->fresh()->password)->not->toBe($originalPassword);
});

test('다른 제약사 소속 계정은 수정·삭제할 수 없다 (404)', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $adminB = User::factory()->create(['role' => 'pharma', 'tenant_id' => $tenantB->id]);

    // tenantA 경로로 tenantB 소속 계정을 수정 시도 → 404
    $this->actingAs($super)
        ->put(route('platform.tenants.admins.update', [$tenantA, $adminB]), [
            'name' => '침범',
            'email' => 'x@hanmi.test',
        ])
        ->assertNotFound();

    $this->actingAs($super)
        ->delete(route('platform.tenants.admins.destroy', [$tenantA, $adminB]))
        ->assertNotFound();
});

test('super_admin 은 제약사 영업사원(cso) 계정을 수정한다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $tenant = Tenant::factory()->create();
    $sales = User::factory()->create(['role' => 'cso', 'tenant_id' => $tenant->id, 'name' => '구이름']);

    $this->actingAs($super)
        ->put(route('platform.tenants.admins.update', [$tenant, $sales]), [
            'name' => '새이름',
            'email' => 'sales-new@hanmi.test',
        ])
        ->assertRedirect(route('platform.tenants.show', $tenant));

    $this->assertDatabaseHas('users', [
        'id' => $sales->id,
        'name' => '새이름',
        'email' => 'sales-new@hanmi.test',
        'role' => 'cso',
    ]);
});

test('super_admin 은 제약사 영업사원(cso) 계정을 삭제·비밀번호 재설정한다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $tenant = Tenant::factory()->create();
    $sales = User::factory()->create(['role' => 'cso', 'tenant_id' => $tenant->id]);
    $originalPassword = $sales->password;

    $this->actingAs($super)
        ->post(route('platform.tenants.admins.reset-password', [$tenant, $sales]), [
            'password' => 'newpass1234',
            'password_confirmation' => 'newpass1234',
        ])
        ->assertRedirect(route('platform.tenants.show', $tenant));

    expect($sales->fresh()->password)->not->toBe($originalPassword);

    $this->actingAs($super)
        ->delete(route('platform.tenants.admins.destroy', [$tenant, $sales]))
        ->assertRedirect(route('platform.tenants.show', $tenant));

    $this->assertDatabaseMissing('users', ['id' => $sales->id]);
});

test('platform 운영자(tenant_id=null) 계정은 제약사 관리 경로로 수정할 수 없다 (404)', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $tenant = Tenant::factory()->create();
    $otherPlatform = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);

    $this->actingAs($super)
        ->delete(route('platform.tenants.admins.destroy', [$tenant, $otherPlatform]))
        ->assertNotFound();
});

test('pharma 는 다른 제약사 관리자 계정을 수정할 수 없다 (403)', function () {
    $tenant = Tenant::factory()->create();
    $pharma = User::factory()->create(['role' => 'pharma', 'tenant_id' => $tenant->id]);
    $admin = User::factory()->create(['role' => 'pharma', 'tenant_id' => $tenant->id]);

    $this->actingAs($pharma)
        ->put(route('platform.tenants.admins.update', [$tenant, $admin]), [
            'name' => 'X',
            'email' => 'x@hanmi.test',
        ])
        ->assertForbidden();
});

test('admin 은 제약사 관리에 접근할 수 없다 (role:platform)', function () {
    $admin = User::factory()->create(['role' => 'pharma']);

    $this->actingAs($admin)->get(route('platform.tenants.index'))->assertForbidden();
    $this->actingAs($admin)->post(route('platform.tenants.store'), ['name' => 'X', 'status' => 'active'])->assertForbidden();
});

test('sales 는 제약사 관리에 접근할 수 없다', function () {
    $sales = User::factory()->create(['role' => 'cso']);

    $this->actingAs($sales)->get(route('platform.tenants.index'))->assertForbidden();
});

test('super_admin 이 대시보드 진입 시 제약사 관리로 리다이렉트된다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);

    $this->actingAs($super)
        ->get(route('dashboard'))
        ->assertRedirect(route('platform.tenants.index'));
});

test('artisan tenancy:make-super-admin 이 기존 사용자를 승격한다', function () {
    $user = User::factory()->create(['role' => 'pharma']);

    $this->artisan('tenancy:make-super-admin', ['email' => $user->email])
        ->assertExitCode(0);

    expect($user->fresh()->role)->toBe('platform')
        ->and($user->fresh()->tenant_id)->toBeNull();
});
