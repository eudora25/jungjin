<?php

namespace App\Services\Clients;

use App\Models\Hospital;
use App\Models\HospitalMoisCursor;
use App\Models\HospitalMoisSync;
use App\Support\Geo\Epsg5174ToWgs84;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 행안부(MOIS) 공공데이터 API 증분 동기화 서비스.
 *
 * 업종(의원·병원·부속의료기관)을 순회하며 커서(마지막 LAST_MDFCN_PNT) 이후 변경분을 페이징 수신,
 * API 필드를 논리 키로 변환(공용 HospitalRowMapper)하고 좌표를 WGS84 로 변환해 hospitals 에 건건 적용한다.
 * DAT_UPDT_SE 로 신규(I)·변경(U)=upsert / 폐업(D)=상태 비활성+폐업일 처리하며, 변경 없는 행은 SKIP 한다.
 * 업종/페이지 단위로 예외를 격리하고, 결과를 HospitalMoisSync 이력에 업종별 카운트로 기록한다.
 *
 * 공유 마스터(platform 소유) 대상이라 테넌트 스코프가 없으며 DB 직접 쓰기로 적용한다.
 * 설계: docs/modules/client/HOSPITAL_LOCALDATA_API_SYNC.md §3~§5
 */
class HospitalMoisSyncService
{
    /** 변경 감지(SKIP) 비교 대상 컬럼. */
    private const COMPARE_FIELDS = [
        'hospital_name', 'address', 'postcode', 'phone', 'hospital_type', 'status',
        'opened_on', 'closed_on', 'suspend_begin_on', 'suspend_end_on', 'latitude', 'longitude',
        'doctor_count', 'bed_count', 'inpatient_room_count', 'total_area',
    ];

    public function __construct(
        private readonly HospitalMoisApiClient $client,
        private readonly HospitalRowMapper $mapper,
        private readonly Epsg5174ToWgs84 $geo,
    ) {}

    /**
     * 동기화 실행. options: user_id, trigger, services(업종키 배열), since(YYYYMMDD), dry_run(bool).
     */
    public function syncAll(array $options = []): HospitalMoisSync
    {
        $userId = $options['user_id'] ?? null;
        $trigger = $options['trigger'] ?? HospitalMoisSync::TRIGGER_MANUAL;
        $allServices = (array) config('mois.services', []);
        $serviceKeys = $options['services'] ?? array_keys($allServices);
        $since = isset($options['since']) ? $this->normalizeSince((string) $options['since']) : null;
        $dryRun = (bool) ($options['dry_run'] ?? false);

        $sync = HospitalMoisSync::create([
            'created_by' => $userId,
            'trigger' => $trigger,
            'params' => array_filter([
                'services' => $serviceKeys,
                'since' => $options['since'] ?? null,
                'dry_run' => $dryRun ?: null,
            ]),
            'status' => HospitalMoisSync::STATUS_PROCESSING,
            'started_at' => now(),
        ]);

        $report = [];
        $success = 0;
        $attempted = 0;

        foreach ($serviceKeys as $key) {
            $svc = $allServices[$key] ?? null;
            if ($svc === null) {
                continue;
            }
            $attempted++;
            try {
                $report[$key] = $this->syncService($key, $svc, $since, $userId, $dryRun, $sync->id);
                $success++;
            } catch (Throwable $e) {
                report($e);
                $report[$key] = ['failed' => true, 'error' => $e->getMessage()];
            }
        }

        $sync->update([
            'status' => ($attempted > 0 && $success === 0)
                ? HospitalMoisSync::STATUS_FAILED
                : HospitalMoisSync::STATUS_COMPLETED,
            'report' => $report,
            'finished_at' => now(),
        ]);

        return $sync->refresh();
    }

    /**
     * 한 업종을 동기화하고 카운트 요약을 반환.
     *
     * @param  array{id:string, base:string, label?:string}  $svc
     * @return array<string,mixed>
     */
    private function syncService(string $key, array $svc, ?string $since, ?int $userId, bool $dryRun, int $syncId): array
    {
        $cursor = HospitalMoisCursor::firstOrCreate(['api_id' => $svc['id']]);
        $from = $this->resolveFrom($cursor, $since);

        $size = max(1, (int) config('mois.num_of_rows', 100));
        $sleepMs = (int) config('mois.page_sleep_ms', 200);

        $counts = ['fetched' => 0, 'inserted' => 0, 'updated' => 0, 'closed' => 0, 'skipped' => 0, 'failed' => 0];
        $maxCursor = $cursor->last_synced_at;
        $now = now();

        $pageNo = 1;
        $total = 0;
        $maxPages = 10000; // 무한 루프 안전망

        do {
            $page = $this->client->fetchPage($svc['base'], $from, null, $pageNo, $size);
            $total = $page['totalCount'];
            $items = $page['items'];

            if (empty($items)) {
                break;
            }

            $maxCursor = $this->applyPage($items, $userId, $dryRun, $counts, $maxCursor, $now);

            $pageNo++;
            if ($sleepMs > 0 && ($pageNo - 1) * $size < $total) {
                usleep($sleepMs * 1000);
            }
        } while (($pageNo - 1) * $size < $total && $pageNo <= $maxPages);

        if (! $dryRun && $maxCursor !== null && $maxCursor !== $cursor->last_synced_at) {
            $cursor->update(['last_synced_at' => $maxCursor, 'last_sync_id' => $syncId]);
        }

        return $counts + ['from' => $from, 'totalCount' => $total, 'cursor' => $maxCursor];
    }

