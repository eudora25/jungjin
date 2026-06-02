<?php

use App\Models\CodeDefinition;
use App\Models\CodeGroup;
use Illuminate\Database\QueryException;

/**
 * GAP-10 — 코드 정의 테이블 (user_role 의미 조회).
 */
test('user_role 코드 3종이 시드되어 조회된다', function () {
    $roles = CodeDefinition::group(CodeDefinition::GROUP_USER_ROLE)->pluck('name', 'code');

    expect($roles)->toHaveCount(3)
        ->and($roles['platform'])->toBe('플랫폼 운영자')
        ->and($roles['pharma'])->toBe('제약사 관리자')
        ->and($roles['cso'])->toBe('영업(CSO)');
});

test('label() 로 코드 라벨을 조회한다', function () {
    expect(CodeDefinition::label('user_role', 'platform'))->toBe('플랫폼 운영자')
        ->and(CodeDefinition::label('user_role', 'cso'))->toBe('영업(CSO)')
        ->and(CodeDefinition::label('user_role', '없는코드'))->toBe('없는코드')
        ->and(CodeDefinition::label('user_role', null))->toBeNull();
});

test('group_code+code 는 유일하다', function () {
    expect(fn () => CodeDefinition::create([
        'group_code' => 'user_role', 'code' => 'platform', 'name' => '중복',
    ]))->toThrow(QueryException::class);
});

test('user_role 코드 그룹이 시드되어 조회된다', function () {
    $group = CodeGroup::where('group_code', 'user_role')->first();

    expect($group)->not->toBeNull()
        ->and($group->name)->toBe('사용자 권한')
        ->and($group->definitions)->toHaveCount(3);
});

test('코드 정의는 상위 코드 그룹을 참조한다', function () {
    $definition = CodeDefinition::group(CodeDefinition::GROUP_USER_ROLE)->first();

    expect($definition->codeGroup)->not->toBeNull()
        ->and($definition->codeGroup->group_code)->toBe('user_role');
});

test('정의되지 않은 group_code 는 외래키 제약으로 거부된다', function () {
    expect(fn () => CodeDefinition::create([
        'group_code' => '없는그룹', 'code' => 'x', 'name' => 'X',
    ]))->toThrow(QueryException::class);
});

test('참조 중인 코드 그룹은 삭제할 수 없다', function () {
    expect(fn () => CodeGroup::where('group_code', 'user_role')->first()->delete())
        ->toThrow(QueryException::class);
});
