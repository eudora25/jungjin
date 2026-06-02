<?php

use App\Models\CodeDefinition;
use App\Models\CodeGroup;
use App\Models\User;

/**
 * GAP-10 — 코드 그룹/코드 정의 admin CRUD (플랫폼 운영자 전용).
 */
function platformUser(): User
{
    return User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
}

test('platform 은 코드 그룹 목록을 조회한다', function () {
    $this->actingAs(platformUser())
        ->get(route('platform.code-groups.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Platform/CodeGroups/Index')
            ->has('codeGroups.data') // user_role 시드 포함
        );
});

test('platform 은 코드 그룹을 등록한다', function () {
    $this->actingAs(platformUser())
        ->post(route('platform.code-groups.store'), [
            'group_code' => 'settlement_status',
            'name' => '정산 상태',
            'description' => '정산 진행 상태 구분',
            'sort_order' => 2,
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('code_groups', [
        'group_code' => 'settlement_status',
        'name' => '정산 상태',
    ]);
});

test('중복 group_code 는 거부된다', function () {
    $this->actingAs(platformUser())
        ->post(route('platform.code-groups.store'), [
            'group_code' => 'user_role', // 시드된 그룹
            'name' => '중복',
        ])
        ->assertSessionHasErrors('group_code');
});

test('platform 은 코드 그룹을 수정한다', function () {
    $group = CodeGroup::create(['group_code' => 'tmp', 'name' => '임시']);

    $this->actingAs(platformUser())
        ->put(route('platform.code-groups.update', $group), [
            'group_code' => 'tmp',
            'name' => '임시(수정)',
            'sort_order' => 5,
            'is_active' => false,
        ])
        ->assertRedirect();

    expect($group->fresh()->name)->toBe('임시(수정)')
        ->and($group->fresh()->is_active)->toBeFalse();
});

test('정의가 없는 코드 그룹은 삭제된다', function () {
    $group = CodeGroup::create(['group_code' => 'tmp', 'name' => '임시']);

    $this->actingAs(platformUser())
        ->delete(route('platform.code-groups.destroy', $group))
        ->assertRedirect(route('platform.code-groups.index'));

    $this->assertDatabaseMissing('code_groups', ['id' => $group->id]);
});

test('정의가 있는 코드 그룹은 삭제할 수 없다 (FK 제약 — 안내 후 유지)', function () {
    $group = CodeGroup::where('group_code', 'user_role')->first(); // 정의 3개 시드됨

    $this->actingAs(platformUser())
        ->delete(route('platform.code-groups.destroy', $group))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('code_groups', ['id' => $group->id]);
});

test('platform 은 코드 그룹에 코드 정의를 추가한다', function () {
    $group = CodeGroup::create(['group_code' => 'settlement_status', 'name' => '정산 상태']);

    $this->actingAs(platformUser())
        ->post(route('platform.code-groups.definitions.store', $group), [
            'code' => 'paid',
            'name' => '지급완료',
            'sort_order' => 1,
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('code_definitions', [
        'group_code' => 'settlement_status',
        'code' => 'paid',
        'name' => '지급완료',
    ]);
});

test('그룹 내 중복 코드는 거부된다', function () {
    $group = CodeGroup::where('group_code', 'user_role')->first();

    $this->actingAs(platformUser())
        ->post(route('platform.code-groups.definitions.store', $group), [
            'code' => 'platform', // 이미 존재
            'name' => '중복',
        ])
        ->assertSessionHasErrors('code');
});

test('platform 은 코드 정의를 수정한다', function () {
    $group = CodeGroup::where('group_code', 'user_role')->first();
    $definition = $group->definitions()->where('code', 'cso')->first();

    $this->actingAs(platformUser())
        ->put(route('platform.code-groups.definitions.update', [$group, $definition]), [
            'code' => 'cso',
            'name' => '영업(CSO) 수정',
            'sort_order' => 9,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($definition->fresh()->name)->toBe('영업(CSO) 수정');
});

test('platform 은 코드 정의를 삭제한다', function () {
    $group = CodeGroup::create(['group_code' => 'settlement_status', 'name' => '정산 상태']);
    $definition = CodeDefinition::create([
        'group_code' => 'settlement_status', 'code' => 'paid', 'name' => '지급완료',
    ]);

    $this->actingAs(platformUser())
        ->delete(route('platform.code-groups.definitions.destroy', [$group, $definition]))
        ->assertRedirect();

    $this->assertDatabaseMissing('code_definitions', ['id' => $definition->id]);
});

test('다른 그룹의 코드 정의는 404 로 거부된다', function () {
    $userRole = CodeGroup::where('group_code', 'user_role')->first();
    $other = CodeGroup::create(['group_code' => 'settlement_status', 'name' => '정산 상태']);
    $definition = $userRole->definitions()->first(); // user_role 소속

    $this->actingAs(platformUser())
        ->delete(route('platform.code-groups.definitions.destroy', [$other, $definition]))
        ->assertNotFound();
});

test('pharma·cso 는 코드 그룹 영역에 접근할 수 없다', function () {
    $pharma = User::factory()->create(['role' => 'pharma']);
    $cso = User::factory()->create(['role' => 'cso']);
    $group = CodeGroup::where('group_code', 'user_role')->first();

    foreach ([$pharma, $cso] as $user) {
        $this->actingAs($user)->get(route('platform.code-groups.index'))->assertForbidden();
        $this->actingAs($user)
            ->post(route('platform.code-groups.store'), ['group_code' => 'x', 'name' => 'X'])
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('platform.code-groups.definitions.store', $group), ['code' => 'y', 'name' => 'Y'])
            ->assertForbidden();
    }
});
