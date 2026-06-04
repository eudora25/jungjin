<?php

use App\Models\HospitalMoisCursor;
use App\Models\HospitalMoisSync;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;

test('동기화 이력을 생성하고 운영자 관계·report 캐스트가 동작한다', function () {
    $admin = User::factory()->create(['role' => 'platform']);

    $sync = HospitalMoisSync::create([
        'created_by' => $admin->id,
        'trigger' => HospitalMoisSync::TRIGGER_MANUAL,
        'params' => ['since' => '20260601', 'services' => ['clinics']],
        'status' => HospitalMoisSync::STATUS_COMPLETED,
        'report' => ['clinics' => ['fetched' => 10, 'inserted' => 3, 'updated' => 2, 'closed' => 1, 'skipped' => 4, 'failed' => 0]],
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    $fresh = $sync->fresh();
    expect($fresh->params)->toBeArray()->toHaveKey('since');
    expect($fresh->report['clinics']['inserted'])->toBe(3);
    expect($fresh->started_at)->toBeInstanceOf(CarbonInterface::class);
    expect($fresh->creator->is($admin))->toBeTrue();
});

test('스케줄 트리거는 created_by 없이 생성된다', function () {
    $sync = HospitalMoisSync::create([
        'trigger' => HospitalMoisSync::TRIGGER_SCHEDULE,
        'status' => HospitalMoisSync::STATUS_PENDING,
    ]);

    expect($sync->created_by)->toBeNull();
    expect($sync->creator)->toBeNull();
});

test('업종 커서는 api_id 가 유니크하고 마지막 동기화와 연결된다', function () {
    $sync = HospitalMoisSync::create([
        'trigger' => HospitalMoisSync::TRIGGER_SCHEDULE,
        'status' => HospitalMoisSync::STATUS_COMPLETED,
    ]);

    $cursor = HospitalMoisCursor::create([
        'api_id' => '15154874',
        'last_synced_at' => '20260604043000',
        'last_sync_id' => $sync->id,
    ]);

    expect($cursor->lastSync->is($sync))->toBeTrue();

    HospitalMoisCursor::create(['api_id' => '15154874']);
})->throws(QueryException::class);
