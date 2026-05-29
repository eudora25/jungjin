<?php

use App\Models\Pharmacy;
use App\Models\User;

beforeEach(function () {
    // MT-8: 공유 마스터(약국) 직접 쓰기는 platform 전용. pharma 는 변경요청을 사용한다.
    $this->platform = User::factory()->create(['role' => 'platform']);
    $this->admin = User::factory()->create(['role' => 'pharma']);
    $this->sales = User::factory()->create(['role' => 'cso']);
});

test('admin(pharma) 은 약국 목록을 조회할 수 있다', function () {
    Pharmacy::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get(route('pharmacies.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Clients/Pharmacies/Index')
            ->where('pharmacies.total', 3)
        );
});

test('pharma·cso 는 약국 목록을 조회할 수는 있지만 등록 버튼은 안 보인다', function () {
    foreach ([$this->admin, $this->sales] as $user) {
        $this->actingAs($user)
            ->get(route('pharmacies.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('can.create', false)
            );
    }
});

test('platform 은 약국을 직접 등록한다', function () {
    $this->actingAs($this->platform)
        ->post(route('pharmacies.store'), [
            'pharmacy_name' => '테스트약국',
            'business_registration_number' => '111-22-33333',
            'status' => 'active',
        ])
        ->assertRedirect();

    expect(Pharmacy::where('pharmacy_name', '테스트약국')->exists())->toBeTrue();
});

test('pharma 는 약국을 직접 등록할 수 없다(변경요청 사용)', function () {
    $this->actingAs($this->admin)
        ->post(route('pharmacies.store'), ['pharmacy_name' => '직접등록불가'])
        ->assertForbidden();

    expect(Pharmacy::where('pharmacy_name', '직접등록불가')->exists())->toBeFalse();
});

test('cso 는 약국을 등록할 수 없다', function () {
    $this->actingAs($this->sales)
        ->post(route('pharmacies.store'), ['pharmacy_name' => '미인가약국'])
        ->assertForbidden();
});

test('약국명은 필수', function () {
    $this->actingAs($this->platform)
        ->post(route('pharmacies.store'), [])
        ->assertSessionHasErrors('pharmacy_name');
});

test('사업자등록번호 유일성 검증', function () {
    Pharmacy::factory()->create(['business_registration_number' => '999-99-99999']);

    $this->actingAs($this->platform)
        ->post(route('pharmacies.store'), [
            'pharmacy_name' => '중복약국',
            'business_registration_number' => '999-99-99999',
        ])
        ->assertSessionHasErrors('business_registration_number');
});

test('platform 이 약국을 수정한다', function () {
    $pharmacy = Pharmacy::factory()->create(['pharmacy_name' => '원래약국']);

    $this->actingAs($this->platform)
        ->put(route('pharmacies.update', $pharmacy), [
            'pharmacy_name' => '수정된약국',
            'status' => 'inactive',
        ])
        ->assertRedirect();

    $pharmacy->refresh();
    expect($pharmacy->pharmacy_name)->toBe('수정된약국')
        ->and($pharmacy->status)->toBe('inactive');
});

test('pharma 는 약국을 직접 수정할 수 없다', function () {
    $pharmacy = Pharmacy::factory()->create(['pharmacy_name' => '원래약국']);

    $this->actingAs($this->admin)
        ->put(route('pharmacies.update', $pharmacy), ['pharmacy_name' => '몰래수정'])
        ->assertForbidden();
});

test('platform 이 약국을 삭제한다(soft delete)', function () {
    $pharmacy = Pharmacy::factory()->create();

    $this->actingAs($this->platform)
        ->delete(route('pharmacies.destroy', $pharmacy))
        ->assertRedirect(route('pharmacies.index'));

    expect(Pharmacy::find($pharmacy->id))->toBeNull()
        ->and(Pharmacy::withTrashed()->find($pharmacy->id))->not->toBeNull();
});
