<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * GAP-10 MT-2 (1부) — 기본 제약사 시드 + 기존 사용자 백필.
 */
function runBackfillMigration(): void
{
    $migration = include database_path('migrations/2026_05_29_100200_seed_default_tenant_and_backfill_users.php');
    $migration->up();
}

function defaultTenantId(): int
{
    return (int) DB::table('tenants')->where('code', 'DEFAULT')->value('id');
}

test('마이그레이션이 기본 제약사를 시드한다', function () {
    $this->assertDatabaseHas('tenants', ['code' => 'DEFAULT', 'name' => '기본 제약사', 'status' => 'active']);
});

test('백필: tenant_id 없는 admin/sales 가 기본 제약사로 채워진다', function () {
    // 레거시 상황 재현 — tenant_id 없는 사용자
    $admin = User::factory()->create(['role' => 'pharma', 'tenant_id' => null]);
    $sales = User::factory()->create(['role' => 'cso', 'tenant_id' => null]);

    runBackfillMigration();

    $tenantId = defaultTenantId();
    expect($admin->fresh()->tenant_id)->toBe($tenantId)
        ->and($sales->fresh()->tenant_id)->toBe($tenantId);
});

test('백필: super_admin 은 tenant_id 가 NULL 로 유지된다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);

    runBackfillMigration();

    expect($super->fresh()->tenant_id)->toBeNull();
});

test('백필: 이미 소속이 있는 사용자는 덮어쓰지 않는다', function () {
    $other = Tenant::factory()->create();
    $user = User::factory()->create(['role' => 'pharma', 'tenant_id' => $other->id]);

    runBackfillMigration();

    expect($user->fresh()->tenant_id)->toBe($other->id);
});

test('기본 제약사 시드는 멱등 — 재실행해도 중복 생성되지 않는다', function () {
    runBackfillMigration();
    runBackfillMigration();

    expect(DB::table('tenants')->where('code', 'DEFAULT')->count())->toBe(1);
});
