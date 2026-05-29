<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'pharma']);
    $this->sales = User::factory()->create(['role' => 'cso']);
});

test('admin 은 사용자 목록을 조회할 수 있다', function () {
    User::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Users/Index'));
});

test('sales 는 사용자 관리 목록에 접근할 수 없다 (role 미들웨어)', function () {
    $this->actingAs($this->sales)
        ->get(route('users.index'))
        ->assertForbidden();
});

test('admin 이 새 사용자를 등록한다', function () {
    $this->actingAs($this->admin)
        ->post(route('users.store'), [
            'name' => '신규사용자',
            'email' => 'new@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'cso',
            'is_active' => true,
        ])
        ->assertRedirect();

    $created = User::where('email', 'new@example.com')->first();
    expect($created)->not->toBeNull()
        ->and($created->role)->toBe('cso')
        ->and($created->is_active)->toBeTrue()
        ->and(Hash::check('Password123!', $created->password))->toBeTrue();
});

test('이메일 중복 시 등록 실패', function () {
    User::factory()->create(['email' => 'dup@example.com']);

    $this->actingAs($this->admin)
        ->post(route('users.store'), [
            'name' => '중복',
            'email' => 'dup@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'cso',
        ])
        ->assertSessionHasErrors('email');
});

test('수정 시 비밀번호를 비우면 기존 비밀번호 유지', function () {
    $user = User::factory()->create([
        'role' => 'cso',
        'password' => Hash::make('OldPass123!'),
    ]);

    $this->actingAs($this->admin)
        ->put(route('users.update', $user), [
            'name' => '수정된이름',
            'email' => $user->email,
            'role' => 'cso',
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $user->refresh();
    expect($user->name)->toBe('수정된이름')
        ->and(Hash::check('OldPass123!', $user->password))->toBeTrue();
});

test('admin 은 다른 사용자를 비활성화할 수 있다', function () {
    $target = User::factory()->create(['is_active' => true]);

    $this->actingAs($this->admin)
        ->post(route('users.toggle-active', $target))
        ->assertRedirect();

    expect($target->fresh()->is_active)->toBeFalse();
});

test('admin 은 자기 자신을 비활성화할 수 없다', function () {
    $this->actingAs($this->admin)
        ->post(route('users.toggle-active', $this->admin))
        ->assertForbidden();
});

test('admin 은 자기 자신을 삭제할 수 없다', function () {
    $this->actingAs($this->admin)
        ->delete(route('users.destroy', $this->admin))
        ->assertForbidden();
});

test('admin 은 사용자 비밀번호를 재설정한다', function () {
    $target = User::factory()->create(['password' => Hash::make('OldPass')]);

    $this->actingAs($this->admin)
        ->post(route('users.reset-password', $target), [
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ])
        ->assertRedirect();

    expect(Hash::check('NewPass123!', $target->fresh()->password))->toBeTrue();
});

test('비활성화된 사용자는 로그인할 수 없다', function () {
    $inactive = User::factory()->create([
        'email' => 'inactive@example.com',
        'password' => Hash::make('Password123!'),
        'is_active' => false,
    ]);

    $this->post(route('login'), [
        'email' => 'inactive@example.com',
        'password' => 'Password123!',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('권한/활성 필터링이 동작한다', function () {
    User::factory()->count(2)->create(['role' => 'pharma', 'is_active' => true]);
    User::factory()->count(3)->create(['role' => 'cso', 'is_active' => false]);

    $this->actingAs($this->admin)
        ->get(route('users.index', ['role' => 'cso', 'active' => '0']))
        ->assertInertia(fn ($page) => $page->where('users.total', 3));
});
