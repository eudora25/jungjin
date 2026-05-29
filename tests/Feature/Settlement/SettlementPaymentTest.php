<?php

use App\Models\Company;
use App\Models\Settlement;
use App\Models\User;

// ── 헬퍼 ───────────────────────────────────────────────────────────────────────

function confirmedSettlement(User $admin, ?Company $company = null): Settlement
{
    $company ??= Company::factory()->create();

    test()->actingAs($admin)
        ->post(route('settlements.store'), [
            'company_id' => $company->id,
            'period_month' => '2026-04',
        ])
        ->assertRedirect();

    $settlement = Settlement::query()
        ->where('company_id', $company->id)
        ->where('period_month', '2026-04')
        ->firstOrFail();

    test()->actingAs($admin)
        ->post(route('settlements.confirm', $settlement))
        ->assertRedirect();

    return $settlement->fresh();
}

// ── 지급 처리 (pay) ────────────────────────────────────────────────────────────

test('지급 처리 시 paid_on 이 필수다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $settlement = confirmedSettlement($admin);

    $this->actingAs($admin)
        ->post(route('settlements.pay', $settlement), [])
        ->assertSessionHasErrors('paid_on');

    expect($settlement->fresh()->status)->toBe(Settlement::STATUS_CONFIRMED);
});

test('미래 날짜는 paid_on 으로 사용할 수 없다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $settlement = confirmedSettlement($admin);

    $this->actingAs($admin)
        ->post(route('settlements.pay', $settlement), [
            'paid_on' => now()->addDay()->toDateString(),
        ])
        ->assertSessionHasErrors('paid_on');
});

test('지급 수단·Batch·메모가 함께 저장된다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $settlement = confirmedSettlement($admin);

    $this->actingAs($admin)
        ->post(route('settlements.pay', $settlement), [
            'paid_on' => '2026-04-30',
            'payment_method' => 'bank_transfer',
            'payment_batch_no' => '2026-04-BATCH-001',
            'payment_note' => '월말 일괄 송금',
        ])
        ->assertRedirect();

    $settlement->refresh();
    expect($settlement->status)->toBe(Settlement::STATUS_PAID)
        ->and($settlement->paid_on?->toDateString())->toBe('2026-04-30')
        ->and($settlement->payment_method)->toBe('bank_transfer')
        ->and($settlement->payment_batch_no)->toBe('2026-04-BATCH-001')
        ->and($settlement->payment_note)->toBe('월말 일괄 송금');
});

test('잘못된 payment_method 는 거부된다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $settlement = confirmedSettlement($admin);

    $this->actingAs($admin)
        ->post(route('settlements.pay', $settlement), [
            'paid_on' => now()->toDateString(),
            'payment_method' => 'paypal',
        ])
        ->assertSessionHasErrors('payment_method');
});

test('영업사원은 지급 처리를 할 수 없다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $sales = User::factory()->create(['role' => 'sales']);
    $settlement = confirmedSettlement($admin);

    $this->actingAs($sales)
        ->post(route('settlements.pay', $settlement), [
            'paid_on' => now()->toDateString(),
        ])
        ->assertForbidden();
});

test('draft 상태에서는 지급 처리할 수 없다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $company = Company::factory()->create();

    $this->actingAs($admin)
        ->post(route('settlements.store'), [
            'company_id' => $company->id,
            'period_month' => '2026-04',
        ]);

    $settlement = Settlement::query()
        ->where('company_id', $company->id)
        ->where('period_month', '2026-04')
        ->firstOrFail();

    // draft 상태 — Policy 에서 차단
    $this->actingAs($admin)
        ->post(route('settlements.pay', $settlement), [
            'paid_on' => now()->toDateString(),
        ])
        ->assertForbidden();
});

// ── 정산 목록 Batch 필터 ───────────────────────────────────────────────────────

test('payment_batch_no 필터로 정산 목록을 조회할 수 있다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $sA = confirmedSettlement($admin, $companyA);
    $sB = confirmedSettlement($admin, $companyB);

    $this->actingAs($admin)
        ->post(route('settlements.pay', $sA), [
            'paid_on' => '2026-04-30',
            'payment_batch_no' => 'BATCH-A',
        ]);

    $this->actingAs($admin)
        ->post(route('settlements.pay', $sB), [
            'paid_on' => '2026-04-30',
            'payment_batch_no' => 'BATCH-B',
        ]);

    $response = $this->actingAs($admin)
        ->get(route('settlements.index', ['payment_batch_no' => 'BATCH-A']))
        ->assertOk();

    $items = $response->original->getData()['page']['props']['settlements']['data'];
    expect(count($items))->toBe(1)
        ->and($items[0]['id'])->toBe($sA->id);
});
