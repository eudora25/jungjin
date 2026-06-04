<?php

use App\Support\Geo\Epsg5174ToWgs84;

beforeEach(function () {
    $this->converter = new Epsg5174ToWgs84;
});

test('골든 케이스: 송림의원 좌표가 한반도 범위로 변환된다', function () {
    // 행안부 의원 API 실제 응답 값 (Pample 검증 케이스 — 창원 진해구 덕산동)
    $result = $this->converter->convert('355092.7765692', '184193.546203198');

    expect($result)->not->toBeNull();
    expect($result['longitude'])->toBeGreaterThan(128.6)->toBeLessThan(128.8);
    expect($result['latitude'])->toBeGreaterThan(35.0)->toBeLessThan(35.3);
});

test('결과를 소수점 7자리로 반올림한다 (decimal(10,7) 정합)', function () {
    $result = $this->converter->convert('355092.7765692', '184193.546203198');

    $decimals = fn ($n) => strlen(substr(strrchr((string) $n, '.') ?: '', 1));
    expect($decimals($result['longitude']))->toBeLessThanOrEqual(7);
    expect($decimals($result['latitude']))->toBeLessThanOrEqual(7);
});

test('빈 문자열·null·비숫자 입력은 null 을 반환한다', function () {
    expect($this->converter->convert('', ''))->toBeNull();
    expect($this->converter->convert('355092', ''))->toBeNull();
    expect($this->converter->convert('', '184193'))->toBeNull();
    expect($this->converter->convert(null, null))->toBeNull();
    expect($this->converter->convert('abc', '123'))->toBeNull();
    expect($this->converter->convert('355092', 'xyz'))->toBeNull();
});

test('변환 결과가 한반도 범위를 벗어나면 null 을 반환한다', function () {
    expect($this->converter->convert('9999999', '9999999'))->toBeNull();
    expect($this->converter->convert('-9999999', '-9999999'))->toBeNull();
});

test('같은 입력을 반복 변환해도 동일 결과를 낸다 (인스턴스 재사용 안전)', function () {
    $a = $this->converter->convert('355092.7765692', '184193.546203198');
    $b = $this->converter->convert('355092.7765692', '184193.546203198');

    expect($a)->toEqual($b);
});
