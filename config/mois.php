<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 행안부(MOIS) 공공데이터 API — 병의원 증분 동기화 설정
    |--------------------------------------------------------------------------
    |
    | 공공데이터포털(data.go.kr) 행안부 표준데이터 OpenAPI 로 병의원 인허가
    | 변경분(신규·변경·폐업)을 증분 수신해 hospitals 마스터에 건건 upsert 한다.
    | 설계: docs/modules/client/HOSPITAL_LOCALDATA_API_SYNC.md
    |
    */

    // data.go.kr serviceKey. 발급 시 이미 URL-encoded 된 키를 그대로 .env 에 주입한다.
    // ⚠️ 절대 커밋 금지 — .env 비밀로만 관리 (R7 직전 주입).
    'api_key' => env('MOIS_API_KEY', ''),

    // 페이지 크기 (API 상한 100, CSV 의 500 과 다름).
    'num_of_rows' => (int) env('MOIS_API_NUM_OF_ROWS', 100),

    // 증분 lookback (행안부는 D-2 자정 기준 갱신). 커서 없을 때 from = 오늘-N일.
    'lookback_days' => (int) env('MOIS_SYNC_LOOKBACK_DAYS', 2),

    // 스케줄러 자동 동기화 활성화. 운영 검증 후 true.
    'enabled' => (bool) env('MOIS_SYNC_ENABLED', false),

    // HTTP 재시도 정책.
    'retry' => [
        'times' => (int) env('MOIS_API_RETRY_TIMES', 3),
        'sleep' => (int) env('MOIS_API_RETRY_SLEEP', 200),
    ],

    // 페이지 간 대기(ms) — 호출 쿼터 보호.
    'page_sleep_ms' => (int) env('MOIS_API_PAGE_SLEEP_MS', 200),

    // 업종별 데이터셋 (Pample application.yml 검증값). 공통 엔드포인트는 /info.
    'services' => [
        'clinics' => [
            'id' => '15154874',
            'base' => 'https://apis.data.go.kr/1741000/clinics',
            'label' => '의원',
        ],
        'hospitals' => [
            'id' => '15154458',
            'base' => 'https://apis.data.go.kr/1741000/hospitals',
            'label' => '병원',
        ],
        'affiliated' => [
            'id' => '15154643',
            'base' => 'https://apis.data.go.kr/1741000/affiliated_medical_institutions',
            'label' => '부속의료기관',
        ],
    ],

];
