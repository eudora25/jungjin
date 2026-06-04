<?php

use App\Models\Hospital;
use App\Models\HospitalMoisCursor;
use App\Models\HospitalMoisSync;
use App\Services\Clients\HospitalMoisSyncService;
use Illuminate\Support\Facades\Http;

/** 골든 좌표(송림의원) — 한반도 범위로 변환됨 */
const MOIS_X = '355092.7765692';
const MOIS_Y = '184193.546203198';

function moisItem(array $overrides = []): array
{
    return array_merge([
        'MNG_NO' => 'C-1',
        'BPLC_NM' => '가나의원',
        'BZSTAT_SE_NM' => '영업/정상',
        'MDLCR_INST_BTP_NM' => '의원',
        'MDEXM_SBJCT_CN_NM' => '내과, 외과',
        'ROAD_NM_ADDR' => '서울 종로구 1',
        'ROAD_NM_ZIP' => '03100',
        'TELNO' => '0211110000',
        'LCPMT_YMD' => '2020-01-01',
        'LAST_MDFCN_PNT' => '20260604010000',
        'DAT_UPDT_SE' => 'I',
    ], $overrides);
}

function moisResponse(array $items): array
{
    return [
        'response' => [
            'header' => ['resultCode' => '0', 'resultMsg' => 'NORMAL SERVICE.'],
            'body' => [
                'totalCount' => count($items),
                'pageNo' => 1,
                'numOfRows' => 100,
                'items' => ['item' => $items],
            ],
        ],
    ];
}

beforeEach(function () {
    config()->set('mois.api_key', 'TEST_KEY');
    config()->set('mois.page_sleep_ms', 0);
    config()->set('mois.retry', ['times' => 1, 'sleep' => 0]);
    $this->service = app(HospitalMoisSyncService::class);
});

test('신규(I)는 좌표 변환과 함께 hospitals 에 INSERT 된다', function () {
    Http::fake(['apis.data.go.kr/*' => Http::response(moisResponse([
        moisItem(['MNG_NO' => 'C-1', 'CRD_INFO_X' => MOIS_X, 'CRD_INFO_Y' => MOIS_Y]),
    ]))]);

    $sync = $this->service->syncAll(['services' => ['clinics'], 'trigger' => HospitalMoisSync::TRIGGER_MANUAL]);

    $h = Hospital::where('hospital_code', 'C-1')->first();
    expect($h)->not->toBeNull();
    expect($h->hospital_name)->toBe('가나의원');
    expect($h->hospital_type)->toBe('clinic');
    expect($h->status)->toBe('active');
    expect($h->opened_on->format('Y-m-d'))->toBe('2020-01-01');
    expect((float) $h->latitude)->toBeGreaterThan(35.0)->toBeLessThan(35.3);

    expect($sync->status)->toBe(HospitalMoisSync::STATUS_COMPLETED);
    expect($sync->report['clinics']['inserted'])->toBe(1);
    expect($sync->report['clinics']['fetched'])->toBe(1);
});

test('변경(U)은 기존 행을 UPDATE 한다', function () {
    Hospital::create(['hospital_code' => 'C-2', 'hospital_name' => '옛이름', 'hospital_type' => 'clinic', 'status' => 'active']);

    Http::fake(['apis.data.go.kr/*' => Http::response(moisResponse([
        moisItem(['MNG_NO' => 'C-2', 'BPLC_NM' => '새이름', 'DAT_UPDT_SE' => 'U']),
    ]))]);

    $sync = $this->service->syncAll(['services' => ['clinics']]);

    expect(Hospital::where('hospital_code', 'C-2')->value('hospital_name'))->toBe('새이름');
    expect($sync->report['clinics']['updated'])->toBe(1);
});

test('폐업(D)은 상태 비활성+폐업일 처리하고 삭제하지 않는다', function () {
    Hospital::create(['hospital_code' => 'C-3', 'hospital_name' => '폐업예정의원', 'hospital_type' => 'clinic', 'status' => 'active']);

    Http::fake(['apis.data.go.kr/*' => Http::response(moisResponse([
        moisItem(['MNG_NO' => 'C-3', 'DAT_UPDT_SE' => 'D', 'CLSBIZ_YMD' => '2026-06-01']),
    ]))]);

    $sync = $this->service->syncAll(['services' => ['clinics']]);

    $h = Hospital::where('hospital_code', 'C-3')->first();
    expect($h)->not->toBeNull();
    expect($h->status)->toBe('inactive');
    expect($h->closed_on->format('Y-m-d'))->toBe('2026-06-01');
    expect($h->trashed())->toBeFalse();
    expect($sync->report['clinics']['closed'])->toBe(1);
});

