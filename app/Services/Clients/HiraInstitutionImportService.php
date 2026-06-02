<?php

namespace App\Services\Clients;

use App\Models\Hospital;
use Illuminate\Support\Facades\DB;

/**
 * 심평원(HIRA) 병원정보서비스(B-1) 적재 — 기관명+우편번호 매칭으로 기존 병의원(인허가 베이스)에
 * `ykiho` 및 보강 컬럼(종별·지역·홈페이지·좌표)을 부착한다.
 *
 * 미매칭/모호 기관은 신규 생성하지 않고 리포트로 보류한다(설계 결정 §6-1).
 */
class HiraInstitutionImportService
{
    private const BATCH = 500;

    private const UNMATCHED_SAMPLE_LIMIT = 50;

    /**
     * @return array{total:int, matched:int, unmatched:int, ambiguous:int, match_rate:float, unmatched_samples:array<int,array<string,string>>}
     */
    public function import(string $xlsxPath): array
    {
        [$byNamePostcode, $byName] = $this->buildHospitalIndex();

        $total = 0;
        $matched = 0;
        $unmatched = 0;
        $ambiguous = 0;
        $unmatchedSamples = [];
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
            $hospitalId = $this->resolveHospitalId($norm, $postcode, $byNamePostcode, $byName, $ambiguous);

            if ($hospitalId === null) {
                $unmatched++;
                if (count($unmatchedSamples) < self::UNMATCHED_SAMPLE_LIMIT) {
                    $unmatchedSamples[] = ['ykiho' => $ykiho, 'name' => $name, 'postcode' => (string) $postcode];
                }

                continue;
            }

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
            'match_rate' => $total > 0 ? round($matched / $total * 100, 1) : 0.0,
            'unmatched_samples' => $unmatchedSamples,
        ];
    }

    /**
     * 기존 병의원 인덱스 — [normalize(name)|postcode => [ids]], [normalize(name) => [ids]]
     *
     * @return array{0: array<string,array<int,int>>, 1: array<string,array<int,int>>}
     */
    private function buildHospitalIndex(): array
    {
        $byNamePostcode = [];
        $byName = [];

        Hospital::query()
            ->select(['id', 'hospital_name', 'postcode'])
            ->orderBy('id')
            ->chunk(2000, function ($chunk) use (&$byNamePostcode, &$byName) {
                foreach ($chunk as $h) {
                    $norm = $this->normalizeName($h->hospital_name);
                    if ($norm === '') {
                        continue;
                    }
                    $byName[$norm][] = $h->id;
                    if ($h->postcode) {
                        $byNamePostcode[$norm.'|'.$h->postcode][] = $h->id;
                    }
                }
            });

        return [$byNamePostcode, $byName];
    }

    /**
     * @param  array<string,array<int,int>>  $byNamePostcode
     * @param  array<string,array<int,int>>  $byName
     */
    private function resolveHospitalId(string $norm, ?string $postcode, array $byNamePostcode, array $byName, int &$ambiguous): ?int
    {
        // 1순위: 기관명 + 우편번호 (유일할 때만)
        if ($postcode !== null && isset($byNamePostcode[$norm.'|'.$postcode])) {
            $ids = $byNamePostcode[$norm.'|'.$postcode];
            if (count($ids) === 1) {
                return $ids[0];
            }
            $ambiguous++;

            return null;
        }

        // 2순위(폴백): 기관명 전역 유일
        if (isset($byName[$norm]) && count($byName[$norm]) === 1) {
            return $byName[$norm][0];
        }

        if (isset($byName[$norm])) {
            $ambiguous++;
        }

        return null;
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
