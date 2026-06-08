<?php

namespace App\Services\Clients;

/**
 * 병의원 인허가 데이터(행안부) 한 행을 hospitals upsert 페이로드로 변환하는 공용 매퍼.
 *
 * CSV(한글 헤더) 경로와 MOIS API(영문 필드) 경로가 각자 어댑터로 "논리 키" 연관배열을
 * 구성한 뒤 본 매퍼를 호출한다 → 상태·일자·종별·정규화 규칙을 한 곳에서 관리한다.
 *
 * 논리 키: code, name, status_raw, kind, specialties, postcode, address, phone,
 *   opened_on, closed_on, suspend_begin_on, suspend_end_on, doctor_count, bed_count,
 *   inpatient_room_count, total_area, license_authority_code, source_synced_at,
 *   latitude, longitude(선택 — API 경로에서 WGS84 변환 후 주입)
 */
class HospitalRowMapper
{
    /**
     * 논리 키 연관배열을 hospitals upsert 페이로드로 변환.
     *
     * @param  array<string,mixed>  $logical
     * @return array<string,mixed>
     */
    public function buildPayload(array $logical, ?int $userId, mixed $now): array
    {
        $kind = $this->trimOrNull($logical['kind'] ?? null);

        $payload = [
            'hospital_code' => $this->trimOrNull($logical['code'] ?? null),
            'hospital_name' => $this->trimOrNull($logical['name'] ?? null),
            'hospital_type' => $kind ? $this->mapHospitalType($kind) : null,
            'specialty' => $this->firstSpecialty($this->trimOrNull($logical['specialties'] ?? null)),
            'postcode' => $this->trimOrNull($logical['postcode'] ?? null),
            'address' => $this->trimOrNull($logical['address'] ?? null),
            'phone' => $this->trimOrNull($logical['phone'] ?? null),
            'status' => $this->mapStatus((string) ($logical['status_raw'] ?? '')),
            'opened_on' => $this->parseDate($logical['opened_on'] ?? null),
            'closed_on' => $this->parseDate($logical['closed_on'] ?? null),
            'suspend_begin_on' => $this->parseDate($logical['suspend_begin_on'] ?? null),
            'suspend_end_on' => $this->parseDate($logical['suspend_end_on'] ?? null),
            'doctor_count' => $this->parseInt($logical['doctor_count'] ?? null),
            'bed_count' => $this->parseInt($logical['bed_count'] ?? null),
            'inpatient_room_count' => $this->parseInt($logical['inpatient_room_count'] ?? null),
            'total_area' => $this->parseDecimal($logical['total_area'] ?? null),
            'license_authority_code' => $this->trimOrNull($logical['license_authority_code'] ?? null),
            'source_synced_at' => $this->parseDateTime($logical['source_synced_at'] ?? null),
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ];

        // 좌표는 API 경로(WGS84 변환 완료)에서만 주입. CSV(TM 좌표)는 위경도 미저장 → 키 없음.
        if (array_key_exists('latitude', $logical)) {
            $payload['latitude'] = $logical['latitude'];
        }
        if (array_key_exists('longitude', $logical)) {
            $payload['longitude'] = $logical['longitude'];
        }

        return $payload;
    }

    /**
     * upsert 시 갱신할 컬럼 목록.
     *
     * @return array<int,string>
     */
    public function updateColumns(bool $withCoords = false): array
    {
        $columns = [
            'hospital_name', 'hospital_type', 'specialty', 'postcode', 'address', 'phone', 'status',
            'opened_on', 'closed_on', 'suspend_begin_on', 'suspend_end_on',
            'doctor_count', 'bed_count', 'inpatient_room_count', 'total_area',
            'license_authority_code', 'source_synced_at',
            'updated_by', 'updated_at', 'deleted_at',
        ];

        if ($withCoords) {
            $columns[] = 'latitude';
            $columns[] = 'longitude';
        }

        return $columns;
    }

    public function mapStatus(string $raw): string
    {
        if (str_contains($raw, '영업') && ! str_contains($raw, '폐업') && ! str_contains($raw, '취소')) {
            return 'active';
        }

        return 'inactive';
    }

    public function mapHospitalType(string $kind): ?string
    {
        if (str_contains($kind, '종합병원')) {
            return 'general_hospital';
        }

        if (str_contains($kind, '치과')) {
            return 'dental';
        }

        if (str_contains($kind, '한방') || str_contains($kind, '한의')) {
            return 'oriental';
        }

        if (str_contains($kind, '의원')) {
            return 'clinic';
        }

        if (str_contains($kind, '병원')) {
            return 'hospital';
        }

        return 'other';
    }

    public function trimOrNull(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));

        return $s === '' ? null : $s;
    }

    private function firstSpecialty(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        // "내과, 신경과, ..." → 첫 항목만 (DB 100자 제한)
        $first = trim((string) strtok($raw, ','));
        if ($first === '') {
            return null;
        }

        return mb_substr($first, 0, 100);
    }

    private function parseDate(mixed $v): ?string
    {
        $s = $this->trimOrNull($v);
        if ($s === null) {
            return null;
        }
        // 'YYYY-MM-DD' 또는 'YYYYMMDD'
        if (preg_match('/^(\d{4})-?(\d{2})-?(\d{2})/', $s, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        return null;
    }

    private function parseDateTime(mixed $v): ?string
    {
        $s = $this->trimOrNull($v);
        if ($s === null) {
            return null;
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})/', $s, $m)) {
            return "{$m[1]} {$m[2]}";
        }
        // MOIS DAT_UPDT_PNT: 구분자 없는 yyyyMMddHHmmss (14자리)
        if (preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})$/', $s, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}:{$m[5]}:{$m[6]}";
        }

        return $this->parseDate($s);
    }

    private function parseInt(mixed $v): ?int
    {
        $s = $this->trimOrNull($v);
        if ($s === null || ! preg_match('/-?\d+/', $s, $m)) {
            return null;
        }
        $n = (int) $m[0];

        return $n < 0 ? null : $n;
    }

    private function parseDecimal(mixed $v): ?string
    {
        $s = $this->trimOrNull($v);
        if ($s === null) {
            return null;
        }
        $s = str_replace(',', '', $s);

        return is_numeric($s) ? $s : null;
    }
}
