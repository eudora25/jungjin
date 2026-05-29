<?php

use App\Models\Company;
use App\Models\Settlement;
use App\Models\User;

test('관리자만 정산 상태 전이를 할 수 있다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sales = User::factory()->create(['role' => 'sales']);
    $company = Company::factory()->create();

    $this->actingAs($admin)
        ->post(route('settlements.store'), [
            'company_id' => $company->id,
            'period_month' => '2026-04',
        ])
        ->assertRedirect();

    $settlement = Settlement::query()->where('company_id', $company->id)->where('period_month', '2026-04')->firstOrFail();

    $this->actingAs($sales)->post(route('settlements.confirm', $settlement))->assertForbidden();
    $this->actingAs($sales)->post(route('settlements.pay', $settlement))->assertForbidden();
    $this->actingAs($sales)->post(route('settlements.cancel', $settlement))->assertForbidden();
    $this->actingAs($sales)->post(route('settlements.recalculate', $settlement))->assertForbidden();
});

test('정산: draft→confirmed→paid 상태 전이가 동작한다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $company = Company::factory()->create();

    $this->actingAs($admin)
        ->post(route('settlements.store'), [
            'company_id' => $company->id,
            'period_month' => '2026-04',
        ])
        ->assertRedirect();

    $settlement = Settlement::query()->where('company_id', $company->id)->where('period_month', '2026-04')->firstOrFail();
    expect($settlement->status)->toBe(Settlement::STATUS_DRAFT);

    $this->actingAs($admin)->post(route('settlements.confirm', $settlement))->assertRedirect();
    $settlement->refresh();
    expect($settlement->status)->toBe(Settlement::STATUS_CONFIRMED)
        ->and($settlement->confirmed_by)->toBe($admin->id)
        ->and($settlement->confirmed_at)->not->toBeNull();

    $this->actingAs($admin)
        ->post(route('settlements.pay', $settlement), ['paid_on' => now()->toDateString()])
        ->assertRedirect();
    $settlement->refresh();
    expect($settlement->status)->toBe(Settlement::STATUS_PAID)
        ->and($settlement->paid_by)->toBe($admin->id)
        ->and($settlement->paid_at)->not->toBeNull()
        ->and($settlement->paid_on?->toDateString())->toBe(now()->toDateString());
});

test('확정된 정산은 재계산할 수 없다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $company = Company::factory()->create();

    $this->actingAs($admin)
        ->post(route('settlements.store'), [
            'company_id' => $company->id,
            'period_month' => '2026-04',
        ])
        ->assertRedirect();

    $settlement = Settlement::query()->where('company_id', $company->id)->where('period_month', '2026-04')->firstOrFail();

    $this->actingAs($admin)->post(route('settlements.confirm', $settlement))->assertRedirect();

    $this->actingAs($admin)->post(route('settlements.recalculate', $settlement))->assertForbidden();
});