    /**
     * 한 페이지의 행들을 hospitals 에 적용하고 갱신된 커서(max LAST_MDFCN_PNT)를 반환.
     *
     * @param  array<int,array<string,mixed>>  $items
     * @param  array<string,int>  $counts
     */
    private function applyPage(array $items, ?int $userId, bool $dryRun, array &$counts, ?string $maxCursor, CarbonInterface $now): ?string
    {
        $prepared = [];
        foreach ($items as $item) {
            $counts['fetched']++;
            $logical = $this->apiToLogical($item);
            $code = $this->mapper->trimOrNull($logical['code'] ?? null);
            if ($code === null) {
                $counts['failed']++;

                continue;
            }
            // 응답은 'yyyy-MM-dd HH:mm:ss' 형식 → 커서/cond 용 14자리(yyyyMMddHHmmss)로 정규화
            $mdfcn = $this->normalizePoint($item['LAST_MDFCN_PNT'] ?? null);
            $maxCursor = $this->maxStr($maxCursor, $mdfcn);
            $prepared[] = [
                'code' => $code,
                'op' => strtoupper((string) ($this->mapper->trimOrNull($item['DAT_UPDT_SE'] ?? null) ?? 'U')),
                'payload' => $this->mapper->buildPayload($logical, $userId, $now),
            ];
        }

        if (empty($prepared)) {
            return $maxCursor;
        }

        $codes = array_column($prepared, 'code');
        $existing = Hospital::withTrashed()->whereIn('hospital_code', $codes)->get()->keyBy('hospital_code');

        foreach ($prepared as $p) {
            $row = $existing->get($p['code']);

            if ($p['op'] === 'D') {
                $this->applyClose($row, $p['payload'], $userId, $dryRun, $counts, $now);

                continue;
            }

            if ($row === null) {
                $counts['inserted']++;
                if (! $dryRun) {
                    DB::table('hospitals')->insert($p['payload']);
                }
            } elseif ($this->changed($row, $p['payload'])) {
                $counts['updated']++;
                if (! $dryRun) {
                    DB::table('hospitals')->where('hospital_code', $p['code'])->update($this->updateData($p['payload']));
                }
            } else {
                $counts['skipped']++;
            }
        }

        return $maxCursor;
    }

