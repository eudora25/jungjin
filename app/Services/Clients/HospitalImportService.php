<?php

namespace App\Services\Clients;

use App\Models\Hospital;
use Illuminate\Support\Facades\DB;

class HospitalImportService
{
    public const REQUIRED_HEADERS = [
        '관리번호',
        '사업장명',
        '영업상태명',
    ];

    public const PREVIEW_LIMIT_DEFAULT = 200;

    public function __construct(private readonly HospitalRowMapper $mapper = new HospitalRowMapper) {}

    /**
     * @return array{headers: array<int,string>, row_count: int, summary: array<string,int>, results: array<int, array<string,mixed>>, errors: array<int,string>}
     */
    public function analyzeFile(string $absolutePath, int $previewLimit = self::PREVIEW_LIMIT_DEFAULT): array
    {
        $summary = ['create' => 0, 'update' => 0, 'inactive' => 0, 'error' => 0];
        $results = [];
        $errors = [];

        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('CSV 파일을 열 수 없습니다.');
        }

        try {
            $lineNo = 0;
            $headers = [];

            $this->attachEncodingFilter($handle);

            while (($cols = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $lineNo++;

                if ($lineNo === 1) {
                    $headers = array_map(fn ($c) => trim((string) $c), $cols);
                    $missing = $this->missingRequiredHeaders($headers);
                    if (! empty($missing)) {
                        $errors[] = '필수 컬럼이 없습니다: '.implode(', ', $missing);
                        break;
                    }

                    continue;
                }

                if (count($cols) === 1 && trim((string) ($cols[0] ?? '')) === '') {
                    continue;
                }

                $row = $this->rowAssoc($headers, $cols);
                $row['_line'] = $lineNo;

                $rowErrors = [];
                $code = $this->trimOrNull($row['관리번호'] ?? null);
                $name = $this->trimOrNull($row['사업장명'] ?? null);
                $rawStatus = $this->trimOrNull($row['영업상태명'] ?? null);

                if ($code === null) {
                    $rowErrors[] = '관리번호는 필수입니다.';
                }
                if ($name === null) {
                    $rowErrors[] = '사업장명은 필수입니다.';
                }
                if ($rawStatus === null) {
                    $rowErrors[] = '영업상태명은 필수입니다.';
                }

                $status = $rawStatus ? $this->mapper->mapStatus($rawStatus) : null;
                $kind = $this->trimOrNull($row['의료기관종별명'] ?? $row['업태구분명'] ?? null);
                $type = $kind ? $this->mapper->mapHospitalType($kind) : null;

                $existing = null;
                if ($code !== null) {
                    $existing = Hospital::withTrashed()->where('hospital_code', $code)->first();
                }

                $action = $existing ? 'update' : 'create';

                if (! empty($rowErrors)) {
                    $summary['error']++;
                    if (count($results) < $previewLimit) {
                        $results[] = [
                            'line' => $lineNo,
                            'action' => 'error',
                            'identifier' => $this->identifier($code, $name),
                            'hospital_type' => $type,
                            'status' => $status,
                            'errors' => $rowErrors,
                        ];
                    }

                    continue;
                }

                $summary[$action]++;
                if ($status === 'inactive') {
                    $summary['inactive']++;
                }

                if (count($results) < $previewLimit) {
                    $results[] = [
                        'line' => $lineNo,
                        'action' => $action,
                        'identifier' => $this->identifier($code, $name),
                        'hospital_type' => $type,
                        'status' => $status,
                        'errors' => [],
                    ];
                }
            }

            return [
                'headers' => $headers,
                'row_count' => max(0, $lineNo - 1),
                'summary' => $summary,
                'results' => $results,
                'errors' => $errors,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array{committed: bool, row_count: int, summary: array<string,int>, results: array<int, array<string,mixed>>, errors: array<int,string>}
     */
    public function importFile(string $absolutePath, ?int $userId = null): array
    {
        $analysis = $this->analyzeFile($absolutePath, self::PREVIEW_LIMIT_DEFAULT);
        if (! empty($analysis['errors']) || ($analysis['summary']['error'] ?? 0) > 0) {
            return ['committed' => false] + $analysis;
        }

        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('CSV 파일을 열 수 없습니다.');
        }

        try {
            $lineNo = 0;
            $headers = [];

            DB::transaction(function () use ($handle, &$lineNo, &$headers, $userId) {
                $this->attachEncodingFilter($handle);

                $now = now();
                $batch = [];
                $batchSize = 500;

                while (($cols = fgetcsv($handle, null, ',', '"', '')) !== false) {
                    $lineNo++;

                    if ($lineNo === 1) {
                        $headers = array_map(fn ($c) => trim((string) $c), $cols);
                        $missing = $this->missingRequiredHeaders($headers);
                        if (! empty($missing)) {
                            throw new \RuntimeException('필수 컬럼이 없습니다: '.implode(', ', $missing));
                        }

                        continue;
                    }

                    if (count($cols) === 1 && trim((string) ($cols[0] ?? '')) === '') {
                        continue;
                    }

                    $row = $this->rowAssoc($headers, $cols);

                    $code = $this->trimOrNull($row['관리번호'] ?? null);
                    $name = $this->trimOrNull($row['사업장명'] ?? null);
                    $rawStatus = $this->trimOrNull($row['영업상태명'] ?? null);

                    if ($code === null || $name === null || $rawStatus === null) {
                        throw new \RuntimeException("행 {$lineNo} 검증 실패");
                    }

                    $batch[] = $this->mapper->buildPayload($this->csvToLogical($row), $userId, $now);

                    if (count($batch) >= $batchSize) {
                        DB::table('hospitals')->upsert($batch, ['hospital_code'], $this->mapper->updateColumns());
                        $batch = [];
                    }
                }

                if (! empty($batch)) {
                    DB::table('hospitals')->upsert($batch, ['hospital_code'], $this->mapper->updateColumns());
                }
            });
        } finally {
            fclose($handle);
        }

        return ['committed' => true] + $analysis;
    }

    /**
     * @return array<int,string>
     */
    private function missingRequiredHeaders(array $headers): array
    {
        return array_values(array_diff(self::REQUIRED_HEADERS, $headers));
    }

    private function attachEncodingFilter(mixed $handle): void
    {
        @stream_filter_append($handle, 'convert.iconv.CP949/UTF-8', STREAM_FILTER_READ);
    }

    /**
     * @return array<string, string|null>
     */
    private function rowAssoc(array $headers, array $cols): array
    {
        $row = [];
        foreach ($headers as $i => $h) {
            $row[$h] = isset($cols[$i]) ? trim((string) $cols[$i]) : null;
        }

        return $row;
    }

    private function trimOrNull(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));

        return $s === '' ? null : $s;
    }

