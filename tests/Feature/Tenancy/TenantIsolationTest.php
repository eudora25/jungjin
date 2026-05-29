<?php

use App\Models\Company;
use App\Models\Performance;
use App\Models\SalesQuota;
use App\Models\Settlement;
use App\Models\Tenant;
use App\Models\User;

/**
 * GAP-10 MT-7 — 테넌트 격리 회귀 (보안 핵심).
 * 실제 워크플로(거래처·실적·정산·목표) 전반에서 admin 이 자기 제약사 것만 보고,
 * 다른 제약사 자원엔 접근할 수 없음을 HTTP 레벨로 종합 검증한다.
 */
function adminFor(Tenant $t): User
{
    return User::factory()->create(['role' => 'admin', 'tenant_id' => $t->id]);
}

// ---- 목록 격리: 자기 제약사 것만 ----

test('거래처 목록은 자기 제약사 것만 보인다', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    Company::factory()->count(2)->create(['tenant_id' => $a->id]);
    Company::factory()->count(3)->create(['tenant_id' => $b->id]);

    $this->actingAs(adminFor($a))
        ->get(route('companies.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('companies.data', 2));
});

test('실적 목록은 자기 제약사 것만 보인다', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    Performance::factory()->count(2)->create(['tenant_id' => $a->id]);
    Performance::factory()->count(3)->create(['tenant_id' => $b->id]);

    $this->actingAs(adminFor($a))
        ->get(route('performance.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('performances.data', 2));
});

test('정산 목록은 자기 제약사 것만 보인다', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    Settlement::factory()->count(2)->create(['tenant_id' => $a->id]);
    Settlement::factory()->count(3)->create(['tenant_id' => $b->id]);

    $this->actingAs(adminFor($a))
        ->get(route('settlements.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('settlements.data', 2));
});

test('목표 목록은 자기 제약사 것만 보인다', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    SalesQuota::factory()->count(2)->create(['tenant_id' => $a->id]);
    SalesQuota::factory()->count(3)->create(['tenant_id' => $b->id]);

    $this->actingAs(adminFor($a))
        ->get(route('sales-quotas.index'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('quotas.data', 2));
});

// ---- 교차 테넌트 상세 차단 (authorize → Gate::before 403) ----
// 목록은 TenantScope(MT-3)로, 상세는 모든 show 의 authorize 에서 Gate::before(MT-5)로 차단.

test('다른 제약사의 거래처 상세는 차단된다 (Gate::before 403)', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $companyB = Company::factory()->create(['tenant_id' => $b->id]);

    $this->actingAs(adminFor($a))
        ->get(route('companies.show', $companyB->id))
        ->assertForbidden();
});

test('다른 제약사의 실적 상세는 차단된다 (Gate::before 403)', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $perfB = Performance::factory()->create(['tenant_id' => $b->id]);

    $this->actingAs(adminFor($a))
        ->get(route('performance.show', $perfB->id))
        ->assertForbidden();
});

test('다른 제약사의 정산 상세는 차단된다 (Gate::before 403)', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $settlementB = Settlement::factory()->create(['tenant_id' => $b->id]);

    $this->actingAs(adminFor($a))
        ->get(route('settlements.show', $settlementB->id))
        ->assertForbidden();
});

// ---- 생성 시 자기 제약사로 자동 주입 (HTTP) ----

test('admin 이 거래처를 생성하면 tenant_id 가 자기 제약사로 자동 주입된다', function () {
    $a = Tenant::factory()->create();

    $this->actingAs(adminFor($a))
        ->post(route('companies.store'), ['company_name' => '격리테스트거래처'])
        ->assertRedirect();

    $company = Company::withoutGlobalScopes()->where('company_name', '격리테스트거래처')->first();
    expect($company)->not->toBeNull()
        ->and($company->tenant_id)->toBe($a->id);
});
