<?php

use App\Models\Company;
use App\Models\Settlement;
use App\Models\User;

test('거래처 상세에서 최근 정산 6건이 period_month 내림차순으로 노출된다', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $company = Company::factory()->create();

    // 7건 생성 → 최근 6건만 노출되어야 함
    foreach (['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07'] as $m) {
        Settlement::factory()->create([
            'company_id' => $company->id,
            'period_month' => $m,
            'settlement_no' => Settlement::settlementNoFor($m, $company->id),
            'status' => Settlement::STATUS_DRAFT,
        ]);
    }

    $res = $this->actingAs($admin)->get(route('companies.show', $company));
    $res->assertOk();

    $res->assertInertia(fn ($page) => $page
        ->component('Companies/Show')
        ->has('recentSettlements', 6)
        ->where('recentSettlements.0.period_month', '2026-07')
        ->where('recentSettlements.5.period_month', '2026-02')
    );
});

