<?php

use App\Models\Company;
use App\Models\CompanySalesAssignment;
use App\Models\User;

// ── 접근 권한 ──────────────────────────────────────────────────────────────────

test('비로그인 사용자는 담당 배정에 접근할 수 없다', function () {
    $company = Company::factory()->create();

    $this->post(route('companies.sales-assignments.store', $company), ['user_id' => 1])
        ->assertRedirect(route('login'));
});

test('영업사원은 담당 배정을 등록할 수 없다', function () {
    $sales = User::factory()->create(['role' => 'sales']);
    $other = User::factory()->create(['role' => 'sales']);
    $company = Company::factory()->create();

    $this->actingAs($sales)
        ->post(route('companies.sales-assignments.store', $company), ['user_id' => $other->id])
        ->assertForbidden();
});

test('관리자는 영업사원을 거래처에 배정할 수 있다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sales = User::factory()->create(['role' => 'sales', 'is_active' => true]);
    $company = Company::factory()->create();

    $this->actingAs($admin)
        ->post(route('companies.sales-assignments.store', $company), ['user_id' => $sales->id])
        ->assertRedirect();

    expect(CompanySalesAssignment::count())->toBe(1);
    $assignment = CompanySalesAssignment::first();
    expect($assignment->company_id)->toBe($company->id)
        ->and($assignment->user_id)->toBe($sales->id)
        ->and($assignment->assigned_by)->toBe($admin->id)
        ->and($assignment->assigned_at)->not->toBeNull();
});

// ── 검증 규칙 ──────────────────────────────────────────────────────────────────

test('영업사원이 아닌 사용자는 담당으로 지정할 수 없다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $otherAdmin = User::factory()->create(['role' => 'admin']);
    $company = Company::factory()->create();

    $this->actingAs($admin)
        ->post(route('companies.sales-assignments.store', $company), ['user_id' => $otherAdmin->id])
        ->assertSessionHasErrors('user_id');

    expect(CompanySalesAssignment::count())->toBe(0);
});

test('비활성 영업사원은 담당으로 지정할 수 없다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sales = User::factory()->create(['role' => 'sales', 'is_active' => false]);
    $company = Company::factory()->create();

    $this->actingAs($admin)
        ->post(route('companies.sales-assignments.store', $company), ['user_id' => $sales->id])
        ->assertSessionHasErrors('user_id');
});

