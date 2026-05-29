<?php

use App\Models\User;

test('sales 는 sales 대시보드에 접근할 수 있다', function () {
    $sales = User::factory()->create(['role' => 'sales']);

    $this->actingAs($sales)
        ->get(route('sales.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Sales/Dashboard'));
});

test('admin 은 sales 대시보드에 접근할 수 없다 (role:sales)', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('sales.dashboard'))
        ->assertForbidden();
});

