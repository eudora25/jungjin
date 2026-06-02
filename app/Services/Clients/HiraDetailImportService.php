<?php

namespace App\Services\Clients;

use App\Models\Hospital;
use Illuminate\Support\Facades\DB;

/**
 * 심평원(HIRA) 의료기관별 상세정보(B-3~12) 정규화 적재.
 * 암호화요양기호(ykiho)로 기존 병의원에 연결 — 매칭 안 된 ykiho 행은 스킵(리포트 카운트).
 *
 * - 1:N (진료과목·장비·교통 등): hospital 별 기존 행 1회 삭제 후 삽입(재실행 멱등).
 * - 1:1 (시설·세부): hospital_id 유니크 upsert.
 */
class HiraDetailImportService
{
    private const BATCH = 500;

    public const TYPES = [
        'specialties', 'equipments', 'facilities', 'hours', 'transports',
        'nursing_grades', 'meal_surcharges', 'special_treatments', 'specialized_fields', 'other_staff',
    ];

    /**
     * @return array{type:string, total:int, resolved:int, skipped_unmatched:int, written:int}
     */
    public function import(string $type, string $xlsxPath): array
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException("알 수 없는 상세 유형: {$type}");
        }

        $ykihoMap = $this->ykihoMap();
        $config = $this->config($type);
        $isOne = $config['mode'] === 'one';

        $total = 0;
        $resolved = 0;
        $skipped = 0;
        $written = 0;
        $cleared = [];   // 1:N 에서 hospital_id 별 최초 삭제 여부
        $batch = [];
        $now = now();

        foreach (XlsxRowReader::rows($xlsxPath) as $row) {
            $total++;

            $ykiho = trim((string) ($row['암호화요양기호'] ?? ''));
            $hospitalId = $ykiho === '' ? null : ($ykihoMap[$ykiho] ?? null);

            if ($hospitalId === null) {
                $skipped++;

                continue;
            }
            $resolved++;

            $payload = ($config['build'])($row);
            $payload['hospital_id'] = $hospitalId;
            $payload['created_at'] = $now;
            $payload['updated_at'] = $now;

            if (! $isOne && ! isset($cleared[$hospitalId])) {
                DB::table($config['table'])->where('hospital_id', $hospitalId)->delete();
                $cleared[$hospitalId] = true;
            }

            $batch[] = $payload;
            if (count($batch) >= self::BATCH) {
                $written += $this->flush($config, $batch, $isOne);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            $written += $this->flush($config, $batch, $isOne);
        }

        return [
            'type' => $type,
            'total' => $total,
            'resolved' => $resolved,
            'skipped_unmatched' => $skipped,
            'written' => $written,
        ];
    }

    /**
     * @param  array<string,mixed>  $config
     * @param  array<int,array<string,mixed>>  $batch
     */
    private function flush(array $config, array $batch, bool $isOne): int
    {
        DB::transaction(function () use ($config, $batch, $isOne) {
            if ($isOne) {
                DB::table($config['table'])->upsert($batch, ['hospital_id'], $config['update']);
            } else {
                // 원천 데이터에 동일 (기관,코드) 중복 행이 존재 → unique 충돌 무시하고 1건만 적재
                DB::table($config['table'])->insertOrIgnore($batch);
            }
        });

        return count($batch);
    }

    /**
     * @return array<string,int> ykiho => hospital_id
     */
    private function ykihoMap(): array
    {
        $map = [];
        Hospital::query()
            ->whereNotNull('ykiho')
            ->select(['id', 'ykiho'])
            ->orderBy('id')
            ->chunk(5000, function ($chunk) use (&$map) {
                foreach ($chunk as $h) {
                    $map[$h->ykiho] = $h->id;
                }
            });

        return $map;
    }

    /**
     * @return array{table:string, mode:string, update:array<int,string>, build:callable}
     */
    private function config(string $type): array
    {
        return match ($type) {
            'specialties' => [
                'table' => 'hospital_specialties',
                'mode' => 'many',
                'update' => [],
                'build' => fn (array $r) => [
                    'dept_code' => $this->s($r, '진료과목코드'),
                    'dept_name' => $this->s($r, '진료과목코드명'),
                    'specialist_count' => $this->i($r, '과목별 전문의수'),
                    'selective_doctor_count' => $this->i($r, '선택진료 의사수'),
                ],
            ],
            'equipments' => [
                'table' => 'hospital_equipments',
                'mode' => 'many',
                'update' => [],
                'build' => fn (array $r) => [
                    'equipment_code' => $this->s($r, '장비코드'),
                    'equipment_name' => $this->s($r, '장비코드명'),
                    'quantity' => $this->i($r, '장비대수'),
                ],
            ],
            'transports' => [
                'table' => 'hospital_transports',
                'mode' => 'many',
                'update' => [],
                'build' => fn (array $r) => [
                    'transport_type' => $this->s($r, '교통편명'),
                    'route_no' => $this->s($r, '노선번호'),
                    'stop_name' => $this->s($r, '하차지점'),
                    'direction' => $this->s($r, '방향'),
                    'distance' => $this->s($r, '거리'),
                    'remarks' => $this->s($r, '비고'),
                ],
            ],
            'nursing_grades' => [
                'table' => 'hospital_nursing_grades',
                'mode' => 'many',
                'update' => [],
                'build' => fn (array $r) => [
                    'insurance_type_code' => $this->s($r, '유형코드'),
                    'insurance_type_name' => $this->s($r, '유형코드명'),
                    'nursing_grade' => $this->s($r, '간호등급'),
                ],
            ],
            'meal_surcharges' => [
                'table' => 'hospital_meal_surcharges',
                'mode' => 'many',
                'update' => [],
                'build' => fn (array $r) => [
                    'type_code' => $this->s($r, '유형코드'),
                    'type_name' => $this->s($r, '유형코드명'),
                    'general_meal_surcharge' => $this->yn($r, '일반식 가산여부'),
                    'calc_headcount' => $this->i($r, '산정인원수'),
                    'therapeutic_meal_grade' => $this->s($r, '치료식 등급'),
                ],
            ],
            'special_treatments' => [
                'table' => 'hospital_special_treatments',
                'mode' => 'many',
                'update' => [],
                'build' => fn (array $r) => [
                    'search_code' => $this->s($r, '검색코드'),
                    'search_name' => $this->s($r, '검색코드명'),
                ],
            ],
            'specialized_fields' => [
                'table' => 'hospital_specialized_fields',
                'mode' => 'many',
                'update' => [],
                'build' => fn (array $r) => [
                    'field_code' => $this->s($r, '검색코드'),
                    'field_name' => $this->s($r, '검색코드명'),
                ],
            ],
            'other_staff' => [
                'table' => 'hospital_other_staff',
                'mode' => 'many',
                'update' => [],
                'build' => fn (array $r) => [
                    'staff_code' => $this->s($r, '기타인력코드'),
                    'staff_name' => $this->s($r, '기타인력코드명'),
                    'staff_count' => $this->i($r, '기타인력수'),
                ],
            ],
            'facilities' => [
                'table' => 'hospital_facilities',
                'mode' => 'one',
                'update' => [
                    'establishment_code', 'establishment_name', 'general_upper_beds', 'general_normal_beds',
                    'adult_icu_beds', 'child_icu_beds', 'newborn_icu_beds', 'delivery_room_beds',
                    'operating_room_beds', 'emergency_room_beds', 'physical_therapy_beds',
                    'psych_closed_upper_beds', 'psych_closed_normal_beds', 'psych_open_upper_beds',
                    'psych_open_normal_beds', 'isolation_beds', 'sterile_treatment_beds', 'updated_at',
                ],
                'build' => fn (array $r) => [
                    'establishment_code' => $this->s($r, '설립구분코드'),
                    'establishment_name' => $this->s($r, '설립구분코드명'),
                    'general_upper_beds' => $this->i($r, '일반입원실상급병상수'),
                    'general_normal_beds' => $this->i($r, '일반입원실일반병상수'),
                    'adult_icu_beds' => $this->i($r, '성인중환자병상수'),
                    'child_icu_beds' => $this->i($r, '소아중환자병상수'),
                    'newborn_icu_beds' => $this->i($r, '신생아중환자병상수'),
                    'delivery_room_beds' => $this->i($r, '분만실병상수'),
                    'operating_room_beds' => $this->i($r, '수술실병상수'),
                    'emergency_room_beds' => $this->i($r, '응급실병상수'),
                    'physical_therapy_beds' => $this->i($r, '물리치료실병상수'),
                    'psych_closed_upper_beds' => $this->i($r, '정신과폐쇄상급병상수'),
                    'psych_closed_normal_beds' => $this->i($r, '정신과폐쇄일반병상수'),
                    'psych_open_upper_beds' => $this->i($r, '정신과개방상급병상수'),
                    'psych_open_normal_beds' => $this->i($r, '정신과개방일반병상수'),
                    'isolation_beds' => $this->i($r, '격리병실병상수'),
                    'sterile_treatment_beds' => $this->i($r, '무균치료실병상수'),
                ],
            ],
            'hours' => [
                'table' => 'hospital_hours',
                'mode' => 'one',
                'update' => [
                    'location_building', 'location_direction', 'location_distance', 'parking_capacity',
                    'parking_fee_required', 'parking_note', 'closed_sunday', 'closed_holiday',
                    'er_day_available', 'er_day_phone1', 'er_day_phone2', 'er_night_available',
                    'er_night_phone1', 'er_night_phone2', 'lunch_weekday', 'lunch_saturday',
                    'reception_weekday', 'reception_saturday', 'treatment_hours', 'updated_at',
                ],
                'build' => fn (array $r) => [
                    'location_building' => $this->s($r, '위치_공공건물(장소)명'),
                    'location_direction' => $this->s($r, '위치_방향'),
                    'location_distance' => $this->s($r, '위치_거리'),
                    'parking_capacity' => $this->i($r, '주차_가능대수'),
                    'parking_fee_required' => $this->yn($r, '주차_비용 부담여부'),
                    'parking_note' => $this->s($r, '주차_기타 안내사항'),
                    'closed_sunday' => $this->s($r, '휴진안내_일요일'),
                    'closed_holiday' => $this->s($r, '휴진안내_공휴일'),
                    'er_day_available' => $this->yn($r, '응급실_주간_운영여부'),
                    'er_day_phone1' => $this->s($r, '응급실_주간_전화번호1'),
                    'er_day_phone2' => $this->s($r, '응급실_주간_전화번호2'),
                    'er_night_available' => $this->yn($r, '응급실_야간_운영여부'),
                    'er_night_phone1' => $this->s($r, '응급실_야간_전화번호1'),
                    'er_night_phone2' => $this->s($r, '응급실_야간_전화번호2'),
                    'lunch_weekday' => $this->s($r, '점심시간_평일'),
                    'lunch_saturday' => $this->s($r, '점심시간_토요일'),
                    'reception_weekday' => $this->s($r, '접수시간_평일'),
                    'reception_saturday' => $this->s($r, '접수시간_토요일'),
                    'treatment_hours' => $this->treatmentHours($r),
                ],
            ],
        };
    }

    private function treatmentHours(array $r): string
    {
        $days = [
            'sun' => '일요일', 'mon' => '월요일', 'tue' => '화요일', 'wed' => '수요일',
            'thu' => '목요일', 'fri' => '금요일', 'sat' => '토요일',
        ];
        $out = [];
        foreach ($days as $key => $ko) {
            $start = $this->s($r, "진료시작시간_{$ko}");
            $end = $this->s($r, "진료종료시간_{$ko}");
            if ($start !== null || $end !== null) {
                $out[$key] = ['start' => $start, 'end' => $end];
            }
        }

        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    private function s(array $r, string $key): ?string
    {
        $v = trim((string) ($r[$key] ?? ''));

        return $v === '' ? null : $v;
    }

    private function i(array $r, string $key): ?int
    {
        $v = $this->s($r, $key);
        if ($v === null || ! preg_match('/-?\d+/', $v, $m)) {
            return null;
        }
        $n = (int) $m[0];

        return $n < 0 ? null : $n;
    }

    private function yn(array $r, string $key): ?bool
    {
        $v = $this->s($r, $key);
        if ($v === null) {
            return null;
        }

        return in_array(mb_strtoupper($v), ['Y', '1', 'TRUE', '예', 'O'], true);
    }
}
