<?php

use App\Models\CodeDefinition;
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