test('같은 거래처에 같은 영업사원을 중복 배정할 수 없다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sales = User::factory()->create(['role' => 'sales', 'is_active' => true]);
    $company = Company::factory()->create();

    CompanySalesAssignment::create([
        'company_id' => $company->id,
        'user_id' => $sales->id,
        'assigned_at' => now(),
        'assigned_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('companies.sales-assignments.store', $company), ['user_id' => $sales->id])
        ->assertSessionHasErrors('user_id');

    expect(CompanySalesAssignment::count())->toBe(1);
});

// ── 삭제 ──────────────────────────────────────────────────────────────────────

test('관리자는 담당 배정을 해제할 수 있다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sales = User::factory()->create(['role' => 'sales']);
    $company = Company::factory()->create();

    $assignment = CompanySalesAssignment::create([
        'company_id' => $company->id,
        'user_id' => $sales->id,
        'assigned_at' => now(),
        'assigned_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('companies.sales-assignments.destroy', [$company, $assignment]))
        ->assertRedirect();

    expect(CompanySalesAssignment::count())->toBe(0);
});

test('영업사원은 담당 배정을 해제할 수 없다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sales = User::factory()->create(['role' => 'sales']);
    $company = Company::factory()->create();

    $assignment = CompanySalesAssignment::create([
        'company_id' => $company->id,
        'user_id' => $sales->id,
        'assigned_at' => now(),
        'assigned_by' => $admin->id,
    ]);

    $this->actingAs($sales)
        ->delete(route('companies.sales-assignments.destroy', [$company, $assignment]))
        ->assertForbidden();
});

test('다른 거래처 ID로 배정 해제 시도하면 404', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sales = User::factory()->create(['role' => 'sales']);
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $assignment = CompanySalesAssignment::create([
        'company_id' => $companyA->id,
        'user_id' => $sales->id,
        'assigned_at' => now(),
        'assigned_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('companies.sales-assignments.destroy', [$companyB, $assignment]))
        ->assertNotFound();

    expect(CompanySalesAssignment::count())->toBe(1);
});

// ── 거래처 상세 prop ──────────────────────────────────────────────────────────

test('거래처 상세 페이지에 담당 영업사원 목록이 prop으로 전달된다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sales = User::factory()->create(['role' => 'sales', 'is_active' => true]);
    $company = Company::factory()->create();

    CompanySalesAssignment::create([
        'company_id' => $company->id,
        'user_id' => $sales->id,
        'assigned_at' => now(),
        'assigned_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('companies.show', $company))
        ->assertOk();

    $props = $response->original->getData()['page']['props'];
    expect(count($props['salesAssignments']))->toBe(1)
        ->and($props['salesAssignments'][0]['user_id'])->toBe($sales->id)
        ->and($props['can']['manageSalesAssignments'])->toBeTrue();
});

// ── 거래처 검색 우선순위 ───────────────────────────────────────────────────────

test('영업사원이 거래처를 검색하면 본인 담당 거래처가 먼저 노출된다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sales = User::factory()->create(['role' => 'sales']);

    $assigned = Company::factory()->create(['company_name' => 'Z거래처', 'status' => 'active']);
    $other = Company::factory()->create(['company_name' => 'A거래처', 'status' => 'active']);

    CompanySalesAssignment::create([
        'company_id' => $assigned->id,
        'user_id' => $sales->id,
        'assigned_at' => now(),
        'assigned_by' => $admin->id,
    ]);

    $response = $this->actingAs($sales)
        ->getJson(route('companies.search', ['q' => '거래처']))
        ->assertOk();

    $items = $response->json();
    // 담당 거래처(Z거래처)가 알파벳 순서상 뒤지만 우선 정렬
    expect($items[0]['id'])->toBe($assigned->id)
        ->and($items[1]['id'])->toBe($other->id);
});

test('assigned_to_me 필터는 본인 담당 거래처만 반환한다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sales = User::factory()->create(['role' => 'sales']);

    $assigned = Company::factory()->create(['status' => 'active']);
    Company::factory()->create(['status' => 'active']); // 미배정

    CompanySalesAssignment::create([
        'company_id' => $assigned->id,
        'user_id' => $sales->id,
        'assigned_at' => now(),
        'assigned_by' => $admin->id,
    ]);

    $response = $this->actingAs($sales)
        ->getJson(route('companies.search', ['assigned_to_me' => 1]))
        ->assertOk();

    $items = $response->json();
    expect(count($items))->toBe(1)
        ->and($items[0]['id'])->toBe($assigned->id);
});

// ── Sales 대시보드 prop ────────────────────────────────────────────────────────

test('Sales 대시보드 props 에 myAssignedCompanies 가 포함된다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sales = User::factory()->create(['role' => 'sales']);
    $company = Company::factory()->create(['company_name' => '내거래처']);

    CompanySalesAssignment::create([
        'company_id' => $company->id,
        'user_id' => $sales->id,
        'assigned_at' => now(),
        'assigned_by' => $admin->id,
    ]);

    $response = $this->actingAs($sales)
        ->get(route('sales.dashboard'))
        ->assertOk();

    $props = $response->original->getData()['page']['props'];
    expect(array_key_exists('myAssignedCompanies', $props))->toBeTrue()
        ->and($props['myAssignedCompanyTotal'])->toBe(1)
        ->and($props['myAssignedCompanies'][0]['company_name'])->toBe('내거래처');
});