    /**
     * 폐업(DAT_UPDT_SE=D): 상태 비활성 + 폐업일. 물리/소프트 삭제하지 않는다(실적 FK 보존).
     *
     * @param  array<string,mixed>  $payload
     * @param  array<string,int>  $counts
     */
    private function applyClose(?Hospital $row, array $payload, ?int $userId, bool $dryRun, array &$counts, CarbonInterface $now): void
    {
        if ($row === null) {
            // 마스터에 없는 폐업 통지 → 신규 폐업점은 적재 대상 아님
            $counts['skipped']++;

            return;
        }

        $closedOn = $payload['closed_on'] ?? $now->format('Y-m-d');

        if ($this->norm($row->status) === 'inactive' && $this->norm($row->closed_on) === $this->norm($closedOn)) {
            $counts['skipped']++;

            return;
        }

        $counts['closed']++;
        if (! $dryRun) {
            DB::table('hospitals')->where('hospital_code', $row->hospital_code)->update([
                'status' => 'inactive',
                'closed_on' => $closedOn,
                'updated_by' => $userId,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * MOIS API 한 행(영문 필드)을 공용 매퍼의 논리 키로 변환. 좌표는 변환 성공 시에만 주입.
     *
     * @param  array<string,mixed>  $item
     * @return array<string,mixed>
     */
    private function apiToLogical(array $item): array
    {
        $logical = [
            'code' => $item['MNG_NO'] ?? null,
            'name' => $item['BPLC_NM'] ?? null,
            'status_raw' => $this->resolveStatusRaw($item),
            'kind' => $item['MDLCR_INST_BTP_NM'] ?? null,
            'specialties' => $item['MDEXM_SBJCT_CN_NM'] ?? null,
            'postcode' => $this->firstNonEmpty($item['ROAD_NM_ZIP'] ?? null, $item['LCTN_ZIP'] ?? null),
            'address' => $this->firstNonEmpty($item['ROAD_NM_ADDR'] ?? null, $item['LOTNO_ADDR'] ?? null),
            'phone' => $item['TELNO'] ?? null,
            'opened_on' => $item['LCPMT_YMD'] ?? null,
            'closed_on' => $item['CLSBIZ_YMD'] ?? null,
            'suspend_begin_on' => $item['TCBIZ_BGNG_YMD'] ?? null,
            'suspend_end_on' => $item['TCBIZ_END_YMD'] ?? null,
            'license_authority_code' => $item['OPN_ATMY_GRP_CD'] ?? null,
            'source_synced_at' => $item['LAST_MDFCN_PNT'] ?? null,
            // 규모 수치 (CSV 경로와 파리티) — GFA 총면적·SCKBD_CNT 병상수·HSPTLZRM_CNT 입원실수·HCWKR_CNT 의료인수
            'total_area' => $item['GFA'] ?? null,
            'bed_count' => $item['SCKBD_CNT'] ?? null,
            'inpatient_room_count' => $item['HSPTLZRM_CNT'] ?? null,
            'doctor_count' => $item['HCWKR_CNT'] ?? null,
        ];

        $coord = $this->geo->convert($item['CRD_INFO_X'] ?? null, $item['CRD_INFO_Y'] ?? null);
        if ($coord !== null) {
            $logical['latitude'] = $coord['latitude'];
            $logical['longitude'] = $coord['longitude'];
        }

        return $logical;
    }

    /**
     * 영업상태 원시값: 영업상태명(SALS_STTS_NM = '영업/정상'·'폐업' 등)이 있으면 우선,
     * 없으면 영업상태코드(SALS_STTS_CD: 01 영업 / 그 외 폐업).
     * (BZSTAT_SE_NM 은 영업상태가 아니라 종별명이므로 사용하지 않는다 — R7 실데이터로 확인.)
     */
    private function resolveStatusRaw(array $item): ?string
    {
        $name = $this->mapper->trimOrNull($item['SALS_STTS_NM'] ?? null);
        if ($name !== null) {
            return $name;
        }

        $code = $this->mapper->trimOrNull($item['SALS_STTS_CD'] ?? null);
        if ($code === '01') {
            return '영업';
        }

        return $code !== null ? '폐업' : null;
    }

    /**
     * 변경 감지: 비교 대상 컬럼 중 payload 에 존재하는 값이 기존과 다르면 true.
     *
     * @param  array<string,mixed>  $payload
     */
    private function changed(Hospital $row, array $payload): bool
    {
        foreach (self::COMPARE_FIELDS as $field) {
            if (! array_key_exists($field, $payload)) {
                continue; // 좌표 미변환 등 — 미포함 컬럼은 비교/갱신 제외
            }
            if ($this->norm($row->getAttribute($field)) !== $this->norm($payload[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * update 시 갱신할 컬럼만 payload 에서 추출(존재하는 것만 — 미변환 좌표 보존).
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function updateData(array $payload): array
    {
        return array_intersect_key($payload, array_flip($this->mapper->updateColumns(withCoords: true)));
    }

    private function resolveFrom(HospitalMoisCursor $cursor, ?string $since): ?string
    {
        if ($since !== null) {
            return $since;
        }
        if ($cursor->last_synced_at !== null) {
            return $cursor->last_synced_at;
        }

        // 첫 실행/커서 없음 → D-N lookback (행안부 D-2 갱신 기준)
        $lookback = max(0, (int) config('mois.lookback_days', 2));

        return now()->subDays($lookback)->startOfDay()->format('YmdHis');
    }

    private function normalizeSince(string $since): string
    {
        return $this->normalizePoint($since) ?? str_pad('', 14, '0');
    }

    /** 'yyyy-MM-dd HH:mm:ss'·'yyyyMMdd'·'yyyyMMddHHmmss' 등 → 14자리(yyyyMMddHHmmss). */
    private function normalizePoint(mixed $v): ?string
    {
        $digits = preg_replace('/\D/', '', (string) ($v ?? ''));
        if ($digits === '') {
            return null;
        }

        return str_pad(substr($digits, 0, 14), 14, '0');
    }

    private function maxStr(?string $a, ?string $b): ?string
    {
        if ($a === null) {
            return $b;
        }
        if ($b === null) {
            return $a;
        }

        return strcmp($a, $b) >= 0 ? $a : $b;
    }

    private function firstNonEmpty(mixed $a, mixed $b): mixed
    {
        return $this->mapper->trimOrNull($a) ?? $b;
    }

    private function norm(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if ($v instanceof CarbonInterface) {
            return $v->format('Y-m-d');
        }
        if (is_numeric($v)) {
            return (string) (float) $v;
        }

        return trim((string) $v);
    }
}
