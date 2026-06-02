<?php

use App\Models\Hospital;
use App\Models\Pharmacy;
use App\Models\User;

/**
 * 사업자등록번호 이력 — 병의원·약국의 번호 변경 이력 보관 + 옛 번호 검색. (GAP-10)
 */
function platformAdmin(): User
{
    return User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
}

test('병의원 등록 시 현재 사업자번호가 이력에 시드된다', function () {
    $this->actingAs(platformAdmin())
        ->post(route('platform.hospitals.store'), [
            'hospital_name' => '시드의원',
            'business_registration_number' => '111-11-11111',
            'status' => 'active',
        ])
        ->assertRedirect();

    $hospital = Hospital::where('hospital_name', '시드의원')->firstOrFail();

    expect($hospital->numberHistories()->count())->toBe(1);
    $current = $hospital->currentNumberHistory();
    expect($current)->not->toBeNull()
        // 하이픈을 입력해도 숫자만 저장
        ->and($current->business_registration_number)->toBe('1111111111')
        ->and($hospital->business_registration_number)->toBe('1111111111')
        ->and($current->is_current)->toBeTrue();
});

test('번호 없이 등록하면 이력이 생기지 않는다', function () {
    $this->actingAs(platformAdmin())
        ->post(route('platform.hospitals.store'), [
            'hospital_name' => '무번호의원',
            'status' => 'active',
        ])
        ->assertRedirect();

    $hospital = Hospital::where('hospital_name', '무번호의원')->firstOrFail();

    expect($hospital->numberHistories()->count())->toBe(0);
});

test('사업자번호 변경 시 과거 번호는 마감되고 새 번호가 현재가 된다', function () {
    $admin = platformAdmin();
    $hospital = Hospital::factory()->create([
        'hospital_name' => '재등록의원',
        'business_registration_number' => '123-45-67890',
    ]);
    $hospital->seedBusinessNumberHistory($admin->id);

    $this->actingAs($admin)
        ->post(route('platform.hospitals.business-number.change', $hospital), [
            'new_business_registration_number' => '234-56-78901',
            'valid_from' => '2026-06-01',
            'previous_valid_to' => '2026-05-31',
            'reason' => '폐업 후 재등록',
        ])
        ->assertRedirect(route('platform.hospitals.show', $hospital));

    $hospital->refresh();

    // 본 컬럼은 새 번호로 갱신 (숫자만 저장)
    expect($hospital->business_registration_number)->toBe('2345678901');

    // 과거 번호는 마감(is_current=false, valid_to 설정)
    $old = $hospital->numberHistories()->where('business_registration_number', '1234567890')->first();
    expect($old->is_current)->toBeFalse()
        ->and($old->valid_to->toDateString())->toBe('2026-05-31');

    // 새 번호가 현재
    $new = $hospital->currentNumberHistory();
    expect($new->business_registration_number)->toBe('2345678901')
        ->and($new->reason)->toBe('폐업 후 재등록')
        ->and($new->valid_from->toDateString())->toBe('2026-06-01');
});

test('과거(폐업) 번호로 검색하면 현재 사용 중인 병의원이 목록에 나온다', function () {
    $admin = platformAdmin();
    $hospital = Hospital::factory()->create([
        'hospital_name' => '재등록의원',
        'business_registration_number' => '123-45-67890',
    ]);
    $hospital->seedBusinessNumberHistory($admin->id);
    $hospital->changeBusinessNumber('234-56-78901', ['reason' => '폐업 후 재등록'], $admin->id);

    // 옛 번호를 하이픈 포함해 입력해도 → 현재 번호 234 를 쓰는 재등록의원이 나와야 함
    $this->actingAs($admin)
        ->get(route('platform.hospitals.index', ['search' => '123-45-67890']))
        ->assertInertia(fn ($page) => $page
            ->has('hospitals.data', 1)
            ->where('hospitals.data.0.hospital_name', '재등록의원')
            ->where('hospitals.data.0.business_registration_number', '2345678901')
            ->where('hospitals.data.0.matched_old_numbers.0', '1234567890')
        );

    // 하이픈 없이 숫자만 입력해도 동일하게 검색된다
    $this->actingAs($admin)
        ->get(route('platform.hospitals.index', ['search' => '1234567890']))
        ->assertInertia(fn ($page) => $page->has('hospitals.data', 1)
            ->where('hospitals.data.0.hospital_name', '재등록의원'));
});

test('약국도 사업자번호 변경·과거번호 검색이 동작한다', function () {
    $admin = platformAdmin();
    $pharmacy = Pharmacy::factory()->create([
        'pharmacy_name' => '재등록약국',
        'business_registration_number' => '321-54-09876',
    ]);
    $pharmacy->seedBusinessNumberHistory($admin->id);

    $this->actingAs($admin)
        ->post(route('platform.pharmacies.business-number.change', $pharmacy), [
            'new_business_registration_number' => '432-65-10987',
            'reason' => '대표 변경',
        ])
        ->assertRedirect(route('platform.pharmacies.show', $pharmacy));

    expect($pharmacy->fresh()->business_registration_number)->toBe('4326510987');

    $this->actingAs($admin)
        ->get(route('platform.pharmacies.index', ['search' => '321-54-09876']))
        ->assertInertia(fn ($page) => $page
            ->has('pharmacies.data', 1)
            ->where('pharmacies.data.0.pharmacy_name', '재등록약국')
            ->where('pharmacies.data.0.business_registration_number', '4326510987')
        );
});

test('새 번호 누락·현재와 동일 번호는 거부된다', function () {
    $admin = platformAdmin();
    $hospital = Hospital::factory()->create(['business_registration_number' => '123-45-67890']);
    $hospital->seedBusinessNumberHistory($admin->id);

    $this->actingAs($admin)
        ->post(route('platform.hospitals.business-number.change', $hospital), ['new_business_registration_number' => ''])
        ->assertSessionHasErrors('new_business_registration_number');

    $this->actingAs($admin)
        ->post(route('platform.hospitals.business-number.change', $hospital), ['new_business_registration_number' => '123-45-67890'])
        ->assertSessionHasErrors('new_business_registration_number');
});

test('pharma·cso 는 사업자번호를 변경할 수 없다', function () {
    $hospital = Hospital::factory()->create(['business_registration_number' => '123-45-67890']);
    $hospital->seedBusinessNumberHistory();

    foreach (['pharma', 'cso'] as $role) {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user)
            ->post(route('platform.hospitals.business-number.change', $hospital), ['new_business_registration_number' => '999-99-99999'])
            ->assertForbidden();
    }
});
