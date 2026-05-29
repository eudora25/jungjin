<?php

use App\Models\Hospital;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;

/**
 * GAP-10 MT-6 — 플랫폼 전역 마스터/사용자 목록 (super_admin).
 */
test('super_admin 은 전 제약사 의약품을 전역 조회한다', function () {
    $super = User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]);

    $hanmi = Tenant::factory()->create(['name' => '한미']);
    $ckd = Tenant::factory()->create(['name' => '종근당']);
    Product::factory()->create(['tenant_id' => $hanmi->id, 'product_name' => '한미제품']);
    Product::factory()->create(['tenant_id' => $ckd->id, 'product_name' => '종근당제품']);

    $this->actingAs($super)
        ->get(route('platform.products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Platform/Products/Index')
            ->has('products.data') // 두 제약사 제품이 모두 보임
        );
});

test('super_admin 은 전역 사용자 목록을 조회한다 (제약사 칸 포함)', function () {
    $super = User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]);
    $tenant = Tenant::factory()->create(['name' => '한미']);
    User::factory()->create(['role' => 'admin', 'tenant_id' => $tenant->id]);

    $this->actingAs($super)
        ->get(route('platform.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Platform/Users/Index')->has('users.data'));
});

test('super_admin 은 공유 병의원·약국을 조회한다', function () {
    $super = User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]);
    Hospital::factory()->create();
    Pharmacy::factory()->create();

    $this->actingAs($super)->get(route('platform.hospitals.index'))->assertOk();
    $this->actingAs($super)->get(route('platform.pharmacies.index'))->assertOk();
});

test('admin·sales 는 플랫폼 영역에 접근할 수 없다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sales = User::factory()->create(['role' => 'sales']);

    foreach (['platform.products.index', 'platform.hospitals.index', 'platform.pharmacies.index', 'platform.users.index'] as $routeName) {
        $this->actingAs($admin)->get(route($routeName))->assertForbidden();
        $this->actingAs($sales)->get(route($routeName))->assertForbidden();
    }
});
