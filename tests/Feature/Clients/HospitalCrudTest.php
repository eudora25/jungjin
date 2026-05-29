<?php

use App\Models\Hospital;
use App\Models\User;

beforeEach(function () {
    // MT-8: 공유 마스터(병의원) 직접 쓰기는 platform 전용. pharma 는 변경요청을 사용한다.
    $this->platform = User::factory()->create(['role' => 'platform']);
    $this->admin = User::factory()->create(['role' => 'pharma']);
    $this->sales = User::factory()->create(['role' => 'cso']);
});

test('admin(pharma) 은 병의원 목록을 조회할 수 있다', function () {
    Hospital::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get(route('hospitals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Clients/Hospitals/Index')
            ->where('hospitals.total', 3)
        );
});

test('platform 이 병의원을 직접 등록한다', function () {
    $this->actingAs($this->platform)
        ->post(route('hospitals.store'), [
            'hospital_name' => '테스트병원',
            'hospital_type' => 'clinic',
            'status' => 'active',
        ])
        ->assertRedirect();

    expect(Hospital::where('hospital_name', '테스트병원')->exists())->toBeTrue();
});

test('유효하지 않은 병의원 유형은 거부', function () {
    $this->actingAs($this->platform)
        ->post(route('hospitals.store'), [
            'hospital_name' => '이상병원',
            'hospital_type' => 'invalid_type',
        ])
        ->assertSessionHasErrors('hospital_type');
});

test('pharma 는 병의원을 직접 등록할 수 없다(변경요청 사용)', function () {
    $this->actingAs($this->admin)
        ->post(route('hospitals.store'), ['hospital_name' => '직접등록불가'])
        ->assertForbidden();

    expect(Hospital::where('hospital_name', '직접등록불가')->exists())->toBeFalse();
});

test('cso 는 병의원을 등록할 수 없다', function () {
    $this->actingAs($this->sales)
        ->post(route('hospitals.store'), ['hospital_name' => '미인가병원'])
        ->assertForbidden();
});

test('유형 필터링', function () {
    Hospital::factory()->create(['hospital_type' => 'dental']);
    Hospital::factory()->create(['hospital_type' => 'clinic']);

    $this->actingAs($this->admin)
        ->get(route('hospitals.index', ['type' => 'dental']))
        ->assertInertia(fn ($page) => $page->where('hospitals.total', 1));
});

test('platform 이 병의원을 수정한다', function () {
    $hospital = Hospital::factory()->create(['hospital_name' => '원래병원']);

    $this->actingAs($this->platform)
        ->put(route('hospitals.update', $hospital), [
            'hospital_name' => '수정된병원',
            'hospital_type' => 'hospital',
            'status' => 'active',
        ])
        ->assertRedirect();

    expect($hospital->fresh()->hospital_name)->toBe('수정된병원');
});

test('pharma 는 병의원을 직접 수정할 수 없다', function () {
    $hospital = Hospital::factory()->create(['hospital_name' => '원래병원']);

    $this->actingAs($this->admin)
        ->put(route('hospitals.update', $hospital), [
            'hospital_name' => '몰래수정',
            'hospital_type' => 'hospital',
        ])
        ->assertForbidden();
});

test('platform 이 병의원을 삭제한다(soft delete)', function () {
    $hospital = Hospital::factory()->create();

    $this->actingAs($this->platform)
        ->delete(route('hospitals.destroy', $hospital))
        ->assertRedirect(route('hospitals.index'));

    expect(Hospital::find($hospital->id))->toBeNull();
});
