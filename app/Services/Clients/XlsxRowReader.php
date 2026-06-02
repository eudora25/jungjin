<?php

namespace App\Services\Clients;

use OpenSpout\Reader\XLSX\Reader;

/**
 * 대용량 xlsx 를 메모리 효율적으로 스트리밍 — 헤더 1행 기준 연관배열 제너레이터.
 * 심평원(HIRA) 공공데이터 적재용 (수십만 행 파일 대응).
 */
class XlsxRowReader
{
    /**
     * 각 행을 [헤더 => 값] 연관배열로 yield. (헤더 행은 제외)
     *
     * @return \Generator<int, array<string,string>>
     */
    public static function rows(string $absolutePath): \Generator
    {
        $reader = new Reader;
        $reader->open($absolutePath);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $headers = [];
                $headerSeen = false;

                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(
                        fn ($v) => self::stringify($v),
                        $row->toArray(),
                    );

                    if (! $headerSeen) {
                        $headers = $cells;
                        $headerSeen = true;

                        continue;
                    }

                    // 완전 빈 행 스킵
                    if (count(array_filter($cells, fn ($c) => $c !== '')) === 0) {
                        continue;
                    }

                    $assoc = [];
                    foreach ($headers as $i => $h) {
                        $assoc[$h] = $cells[$i] ?? '';
                    }

                    yield $assoc;
                }

                // 첫 시트만 처리
                break;
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * 셀 값을 안전하게 문자열화. 날짜 셀은 DateTimeInterface 로 들어오므로 포맷, 숫자/불리언도 처리.
     */
    private static function stringify(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_string($v)) {
            return trim($v);
        }
        if ($v instanceof \DateTimeInterface) {
            $hms = $v->format('H:i:s');

            return $hms === '00:00:00' ? $v->format('Y-m-d') : $v->format('Y-m-d H:i:s');
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }

        return trim((string) $v);
    }
}