test('변경 없는 행은 SKIP 한다', function () {
    Hospital::create([
        'hospital_code' => 'C-4', 'hospital_name' => '동일의원', 'hospital_type' => 'clinic',
        'status' => 'active', 'opened_on' => '2020-01-01',
    ]);

    Http::fake(['apis.data.go.kr/*' => Http::response(moisResponse([
        // 좌표·주소 없는 동일값 행 (DAT_UPDT_SE=U)
        [
            'MNG_NO' => 'C-4', 'BPLC_NM' => '동일의원', 'BZSTAT_SE_NM' => '영업',
            'MDLCR_INST_BTP_NM' => '의원', 'LCPMT_YMD' => '2020-01-01',
            'LAST_MDFCN_PNT' => '20260604010000', 'DAT_UPDT_SE' => 'U',
        ],
    ]))]);

    $sync = $this->service->syncAll(['services' => ['clinics']]);

    expect($sync->report['clinics']['skipped'])->toBe(1);
    expect($sync->report['clinics']['updated'])->toBe(0);
});

test('커서가 max LAST_MDFCN_PNT 로 전진한다', function () {
    Http::fake(['apis.data.go.kr/*' => Http::response(moisResponse([
        moisItem(['MNG_NO' => 'C-5', 'LAST_MDFCN_PNT' => '20260604010000']),
        moisItem(['MNG_NO' => 'C-6', 'LAST_MDFCN_PNT' => '20260604093000']),
    ]))]);

    $this->service->syncAll(['services' => ['clinics']]);

    $cursor = HospitalMoisCursor::where('api_id', '15154874')->first();
    expect($cursor->last_synced_at)->toBe('20260604093000');
});

test('멱등: 같은 변경분을 두 번 동기화하면 두 번째는 모두 SKIP', function () {
    Http::fake(['apis.data.go.kr/*' => Http::response(moisResponse([
        moisItem(['MNG_NO' => 'C-7']),
    ]))]);

    $first = $this->service->syncAll(['services' => ['clinics']]);
    $second = $this->service->syncAll(['services' => ['clinics']]);

    expect($first->report['clinics']['inserted'])->toBe(1);
    expect($second->report['clinics']['inserted'])->toBe(0);
    expect($second->report['clinics']['skipped'])->toBe(1);
    expect(Hospital::where('hospital_code', 'C-7')->count())->toBe(1);
});

test('dry-run 은 DB 를 변경하지 않고 카운트만 집계한다', function () {
    Http::fake(['apis.data.go.kr/*' => Http::response(moisResponse([
        moisItem(['MNG_NO' => 'C-8']),
    ]))]);

    $sync = $this->service->syncAll(['services' => ['clinics'], 'dry_run' => true]);

    expect(Hospital::where('hospital_code', 'C-8')->exists())->toBeFalse();
    expect($sync->report['clinics']['inserted'])->toBe(1);
    expect(HospitalMoisCursor::where('api_id', '15154874')->value('last_synced_at'))->toBeNull();
});

test('한 업종이 실패해도 다른 업종은 처리된다 (격리)', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/clinics/')) {
            return Http::response([
                'response' => ['header' => ['resultCode' => '30', 'resultMsg' => 'SERVICE KEY ERROR']],
            ]);
        }

        return Http::response(moisResponse([
            moisItem(['MNG_NO' => 'HP-1', 'MDLCR_INST_BTP_NM' => '종합병원']),
        ]));
    });

    $sync = $this->service->syncAll(['services' => ['clinics', 'hospitals']]);

    expect($sync->report['clinics']['failed'])->toBeTrue();
    expect($sync->report['hospitals']['inserted'])->toBe(1);
    expect($sync->status)->toBe(HospitalMoisSync::STATUS_COMPLETED);
    expect(Hospital::where('hospital_code', 'HP-1')->value('hospital_type'))->toBe('general_hospital');
});
