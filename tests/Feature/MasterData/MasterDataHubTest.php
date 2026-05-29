<?php

use App\Models\Hospital;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\User;

/**
 * GAP-9 기준정보 마스터 허브 (/master-data)
 */
test('admin 은 마스터 허브에 접근하고 3종 건수를 받는다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);

    Product::factory()->count(3)->create();
    Pharmacy::factory()->count(2)->create();
    Hospital::factory()->count(4)->create();

    $this->actingAs($admin)
        ->get(route('master-data.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('MasterData/Index')
            ->where('counts.products', 3)
            ->where('counts.pharmacies', 2)
            ->where('counts.hospitals', 4)
        );
});

test('sales 는 마스터 허브에 접근할 수 없다 (role:pharma)', function () {
    $sales = User::factory()->create(['role' => 'cso']);

    $this->actingAs($sales)
        ->get(route('master-data.index'))
        ->assertForbidden();
});

test('비로그인 사용자는 마스터 허브 접근 시 로그인으로 이동한다', function () {
    $this->get(route('master-data.index'))
        ->assertRedirect(route('login'));
});
