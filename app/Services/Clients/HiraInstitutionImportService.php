<?php

namespace App\Services\Clients;

use App\Models\Hospital;
use Illuminate\Support\Facades\DB;

/**
 * 심평원(HIRA) 병원정보서비스(B-1) 적재 — 기관명+우편번호 매칭으로 기존 병의원(인허가 베이스)에
 * `ykiho` 및 보강 컬럼(종별·지역·홈페이지·좌표)을 부착한다.
 *
 * 기관명+우편번호가 여러 행에 걸리면(같은 위치=같은 기관의 개·폐업 인허가 이력) 보류하지 않고
 * 대표행을 타이브레이크로 선택한다: 영업(active) > 폐업일 없음 > 개설일(opened_on) 최신 > 최근 id.
 * 단, 우편번호 없이 기관명만 동명인 경우(다른 위치일 수 있음)는 유일할 때만 매칭하고 보류한다.
 * 매칭 안 된 기관은 신규 생성하지 않고 리포트로 보류한다(설계 결정 §6-1).
 */
class HiraInstitutionImportService
{
    private const BATCH = 500;

    private const UNMATCHED_SAMPLE_LIMIT = 50;

    private const CONFLICT_SAMPLE_LIMIT = 50;

    /**
     * @return array{total:int, matched:int, unmatched:int, ambiguous:int, tie_broken:int, conflict:int, match_rate:float, unmatched_samples:array<int,array<string,string>>, conflict_samples:array<int,array<string,mixed>>}
     */
    public function import(string $xlsxPath): array
    {
        [$byNamePostcode, $byName, $claimedYkiho, $meta] = $this->buildHospitalIndex();

        $total = 0;
        $matched = 0;
        $unmatched = 0;
        $ambiguous = 0;
        $tieBroken = 0;
        $conflict = 0;
        $unmatchedSamples = [];
        $conflictSamples = [];
        $batch = [];

        foreach (XlsxRowReader::rows($xlsxPath) as $row) {
            $total++;

            $ykiho = $this->v($row, '암호화요양기호');
            $name = $this->v($row, '요양기관명');
            $postcode = $this->v($row, '우편번호');

            if ($ykiho === null || $name === null) {
                $unmatched++;

                continue;
            }

            $norm = $this->normalizeName($name);
            $hospitalId = $this->resolveHospitalId($norm, $postcode, $byNamePostcode, $byName, $meta, $ambiguous, $tieBroken);

            if ($hospitalId === null) {
                $unmatched++;
                if (count($unmatchedSamples) < self::UNMATCHED_SAMPLE_LIMIT) {
                    $unmatchedSamples[] = ['ykiho' => $ykiho, 'name' => $name, 'postcode' => (string) $postcode];
                }

                continue;
            }

            // ykiho 충돌 가드: 이 ykiho 가 이미 다른 병의원에 부착돼 있으면 건너뜀(유니크 위반 방지).
            // 같은 병의원이면 재실행(멱등)이므로 통과. 원천 중복 ykiho·재실행 시 다른 매칭을 안전 처리.
            $owner = $claimedYkiho[$ykiho] ?? null;
            if ($owner !== null && $owner !== $hospitalId) {
                $conflict++;
                if (count($conflictSamples) < self::CONFLICT_SAMPLE_LIMIT) {
                    $conflictSamples[] = [
                        'ykiho' => $ykiho,
                        'name' => $name,
                        'postcode' => (string) $postcode,
                        'owner_id' => $owner,      // 이미 이 ykiho 를 가진 병원
                        'target_id' => $hospitalId, // 이름+우편번호로 매칭된 병원(건너뜀)
                    ];
                }

                continue;
            }
            $claimedYkiho[$ykiho] = $hospitalId;

            $matched++;
            $batch[] = [
                'id' => $hospitalId,
                'ykiho' => $ykiho,
                'clazz_code' => $this->v($row, '종별코드'),
                'sido_code' => $this->v($row, '시도코드'),
                'sigungu_code' => $this->v($row, '시군구코드'),
                'eupmyeondong' => $this->v($row, '읍면동'),
                'homepage' => $this->v($row, '병원홈페이지'),
                'latitude' => $this->coord($this->v($row, '좌표(Y)')),
                'longitude' => $this->coord($this->v($row, '좌표(X)')),
                'updated_at' => now(),
            ];

            if (count($batch) >= self::BATCH) {
                $this->flush($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            $this->flush($batch);
        }

        return [
            'total' => $total,
            'matched' => $matched,
            'unmatched' => $unmatched,
            'ambiguous' => $ambiguous,
            'tie_broken' => $tieBroken,
            'conflict' => $conflict,
            'match_rate' => $total > 0 ? round($matched / $total * 100, 1) : 0.0,
            'unmatched_samples' => $unmatchedSamples,
            'conflict_samples' => $conflictSamples,
        ];
    }

    /**
     * 기존 병의원 인덱스 (단일 스캔):
     *  - [normalize(name)|postcode => [ids]], [normalize(name) => [ids]]
     *  - 이미 부착된 ykiho 소유 맵 [ykiho => hospital_id] (충돌 가드용)
     *  - 대표행 타이브레이크용 메타 [id => [status, opened_on, closed_on]]
     *
     * @return array{0: array<string,array<int,int>>, 1: array<string,array<int,int>>, 2: array<string,int>, 3: array<int,array{status:?string, opened_on:?string, closed_on:?string}>}
     */
    private function buildHospitalIndex(): array
    {
        $byNamePostcode = [];
        $byName = [];
        $claimedYkiho = [];
        $meta = [];

        Hospital::query()
            ->select(['id', 'hospital_name', 'postcode', 'ykiho', 'status', 'opened_on', 'closed_on'])
            ->orderBy('id')
            ->chunk(2000, function ($chunk) use (&$byNamePostcode, &$byName, &$claimedYkiho, &$meta) {
                foreach ($chunk as $h) {
                    if ($h->ykiho !== null && $h->ykiho !== '') {
                        $claimedYkiho[$h->ykiho] = $h->id;
                    }
                    $norm = $this->normalizeName($h->hospital_name);
                    if ($norm === '') {
                        continue;
                    }
                    $byName[$norm][] = $h->id;
                    if ($h->postcode) {
                        $byNamePostcode[$norm.'|'.$h->postcode][] = $h->id;
                        // 타이브레이크 메타는 우편번호 보유(=name+postcode 후보) 행만 필요
                        $meta[$h->id] = [
                            'status' => $h->status,
                            'opened_on' => $h->opened_on?->format('Y-m-d'),
                            'closed_on' => $h->closed_on?->format('Y-m-d'),
                        ];
                    }
                }
            });

        return [$byNamePostcode, $byName, $claimedYkiho, $meta];
    }

    /**
     * @param  array<string,array<int,int>>  $byNamePostcode
     * @param  array<string,array<int,int>>  $byName
     * @param  array<int,array{status:?string, opened_on:?string, closed_on:?string}>  $meta
     */
    private function resolveHospitalId(string $norm, ?string $postcode, array $byNamePostcode, array $byName, array $meta, int &$ambiguous, int &$tieBroken): ?int
    {
        // 1순위: 기관명 + 우편번호. 여러 행이면 같은 위치의 개·폐업 이력으로 보고 대표행을 타이브레이크.
        if ($postcode !== null && isset($byNamePostcode[$norm.'|'.$postcode])) {
            $ids = $byNamePostcode[$norm.'|'.$postcode];
            if (count($ids) === 1) {
                return $ids[0];
            }
            $tieBroken++;

            return $this->pickRepresentative($ids, $meta);
        }

        // 2순위(폴백): 기관명 전역 유일할 때만 (우편 다른 동명은 다른 위치일 수 있어 보류)
        if (isset($byName[$norm]) && count($byName[$norm]) === 1) {
            return $byName[$norm][0];
        }

        if (isset($byName[$norm])) {
            $ambiguous++;
        }

        return null;
    }

    /**
     * 같은 위치(이름+우편번호) 후보 중 대표행을 결정적으로 선택.
     * 우선순위: 영업(active) > 폐업일 없음 > 개설일(opened_on) 최신 > 최근 id.
     *
     * @param  array<int,int>  $ids
     * @param  array<int,array{status:?string, opened_on:?string, closed_on:?string}>  $meta
     */
    private function pickRepresentative(array $ids, array $meta): int
    {
        usort($ids, function ($a, $b) use ($meta) {
            $ma = $meta[$a] ?? ['status' => null, 'opened_on' => null, 'closed_on' => null];
            $mb = $meta[$b] ?? ['status' => null, 'opened_on' => null, 'closed_on' => null];

            // 1) active 우선
            $byActive = (int) ($mb['status'] === 'active') <=> (int) ($ma['status'] === 'active');
            if ($byActive !== 0) {
                return $byActive;
            }
            // 2) 폐업일 없음(영업중) 우선
            $byOpen = (int) ($mb['closed_on'] === null) <=> (int) ($ma['closed_on'] === null);
            if ($byOpen !== 0) {
                return $byOpen;
            }
            // 3) 개설일 최신 우선
            $byOpened = strcmp((string) $mb['opened_on'], (string) $ma['opened_on']);
            if ($byOpened !== 0) {
                return $byOpened;
            }

            // 4) 결정적: 최근(큰) id 우선
            return $b <=> $a;
        });

        return $ids[0];
    }

    /**
     * 매칭된 병의원 행에 보강 컬럼만 부분 갱신. (upsert 는 INSERT 행 NOT NULL 검증에 걸려 부적합)
     *
     * @param  array<int,array<string,mixed>>  $batch
     */
    private function flush(array $batch): void
    {
        DB::transaction(function () use ($batch) {
            foreach ($batch as $row) {
                $id = $row['id'];
                unset($row['id']);
                DB::table('hospitals')->where('id', $id)->update($row);
            }
        });
    }

    private function normalizeName(string $name): string
    {
        $n = preg_replace('/\(.*?\)/u', '', $name) ?? $name;
        $n = preg_replace('/\s+/u', '', $n) ?? $n;

        return trim($n);
    }

    private function v(array $row, string $key): ?string
    {
        $s = trim((string) ($row[$key] ?? ''));

        return $s === '' ? null : $s;
    }

    private function coord(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }

        return is_numeric($v) ? $v : null;
    }
}
