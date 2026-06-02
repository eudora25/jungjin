<?php

use App\Models\Hospital;
use App\Services\Clients\HiraDetailImportService;
use App\Services\Clients\HiraInstitutionImportService;

test('심평원 병원정보(B-1)를 기관명+우편번호로 매칭해 ykiho·보강 컬럼을 부착한다', function () {
    $matchable = Hospital::factory()->create([
        'hospital_name' => '봄안과의원',
        'postcode' => '41151',
        'ykiho' => null,
        'homepage' => null,
    ]);

    $path = write_xlsx(
        ['암호화요양기호', '요양기관명', '종별코드', '시도코드', '시군구코드', '읍면동', '우편번호', '병원홈페이지', '개설일자', '총의사수', '좌표(X)', '좌표(Y)'],
        [
            ['YK-1', '(VOM)봄안과의원', '31', '230000', '230002', '율하동', '41151', 'http://bom.test', '2014-01-02', '3', '128.6918945', '35.8698515'],
            ['YK-2', '없는의원', '31', '230000', '230002', '어딘가', '99999', '', '2020-01-01', '1', '127.0', '37.0'],
        ],
    );

    $report = app(HiraInstitutionImportService::class)->import($path);
    @unlink($path);

    expect($report['total'])->toBe(2);
    expect($report['matched'])->toBe(1);
    expect($report['unmatched'])->toBe(1);
    expect($report['match_rate'])->toBe(50.0);
    expect($report['unmatched_samples'][0]['name'])->toBe('없는의원');

    $matchable->refresh();
    expect($matchable->ykiho)->toBe('YK-1');
    expect($matchable->clazz_code)->toBe('31');
    expect($matchable->sido_code)->toBe('230000');
    expect($matchable->eupmyeondong)->toBe('율하동');
    expect($matchable->homepage)->toBe('http://bom.test');
    expect((float) $matchable->latitude)->toBe(35.8698515);
    expect((float) $matchable->longitude)->toBe(128.6918945);
});

test('동일 기관명+우편번호가 2건 이상이면 모호로 보류한다', function () {
    Hospital::factory()->count(2)->create(['hospital_name' => '같은의원', 'postcode' => '12345', 'ykiho' => null]);

    $path = write_xlsx(
        ['암호화요양기호', '요양기관명', '우편번호'],
        [['YK-9', '같은의원', '12345']],
    );

    $report = app(HiraInstitutionImportService::class)->import($path);
    @unlink($path);

    expect($report['matched'])->toBe(0);
    expect($report['ambiguous'])->toBe(1);
    expect(Hospital::whereNotNull('ykiho')->count())->toBe(0);
});

test('심평원 진료과목(B-5, 1:N)을 ykiho로 연결 적재하고 미매칭은 스킵한다', function () {
    $h = Hospital::factory()->create(['ykiho' => 'YK-1']);

    $path = write_xlsx(
        ['암호화요양기호', '요양기관명', '진료과목코드', '진료과목코드명', '과목별 전문의수', '선택진료 의사수'],
        [
            ['YK-1', '병원', '01', '내과', '2', '0'],
            ['YK-1', '병원', '12', '외과', '1', '0'],
            ['YK-X', '미매칭', '01', '내과', '1', '0'],
        ],
    );

    $report = app(HiraDetailImportService::class)->import('specialties', $path);
    @unlink($path);

    expect($report['resolved'])->toBe(2);
    expect($report['skipped_unmatched'])->toBe(1);
    expect($h->specialties()->count())->toBe(2);
    expect($h->specialties()->where('dept_code', '01')->value('specialist_count'))->toBe(2);
});

test('원천에 동일 (기관,코드) 중복 행이 있어도 1건만 적재한다 (insertOrIgnore)', function () {
    $h = Hospital::factory()->create(['ykiho' => 'YK-1']);

    $path = write_xlsx(
        ['암호화요양기호', '요양기관명', '검색코드', '검색코드명'],
        [
            ['YK-1', '병원', 'ST', '방문진료 시범기관'],
            ['YK-1', '병원', 'ST', '방문진료 시범기관'], // 원천 중복
            ['YK-1', '병원', 'S5', 'HPV 참여기관'],
        ],
    );

    app(HiraDetailImportService::class)->import('special_treatments', $path);
    @unlink($path);

    expect($h->specialTreatments()->count())->toBe(2);
});

test('진료과목 재적재 시 기관별 기존 행을 교체한다(멱등)', function () {
    $h = Hospital::factory()->create(['ykiho' => 'YK-1']);
    $svc = app(HiraDetailImportService::class);

    $first = write_xlsx(
        ['암호화요양기호', '요양기관명', '진료과목코드', '진료과목코드명', '과목별 전문의수', '선택진료 의사수'],
        [['YK-1', '병원', '01', '내과', '2', '0'], ['YK-1', '병원', '12', '외과', '1', '0']],
    );
    $svc->import('specialties', $first);
    @unlink($first);

    $second = write_xlsx(
        ['암호화요양기호', '요양기관명', '진료과목코드', '진료과목코드명', '과목별 전문의수', '선택진료 의사수'],
        [['YK-1', '병원', '01', '내과', '5', '0']],
    );
    $svc->import('specialties', $second);
    @unlink($second);

    expect($h->specialties()->count())->toBe(1);
    expect($h->specialties()->where('dept_code', '01')->value('specialist_count'))->toBe(5);
});

test('심평원 시설정보(B-3, 1:1)를 병상 상세로 upsert 한다', function () {
    $h = Hospital::factory()->create(['ykiho' => 'YK-1']);

    $path = write_xlsx(
        ['암호화요양기호', '요양기관명', '설립구분코드', '설립구분코드명', '일반입원실일반병상수', '수술실병상수', '응급실병상수'],
        [['YK-1', '병원', '12', '개인', '40', '2', '1']],
    );

    $report = app(HiraDetailImportService::class)->import('facilities', $path);
    @unlink($path);

    expect($report['resolved'])->toBe(1);
    $h->load('facility');
    expect($h->facility->establishment_name)->toBe('개인');
    expect($h->facility->general_normal_beds)->toBe(40);
    expect($h->facility->operating_room_beds)->toBe(2);
});

test('심평원 세부정보(B-4)의 요일별 진료시간을 JSON 으로 저장한다', function () {
    $h = Hospital::factory()->create(['ykiho' => 'YK-1']);

    $path = write_xlsx(
        ['암호화요양기호', '요양기관명', '주차_가능대수', '주차_비용 부담여부', '진료시작시간_월요일', '진료종료시간_월요일'],
        [['YK-1', '병원', '30', 'N', '0900', '1800']],
    );

    app(HiraDetailImportService::class)->import('hours', $path);
    @unlink($path);

    $h->load('hours');
    expect($h->hours->parking_capacity)->toBe(30);
    expect($h->hours->parking_fee_required)->toBeFalse();
    expect($h->hours->treatment_hours['mon'])->toBe(['start' => '0900', 'end' => '1800']);
});
