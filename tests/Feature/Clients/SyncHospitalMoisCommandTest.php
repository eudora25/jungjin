<?php

use App\Jobs\SyncHospitalMoisJob;
use App\Models\Hospital;
use App\Models\HospitalMoisSync;
use App\Services\Clients\HospitalMoisSyncService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

function fakeMoisOk(): void
{
    Http::fake(['apis.data.go.kr/*' => Http::response([
        'response' => [
            'header' => ['resultCode' => '0', 'resultMsg' => 'NORMAL SERVICE.'],
            'body' => [
                'totalCount' => 1, 'pageNo' => 1, 'numOfRows' => 100,
                'items' => ['item' => [[
                    'MNG_NO' => 'CMD-1', 'BPLC_NM' => '명령의원', 'SALS_STTS_NM' => '영업/정상',
                    'MDLCR_INST_BTP_NM' => '의원', 'LCPMT_YMD' => '2021-05-05',
                    'LAST_MDFCN_PNT' => '20260604010000', 'DAT_UPDT_SE' => 'I',
                ]]],
            ],
        ],
    ])]);
}

beforeEach(function () {
    config()->set('mois.api_key', 'TEST_KEY');
    config()->set('mois.page_sleep_ms', 0);
    config()->set('mois.retry', ['times' => 1, 'sleep' => 0]);
});

test('hospitals:sync-mois --dry-run 은 DB 를 변경하지 않고 이력만 남긴다', function () {
    fakeMoisOk();

    $this->artisan('hospitals:sync-mois', ['--svc' => ['clinics'], '--dry-run' => true])
        ->assertExitCode(0);

    expect(Hospital::where('hospital_code', 'CMD-1')->exists())->toBeFalse();

    $sync = HospitalMoisSync::latest('id')->first();
    expect($sync->status)->toBe(HospitalMoisSync::STATUS_COMPLETED);
    expect($sync->trigger)->toBe(HospitalMoisSync::TRIGGER_MANUAL);
    expect($sync->params['dry_run'])->toBeTrue();
});

test('hospitals:sync-mois 는 지정 업종만 동기화하고 INSERT 한다', function () {
    fakeMoisOk();

    $this->artisan('hospitals:sync-mois', ['--svc' => ['clinics']])->assertExitCode(0);

    expect(Hospital::where('hospital_code', 'CMD-1')->value('hospital_name'))->toBe('명령의원');

    $sync = HospitalMoisSync::latest('id')->first();
    expect($sync->params['services'])->toBe(['clinics']);
    expect(array_keys($sync->report))->toBe(['clinics']);
});

test('--queue 옵션은 동기화를 큐로 디스패치한다', function () {
    Bus::fake();

    $this->artisan('hospitals:sync-mois', ['--svc' => ['clinics'], '--queue' => true])
        ->assertExitCode(0);

    Bus::assertDispatched(SyncHospitalMoisJob::class);
    // 큐 위임 시 인라인 실행하지 않음
    expect(HospitalMoisSync::count())->toBe(0);
});

test('SyncHospitalMoisJob 은 서비스에 위임해 이력을 남긴다', function () {
    fakeMoisOk();

    (new SyncHospitalMoisJob(['services' => ['clinics'], 'trigger' => HospitalMoisSync::TRIGGER_SCHEDULE]))
        ->handle(app(HospitalMoisSyncService::class));

    $sync = HospitalMoisSync::latest('id')->first();
    expect($sync)->not->toBeNull();
    expect($sync->trigger)->toBe(HospitalMoisSync::TRIGGER_SCHEDULE);
    expect($sync->status)->toBe(HospitalMoisSync::STATUS_COMPLETED);
    expect(Hospital::where('hospital_code', 'CMD-1')->exists())->toBeTrue();
});
