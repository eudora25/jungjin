<?php

use App\Models\User;

test('last_sign_in_at is updated on successful login', function () {
    $user = User::factory()->create(['last_sign_in_at' => null]);

    expect($user->last_sign_in_at)->toBeNull();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $user->refresh();
    expect($user->last_sign_in_at)->not->toBeNull();
});

test('last_sign_in_at is not touched on failed login', function () {
    $before = now()->subDay();
    $user = User::factory()->create(['last_sign_in_at' => $before]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $user->refresh();
    expect($user->last_sign_in_at->timestamp)->toBe($before->timestamp);
});
