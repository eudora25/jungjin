<?php

use App\Models\Hospital;
use App\Models\Pharmacy;
use App\Models\User;

/**
 * GAP-10 MT-6 (마스터 CRUD) — super_admin 의 공유 마스터(약국·병의원) 전역 CRUD.
 */
function superAdmin(): User
{
    return User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
}

// ---- 약국 ----

test('super_admin 은 약국을 등록한다', function () {
    $this->actingAs(superAdmin())
        ->post(route('platform.pharmacies.store'), ['pharmacy_name' => '플랫폼약국'])
        ->assertRedirect();

    $this->assertDatabaseHas('pharmacies', ['pharmacy_name' => '플랫폼약국']);
});

test('super_admin 은 약국을 수정/삭제한다', function () {
    $pharmacy = Pharmacy::factory()->create(['pharmacy_name' => '원래약국']);
    $super = superAdmin();

    $this->actingAs($super)
        ->put(route('platform.pharmacies.update', $pharmacy), ['pharmacy_name' => '수정약국'])
        ->assertRedirect();
    $this->assertDatabaseHas('pharmacies', ['id' => $pharmacy->id, 'pharmacy_name' => '수정약국']);

    $this->actingAs($super)
        ->delete(route('platform.pharmacies.destroy', $pharmacy))
        ->assertRedirect(route('platform.pharmacies.index'));
    $this->assertSoftDeleted('pharmacies', ['id' => $pharmacy->id]);
});

test('약국 등록 시 약국명은 필수', function () {
    $this->actingAs(superAdmin())
        ->post(route('platform.pharmacies.store'), ['pharmacy_name' => ''])
        ->assertSessionHasErrors('pharmacy_name');
});

// ---- 병의원 ----

test('super_admin 은 병의원을 등록한다', function () {
    $this->actingAs(superAdmin())
        ->post(route('platform.hospitals.store'), ['hospital_name' => '플랫폼병원', 'hospital_type' => 'clinic'])
        ->assertRedirect();

    $this->assertDatabaseHas('hospitals', ['hospital_name' => '플랫폼병원', 'hospital_type' => 'clinic']);
});

test('super_admin 은 병의원을 수정한다', function () {
    $hospital = Hospital::factory()->create(['hospital_name' => '원래병원']);

    $this->actingAs(superAdmin())
        ->put(route('platform.hospitals.update', $hospital), ['hospital_name' => '수정병원'])
        ->assertRedirect();

    $this->assertDatabaseHas('hospitals', ['id' => $hospital->id, 'hospital_name' => '수정병원']);
});

// ---- 권한 차단 ----

test('admin 은 플랫폼 약국/병의원 CRUD 에 접근할 수 없다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);

    $this->actingAs($admin)->get(route('platform.pharmacies.create'))->assertForbidden();
    $this->actingAs($admin)->post(route('platform.pharmacies.store'), ['pharmacy_name' => 'X'])->assertForbidden();
    $this->actingAs($admin)->get(route('platform.hospitals.create'))->assertForbidden();
});
