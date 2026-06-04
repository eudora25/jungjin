<?php

namespace App\Services\Clients;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * 행안부(MOIS) 공공데이터 API 클라이언트 — 병의원 인허가 변경분 페이지 수신.
 *
 * 공통 엔드포인트 `{base}/info` 를 GET 하며, 인증키(serviceKey)·페이징·증분 조건
 * (`cond[LAST_MDFCN_PNT::GTE/LT]`) 을 구성한다. 응답 wrapper(response.header/body)를
 * 검사해 `resultCode='0'` 만 성공으로 보고, items.item 을 리스트로 평탄화해 반환한다.
 *
 * 설계: docs/modules/client/HOSPITAL_LOCALDATA_API_SYNC.md §4-2
 */
class HospitalMoisApiClient
{
    /**
     * 한 페이지를 조회해 평탄화된 결과를 반환.
     *
     * @return array{totalCount:int, pageNo:int, numOfRows:int, items:array<int,array<string,mixed>>}
     */
    public function fetchPage(string $baseUrl, ?string $from, ?string $to, int $pageNo, int $size = 100): array
    {
        $params = array_filter([
            'pageNo' => $pageNo,
            'numOfRows' => $size,
            'returnType' => 'json',
            'cond[LAST_MDFCN_PNT::GTE]' => $from,
            'cond[LAST_MDFCN_PNT::LT]' => $to,
        ], fn ($v) => $v !== null && $v !== '');

        // serviceKey 는 발급 시 이미 URL-encoded 라 쿼리 배열로 넘기면 이중 인코딩된다.
        // → URL 에 raw 로 붙이고, 나머지 파라미터만 http_build_query 로 인코딩(cond[..] 키 포함).
        $apiKey = (string) config('mois.api_key');
        $url = rtrim($baseUrl, '/').'/info?serviceKey='.$apiKey.'&'.http_build_query($params);

        $retry = (array) config('mois.retry', ['times' => 3, 'sleep' => 200]);

        $response = Http::retry($retry['times'] ?? 3, $retry['sleep'] ?? 200)
            ->acceptJson()
            ->get($url)
            ->throw();

        $json = $response->json();
        $header = data_get($json, 'response.header', []);
        $resultCode = (string) data_get($header, 'resultCode', '');

        if ($resultCode !== '0') {
            $msg = (string) data_get($header, 'resultMsg', '알 수 없는 오류');
            throw new RuntimeException("MOIS API 오류 [{$resultCode}] {$msg}");
        }

        $body = data_get($json, 'response.body', []);

        return [
            'totalCount' => (int) data_get($body, 'totalCount', 0),
            'pageNo' => (int) data_get($body, 'pageNo', $pageNo),
            'numOfRows' => (int) data_get($body, 'numOfRows', $size),
            'items' => $this->flattenItems(data_get($body, 'items.item')),
        ];
    }

    /**
     * items.item 정규화: 단일 객체(연관배열)·객체 리스트·빈값('')/null 을 모두 리스트로.
     *
     * @return array<int,array<string,mixed>>
     */
    private function flattenItems(mixed $item): array
    {
        if (empty($item)) {
            return [];
        }

        // 단일 결과는 연관배열로 옴 → 리스트로 감싼다.
        if (array_is_list($item)) {
            return $item;
        }

        return [$item];
    }
}
