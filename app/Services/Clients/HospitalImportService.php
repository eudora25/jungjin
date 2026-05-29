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

            while (($cols = fgetcsv($handle)) !== false) {
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

                $status = $rawStatus ? $this->mapStatus($rawStatus) : null;
                $kind = $this->trimOrNull($row['의료기관종별명'] ?? $row['업태구분명'] ?? null);
                $type = $kind ? $this->mapHospitalType($kind) : null;

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

                while (($cols = fgetcsv($handle)) !== false) {
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

                    $kind = $this->trimOrNull($row['의료기관종별명'] ?? $row['업태구분명'] ?? null);
                    $type = $kind ? $this->mapHospitalType($kind) : null;
                    $specialty = $this->firstSpecialty($this->trimOrNull($row['진료과목내용명'] ?? null));

                    $batch[] = [
                        'hospital_code' => $code,
                        'hospital_name' => $name,
                        'hospital_type' => $type,
                        'specialty' => $specialty,
                        'postcode' => $this->trimOrNull($row['도로명우편번호'] ?? $row['소재지우편번호'] ?? null),
                        'address' => $this->trimOrNull($row['도로명주소'] ?? $row['지번주소'] ?? null),
                        'phone' => $this->trimOrNull($row['전화번호'] ?? null),
                        'status' => $this->mapStatus($rawStatus),
                        'created_by' => $userId,
                        'updated_by' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'deleted_at' => null,
                    ];

                    if (count($batch) >= $batchSize) {
                        DB::table('hospitals')->upsert(
                            $batch,
                            ['hospital_code'],
                            [
                                'hospital_name',
                                'hospital_type',
                                'specialty',
                                'postcode',
                                'address',
                                'phone',
                                'status',
                                'updated_by',
                                'updated_at',
                                'deleted_at',
                            ],
                        );
                        $batch = [];
                    }
                }

                if (! empty($batch)) {
                    DB::table('hospitals')->upsert(
                        $batch,
                        ['hospital_code'],
                        [
                            'hospital_name',
                            'hospital_type',
                            'specialty',
                            'postcode',
                            'address',
                            'phone',
                            'status',
                            'updated_by',
                            'updated_at',
                            'deleted_at',
                        ],
                    );
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

    private function mapStatus(string $raw): string
    {
        if (str_contains($raw, '영업') && ! str_contains($raw, '폐업') && ! str_contains($raw, '취소')) {
            return 'active';
        }

        return 'inactive';
    }

    private function mapHospitalType(string $kind): ?string
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
}

