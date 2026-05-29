<?php

use App\Models\Performance;
use App\Models\User;

/**
 * P2-8 영업사원 대시보드 보강 테스트
 *  - P2-8-1: 최근 12개월 본인 승인 실적 월별 차트
 *  - P2-8-2: 최근 반려 실적 (최대 5건)
 *  - P2-8-3: 제출 대기(draft) 카운트
 */
function makePerf(User $user, string $status, string $date, int $qty, float $unit, array $extra = []): Performance
{
    return Performance::factory()->create(array_merge([
        'created_by' => $user->id,
        'status' => $status,
        'performance_date' => $date,
        'quantity' => $qty,
        'unit_price' => $unit,
        'commission_rate' => null,
    ], $extra));
}

test('myMonthlyChart 는 최근 12개월 라벨과 승인 실적 월별 합계를 반환한다', function () {
    $sales = User::factory()->create(['role' => 'sales']);

    $thisMonth = now()->format('Y-m');
    $lastMonth = now()->subMonth()->format('Y-m');

    // 이번달 승인 2건 → 10*1000 + 5*2000 = 20000
    makePerf($sales, Performance::STATUS_APPROVED, now()->startOfMonth()->toDateString(), 10, 1000);
    makePerf($sales, Performance::STATUS_APPROVED, now()->startOfMonth()->addDay()->toDateString(), 5, 2000);
    // 이번달 미승인(반려/draft) → 차트 제외
    makePerf($sales, Performance::STATUS_REJECTED, now()->startOfMonth()->toDateString(), 100, 1000);
    makePerf($sales, Performance::STATUS_DRAFT, now()->startOfMonth()->toDateString(), 100, 1000);
    // 지난달 승인 1건 → 1*5000 = 5000
    makePerf($sales, Performance::STATUS_APPROVED, now()->subMonth()->startOfMonth()->toDateString(), 1, 5000);

    // 다른 영업사원의 승인 실적 → 제외
    $other = User::factory()->create(['role' => 'sales']);
    makePerf($other, Performance::STATUS_APPROVED, now()->startOfMonth()->toDateString(), 99, 9999);

    $this->actingAs($sales)
        ->get(route('sales.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('myMonthlyChart.labels', 12)
            ->has('myMonthlyChart.data', 12)
            ->where('myMonthlyChart.labels.11', $thisMonth)
            ->where('myMonthlyChart.labels.10', $lastMonth)
            ->where('myMonthlyChart.data.11', 20000)
            ->where('myMonthlyChart.data.10', 5000)
            ->where('myMonthlyChart.data.0', 0)
        );
});

test('recentRejected 는 본인 반려 실적을 최신순 최대 5건, 사유와 함께 반환한다', function () {
    $sales = User::factory()->create(['role' => 'sales']);

    // 반려 6건 생성 (updated_at 으로 정렬 검증)
    for ($i = 0; $i < 6; $i++) {
        $p = makePerf($sales, Performance::STATUS_REJECTED, now()->toDateString(), 1, 1000, [
            'rejected_reason' => "사유 {$i}",
        ]);
        $p->forceFill(['updated_at' => now()->subMinutes(6 - $i)])->save();
    }

    // 다른 사용자 반려 → 제외
    $other = User::factory()->create(['role' => 'sales']);
    makePerf($other, Performance::STATUS_REJECTED, now()->toDateString(), 1, 1000, ['rejected_reason' => '남의 사유']);

    $this->actingAs($sales)
        ->get(route('sales.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('recentRejected', 5)
            // 가장 최근(i=5) 이 맨 위
            ->where('recentRejected.0.rejected_reason', '사유 5')
            ->where('recentRejected.4.rejected_reason', '사유 1')
        );
});

test('statusCounts.draft 는 본인 임시저장 실적 건수를 반환한다', function () {
    $sales = User::factory()->create(['role' => 'sales']);

    makePerf($sales, Performance::STATUS_DRAFT, now()->toDateString(), 1, 1000);
    makePerf($sales, Performance::STATUS_DRAFT, now()->toDateString(), 1, 1000);
    makePerf($sales, Performance::STATUS_APPROVED, now()->toDateString(), 1, 1000);

    // 다른 사용자 draft → 제외
    $other = User::factory()->create(['role' => 'sales']);
    makePerf($other, Performance::STATUS_DRAFT, now()->toDateString(), 1, 1000);

    $this->actingAs($sales)
        ->get(route('sales.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('statusCounts.draft', 2));
});