    private function identifier(?string $code, ?string $name): string
    {
        $parts = [];
        if ($code) {
            $parts[] = "관리번호={$code}";
        }
        if ($name) {
            $parts[] = "사업장명={$name}";
        }

        return implode(' / ', $parts);
    }

    /**
     * 인허가 CSV 한 행(한글 헤더)을 공용 매퍼의 "논리 키" 연관배열로 변환.
     * 좌표(A)는 TM 좌표계라 위경도(WGS84)에 저장하지 않는다(키 미포함) — 위경도는 심평원(B)/MOIS API 에서 채움.
     *
     * @param  array<string,string|null>  $row
     * @return array<string,mixed>
     */
    private function csvToLogical(array $row): array
    {
        return [
            'code' => $row['관리번호'] ?? null,
            'name' => $row['사업장명'] ?? null,
            'status_raw' => $row['영업상태명'] ?? null,
            'kind' => $row['의료기관종별명'] ?? $row['업태구분명'] ?? null,
            'specialties' => $row['진료과목내용명'] ?? null,
            'postcode' => $row['도로명우편번호'] ?? $row['소재지우편번호'] ?? null,
            'address' => $row['도로명주소'] ?? $row['지번주소'] ?? null,
            'phone' => $row['전화번호'] ?? null,
            'opened_on' => $row['인허가일자'] ?? null,
            'closed_on' => $row['폐업일자'] ?? null,
            'suspend_begin_on' => $row['휴업시작일자'] ?? null,
            'suspend_end_on' => $row['휴업종료일자'] ?? null,
            'doctor_count' => $row['의료인수'] ?? null,
            'bed_count' => $row['병상수'] ?? null,
            'inpatient_room_count' => $row['입원실수'] ?? null,
            'total_area' => $row['총면적'] ?? null,
            'license_authority_code' => $row['개방자치단체코드'] ?? null,
            'source_synced_at' => $row['데이터갱신시점'] ?? null,
        ];
    }
}
