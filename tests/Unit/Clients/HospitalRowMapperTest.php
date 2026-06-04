<?php

use App\Services\Clients\HospitalRowMapper;

beforeEach(function () {
    $this->mapper = new HospitalRowMapper;
});

test('영업상태명을 active/inactive 로 매핑한다', function () {
    expect($this->mapper->mapStatus('영업/정상'))->toBe('active');
    expect($this->mapper->mapStatus('정상영업'))->toBe('active');
    expect($this->mapper->mapStatus('폐업'))->toBe('inactive');
    expect($this->mapper->mapStatus('영업취소'))->toBe('inactive');
    expect($this->mapper->mapStatus('휴업'))->toBe('inactive');
});

test('의료기관종별명을 hospital_type 으로 매핑한다', function () {
    expect($this->mapper->mapHospitalType('종합병원'))->toBe('general_hospital');
    expect($this->mapper->mapHospitalType('치과의원'))->toBe('dental');
    expect($this->mapper->mapHospitalType('한방병원'))->toBe('oriental');
    expect($this->mapper->mapHospitalType('한의원'))->toBe('oriental');
    expect($this->mapper->mapHospitalType('의원'))->toBe('clinic');
    expect($this->mapper->mapHospitalType('요양병원'))->toBe('hospital');
    expect($this->mapper->mapHospitalType('알수없음'))->toBe('other');
});

test('빈 문자열은 NULL 로, 날짜는 YYYY-MM-DD 로 정규화한다', function () {
    $now = '2026-06-04 00:00:00';

    $payload = $this->mapper->buildPayload([
        'code' => 'H-1',
        'name' => '병원',
        'status_raw' => '영업/정상',
        'opened_on' => '19900315',     // YYYYMMDD
        'closed_on' => '',             // emptyToNull
        'phone' => '',
        'total_area' => '8,500.50',
    ], 7, $now);

    expect($payload['opened_on'])->toBe('1990-03-15');
    expect($payload['closed_on'])->toBeNull();
    expect($payload['phone'])->toBeNull();
    expect($payload['total_area'])->toBe('8500.50');
    expect($payload['created_by'])->toBe(7);
    expect($payload['status'])->toBe('active');
});

test('CSV 논리키와 API 논리키 입력이 동일한 핵심 payload 를 만든다', function () {
    $now = '2026-06-04 00:00:00';

    // CSV(한글 헤더) 어댑터가 만든 논리키
    $csvLogical = [
        'code' => 'H-100',
        'name' => '큰병원',
        'status_raw' => '영업/정상',
        'kind' => '종합병원',
        'specialties' => '내과, 외과',
        'postcode' => '03100',
        'address' => '서울 종로구 1',
        'phone' => '0211110000',
        'opened_on' => '1990-03-15',
        'closed_on' => null,
        'doctor_count' => '45',
        'bed_count' => '120',
        'inpatient_room_count' => '30',
        'total_area' => '8500.50',
        'license_authority_code' => '3000000',
        'source_synced_at' => '2026-04-01 22:30:12',
    ];

    // API(영문 필드) 어댑터가 만든 논리키 — 같은 의미값(원시 형식만 다름)
    $apiLogical = [
        'code' => 'H-100',
        'name' => '큰병원',
        'status_raw' => '영업/정상',
        'kind' => '종합병원',
        'specialties' => '내과, 외과',
        'postcode' => '03100',
        'address' => '서울 종로구 1',
        'phone' => '0211110000',
        'opened_on' => '19900315',   // API 는 YYYYMMDD 로 오기도 함
        'closed_on' => '',
        'doctor_count' => '45',
        'bed_count' => '120',
        'inpatient_room_count' => '30',
        'total_area' => '8500.50',
        'license_authority_code' => '3000000',
        'source_synced_at' => '2026-04-01 22:30:12',
    ];

    $csvPayload = $this->mapper->buildPayload($csvLogical, 1, $now);
    $apiPayload = $this->mapper->buildPayload($apiLogical, 1, $now);

    expect($apiPayload)->toEqual($csvPayload);
    expect($csvPayload['hospital_type'])->toBe('general_hospital');
    expect($csvPayload['specialty'])->toBe('내과');
});

test('좌표는 논리키에 있을 때만 payload 에 포함된다 (CSV 회귀 보호)', function () {
    $now = '2026-06-04 00:00:00';

    $csv = $this->mapper->buildPayload(['code' => 'H-1', 'name' => 'A', 'status_raw' => '영업'], 1, $now);
    expect($csv)->not->toHaveKey('latitude');
    expect($csv)->not->toHaveKey('longitude');

    $api = $this->mapper->buildPayload([
        'code' => 'H-1', 'name' => 'A', 'status_raw' => '영업',
        'latitude' => 37.5, 'longitude' => 127.0,
    ], 1, $now);
    expect($api['latitude'])->toBe(37.5);
    expect($api['longitude'])->toBe(127.0);
});

test('updateColumns 는 좌표 포함 여부를 제어한다', function () {
    expect($this->mapper->updateColumns())->not->toContain('latitude');
    expect($this->mapper->updateColumns(withCoords: true))->toContain('latitude');
    expect($this->mapper->updateColumns(withCoords: true))->toContain('longitude');
});
