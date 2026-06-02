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
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);

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
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $tenant = Tenant::factory()->create(['name' => '한미']);
    User::factory()->create(['role' => 'pharma', 'tenant_id' => $tenant->id]);

    $this->actingAs($super)
        ->get(route('platform.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Platform/Users/Index')->has('users.data'));
});

test('super_admin 은 공유 병의원·약국을 조회한다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    Hospital::factory()->create();
    Pharmacy::factory()->create();

    $this->actingAs($super)->get(route('platform.hospitals.index'))->assertOk();
    $this->actingAs($super)->get(route('platform.pharmacies.index'))->assertOk();
});

test('약국 목록은 지역·대표·전화·주소 컬럼을 포함한다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    Pharmacy::factory()->create([
        'pharmacy_name' => '테스트약국',
        'address' => '서울특별시 종로구 대학로 101 (연건동)',
        'representative_name' => '김약사',
        'landline_phone' => '02-333-4444',
        'mobile_phone' => null,
        'business_registration_number' => null,
    ]);

    $this->actingAs($super)
        ->get(route('platform.pharmacies.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Platform/Pharmacies/Index')
            ->where('pharmacies.data.0.region', '서울특별시')
            ->where('pharmacies.data.0.representative_name', '김약사')
            ->where('pharmacies.data.0.phone', '02-333-4444')
            ->where('pharmacies.data.0.address', '서울특별시 종로구 대학로 101 (연건동)')
            ->where('pharmacies.data.0.business_registration_number', null)
        );
});

test('약국 전화번호는 일반전화가 없으면 휴대전화를 보여준다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    Pharmacy::factory()->create([
        'pharmacy_name' => '모바일약국',
        'landline_phone' => null,
        'mobile_phone' => '010-1234-5678',
    ]);

    $this->actingAs($super)
        ->get(route('platform.pharmacies.index'))
        ->assertInertia(fn ($page) => $page->where('pharmacies.data.0.phone', '010-1234-5678'));
});

test('약국 목록을 지역(시도)으로 필터링한다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    Pharmacy::factory()->create(['pharmacy_name' => '서울약국', 'address' => '서울특별시 종로구 대학로 1']);
    Pharmacy::factory()->create(['pharmacy_name' => '부산약국', 'address' => '부산광역시 해운대구 1']);

    $this->actingAs($super)
        ->get(route('platform.pharmacies.index', ['region' => '서울특별시']))
        ->assertInertia(fn ($page) => $page
            ->has('pharmacies.data', 1)
            ->where('pharmacies.data.0.pharmacy_name', '서울약국')
            ->where('filters.region', '서울특별시')
        );
});

test('약국 목록의 잘못된 지역 값은 무시한다', function () {
    $super = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    Pharmacy::factory()->count(2)->create();

    $this->actingAs($super)
        ->get(route('platform.pharmacies.index', ['region' => '없는도']))
        ->assertInertia(fn ($page) => $page
            ->has('pharmacies.data', 2)
            ->where('filters.region', '')
        );
});

test('admin·sales 는 플랫폼 영역에 접근할 수 없다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);
    $sales = User::factory()->create(['role' => 'cso']);

    foreach (['platform.products.index', 'platform.hospitals.index', 'platform.pharmacies.index', 'platform.users.index'] as $routeName) {
        $this->actingAs($admin)->get(route($routeName))->assertForbidden();
        $this->actingAs($sales)->get(route($routeName))->assertForbidden();
    }
});
