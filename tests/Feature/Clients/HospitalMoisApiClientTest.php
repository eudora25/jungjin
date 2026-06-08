<?php

use App\Services\Clients\HospitalMoisApiClient;
use Illuminate\Support\Facades\Http;

function moisBody(array $items, int $totalCount, int $pageNo = 1, int $numOfRows = 100): array
{
    return [
        'response' => [
            'header' => ['resultCode' => '0', 'resultMsg' => 'NORMAL SERVICE.'],
            'body' => [
                'totalCount' => $totalCount,
                'pageNo' => $pageNo,
                'numOfRows' => $numOfRows,
                'items' => ['item' => $items],
            ],
        ],
    ];
}

beforeEach(function () {
    config()->set('mois.api_key', 'TEST_KEY_ENCODED');
    config()->set('mois.retry', ['times' => 1, 'sleep' => 0]);
    $this->client = app(HospitalMoisApiClient::class);
    $this->base = 'https://apis.data.go.kr/1741000/clinics';
});

test('serviceKey·페이징·cond[] 쿼리를 구성해 호출하고 items 를 평탄화한다', function () {
    Http::fake([
        'apis.data.go.kr/*' => Http::response(moisBody([
            ['MNG_NO' => 'H-1', 'BPLC_NM' => '가나의원'],
            ['MNG_NO' => 'H-2', 'BPLC_NM' => '다라의원'],
        ], 2)),
    ]);

    $page = $this->client->fetchPage($this->base, '20260601000000', '20260602000000', 1, 100);

    expect($page['totalCount'])->toBe(2);
    expect($page['items'])->toHaveCount(2);
    expect($page['items'][0]['MNG_NO'])->toBe('H-1');

    Http::assertSent(function ($request) {
        $url = $request->url();

        return str_contains($url, '/clinics/info')
            && str_contains($url, 'serviceKey=TEST_KEY_ENCODED')
            && str_contains($url, 'pageNo=1')
            && str_contains($url, 'numOfRows=100')
            && str_contains($url, 'returnType=json')
            && str_contains($url, 'DAT_UPDT_PNT')
            && str_contains($url, '20260601000000')
            && str_contains($url, '20260602000000');
    });
});

test('단일 item(연관배열)도 리스트로 평탄화한다', function () {
    Http::fake([
        'apis.data.go.kr/*' => Http::response(moisBody(
            ['MNG_NO' => 'H-9', 'BPLC_NM' => '홀로의원'], 1
        )),
    ]);

    $page = $this->client->fetchPage($this->base, null, null, 1, 100);

    expect($page['items'])->toHaveCount(1);
    expect($page['items'][0]['MNG_NO'])->toBe('H-9');
});

test('결과 0건이면 빈 items 를 반환한다', function () {
    Http::fake([
        'apis.data.go.kr/*' => Http::response([
            'response' => [
                'header' => ['resultCode' => '0', 'resultMsg' => 'NORMAL SERVICE.'],
                'body' => ['totalCount' => 0, 'pageNo' => 1, 'numOfRows' => 100, 'items' => ''],
            ],
        ]),
    ]);

    $page = $this->client->fetchPage($this->base, null, null, 1, 100);

    expect($page['totalCount'])->toBe(0);
    expect($page['items'])->toBe([]);
});

test('from/to 가 null 이면 cond[] 파라미터를 보내지 않는다', function () {
    Http::fake(['apis.data.go.kr/*' => Http::response(moisBody([], 0))]);

    $this->client->fetchPage($this->base, null, null, 1, 100);

    Http::assertSent(fn ($request) => ! str_contains($request->url(), 'DAT_UPDT_PNT'));
});

test('resultCode 가 0 이 아니면 예외를 던진다', function () {
    Http::fake([
        'apis.data.go.kr/*' => Http::response([
            'response' => [
                'header' => ['resultCode' => '30', 'resultMsg' => 'SERVICE KEY IS NOT REGISTERED ERROR.'],
            ],
        ]),
    ]);

    expect(fn () => $this->client->fetchPage($this->base, null, null, 1, 100))
        ->toThrow(RuntimeException::class, 'SERVICE KEY IS NOT REGISTERED ERROR.');
});

test('HTTP 5xx 응답이면 예외를 던진다', function () {
    Http::fake(['apis.data.go.kr/*' => Http::response('gateway error', 503)]);

    expect(fn () => $this->client->fetchPage($this->base, null, null, 1, 100))
        ->toThrow(Exception::class);
});
