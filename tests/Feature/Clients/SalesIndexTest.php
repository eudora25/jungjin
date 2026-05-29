<?php

use App\Models\User;

test('sales 목록은 role=sales 사용자만 노출한다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    User::factory()->count(3)->create(['role' => 'cso']);
    User::factory()->count(2)->create(['role' => 'pharma']);

    $this->actingAs($admin)
        ->get(route('sales.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Clients/Sales/Index')
            ->where('sales.total', 3)
        );
});

test('영업사원은 영업사원 목록에 접근할 수 없다', function () {
    $sales = User::factory()->create(['role' => 'cso']);

    $this->actingAs($sales)
        ->get(route('sales.index'))
        ->assertForbidden();
});

test('검색어로 이름을 필터링한다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    User::factory()->create(['role' => 'cso', 'name' => '김영업']);
    User::factory()->create(['role' => 'cso', 'name' => '이영업']);

    $this->actingAs($admin)
        ->get(route('sales.index', ['search' => '김']))
        ->assertInertia(fn ($page) => $page->where('sales.total', 1));
});
