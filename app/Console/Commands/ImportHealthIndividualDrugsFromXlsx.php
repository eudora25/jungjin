<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Reader\XLSX\Sheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ImportHealthIndividualDrugsFromXlsx extends Command
{
    protected $signature = 'health:import-individual-drugs
        {path? : 개별의약품 목록 xlsx 전체 경로 (미지정 시 docs 폴더에서 자동 탐색)}
        {--snapshot-date= : 데이터 기준일 YYYY-MM-DD (미지정 시 파일명 8자리 숫자)}
        {--replace : 동일 스냅샷일 기존 행 삭제 후 적재}';

    protected $description = '「개별의약품 목록」xlsx → health_individual_drugs 적재 (OpenSpout 스트리밍)';

    private const EXPECTED_HEADERS = [
        '품목기준코드',
        '의약품명 적용 규칙명',
        '의약품 생성 적용 규칙명',
        '대표표준코드',
        '개별 의약품명',
        '의약품허가품목명',
        '제약업체명',
        '사업자등록번호',
        '업체상태',
        '품목허가번호',
        '품목허가일자',
        '취소/취하상태',
        '취소/취하일자',
        '품목구분',
        '전문일반구분',
        '신고허가구분',
        '완제원료구분',
        '마약류구분',
        '약효분류',
        'ATC코드',
        '주성분명',
        '주성분수',
        '신약여부',
        '희귀의약품여부',
        '주성분코드',
        '현재보험코드',
        '현재보험약가',
        '현재보험약가적용시작일자',
        '급여매핑상태',
        '대조약여부',
        '대조약구분명',
        '대조약공고일자',
        '생동성인정품목여부',
        '생동성인정품목공고일자',
    ];

    private const TARGET_SHEET_NAME = '개별의약품 목록';

    public function handle(): int
    {
        ini_set('memory_limit', '512M');

        $path = $this->resolvePath($this->argument('path'));
        if ($path === null || ! is_readable($path)) {
            $this->error('xlsx 파일을 찾을 수 없거나 읽을 수 없습니다.');

            return self::FAILURE;
        }

        $snapshotDate = $this->resolveSnapshotDate($path, $this->option('snapshot-date'));
        if ($snapshotDate === null) {
            $this->error('스냅샷 기준일을 --snapshot-date 로 지정하거나, 파일명에 YYYYMMDD 형식을 넣어 주세요.');

            return self::FAILURE;
        }

        $options = new Options;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $options->SHOULD_FORMAT_DATES = true;

        $reader = new Reader($options);

        try {
            $reader->open($path);
        } catch (\Throwable $e) {
            $this->error('xlsx 열기 실패: '.$e->getMessage());

            return self::FAILURE;
        }

        $sheet = $this->resolveTargetSheet($reader);
        if ($sheet === null) {
            $reader->close();
            $this->error('시트「'.self::TARGET_SHEET_NAME.'」를 찾을 수 없습니다.');

            return self::FAILURE;
        }

        if ($this->option('replace')) {
            $deleted = DB::table('health_individual_drugs')
                ->whereDate('data_snapshot_date', $snapshotDate)
                ->delete();
            $this->info(sprintf('동일 스냅샷(%s) 기존 %d건 삭제.', $snapshotDate, $deleted));
        }

        $importedAt = now()->toDateTimeString();
        $ts = now()->toDateTimeString();
        $basename = basename($path);

        $rowIterator = $sheet->getRowIterator();
        $rowIterator->rewind();
        if (! $rowIterator->valid()) {
            $reader->close();
            $this->error('시트에 행이 없습니다.');

            return self::FAILURE;
        }

        /** @var Row $headerRow */
        $headerRow = $rowIterator->current();
        try {
            $this->assertHeaderRow($headerRow);
        } catch (\RuntimeException $e) {
            $reader->close();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $inserted = 0;
        $batch = [];
        $batchSize = 500;
        $rowIterator->next();

        $this->info('데이터 행 적재 중…');

        while ($rowIterator->valid()) {
            /** @var Row $row */
            $row = $rowIterator->current();
            $excelRowIndex = $rowIterator->key();

            $batch[] = $this->buildRowRecord(
                $row,
                $excelRowIndex,
                $snapshotDate,
                $basename,
                $importedAt,
                $ts,
            );

            if (count($batch) >= $batchSize) {
                DB::table('health_individual_drugs')->insert($batch);
                $inserted += count($batch);
                $batch = [];
                if ($inserted % 5000 === 0) {
                    $this->line("  … {$inserted}건");
                }
            }

            $rowIterator->next();
        }

        if ($batch !== []) {
            DB::table('health_individual_drugs')->insert($batch);
            $inserted += count($batch);
        }
        $reader->close();

        $this->newLine(2);
        $this->info(sprintf('적재 완료: %d건 (스냅샷 %s)', $inserted, $snapshotDate));

        return self::SUCCESS;
    }

    private function resolveTargetSheet(Reader $reader): ?Sheet
    {
        $first = null;
        foreach ($reader->getSheetIterator() as $sheet) {
            if ($first === null) {
                $first = $sheet;
            }
            if ($sheet->getName() === self::TARGET_SHEET_NAME) {
                return $sheet;
            }
        }

        return $first;
    }

    private function resolvePath(?string $argumentPath): ?string
    {
        if ($argumentPath !== null && $argumentPath !== '') {
            $path = $argumentPath;
            if (! str_starts_with($path, '/')) {
                $path = base_path($path);
            }

            return is_file($path) ? $path : null;
        }

        $docs = base_path('docs');
        $candidates = array_merge(
            glob($docs.'/*목록*.xlsx') ?: [],
            glob($docs.'/*.xlsx') ?: [],
        );
        $candidates = array_values(array_unique($candidates));
        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $candidates[0];
    }

    private function resolveSnapshotDate(string $path, ?string $optionDate): ?string
    {
        if ($optionDate !== null && $optionDate !== '') {
            try {
                return Carbon::parse($optionDate)->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        if (preg_match('/(\d{8})\s*\.xlsx$/u', basename($path), $m)) {
            $d = Carbon::createFromFormat('Ymd', $m[1], config('app.timezone'));

            return $d ? $d->toDateString() : null;
        }

        return null;
    }

    private function assertHeaderRow(Row $row): void
    {
        $cells = $this->rowTo34Values($row);
        for ($i = 0; $i < 34; $i++) {
            $cell = trim($this->scalarToString($cells[$i + 1] ?? null));
            $expected = self::EXPECTED_HEADERS[$i];
            if ($cell !== $expected) {
                throw new \RuntimeException(sprintf(
                    '헤더 불일치 (열 %d): 기대 [%s], 실제 [%s]',
                    $i + 1,
                    $expected,
                    $cell,
                ));
            }
        }
    }

    /**
     * @return array<int, mixed> 키 1..34
     */
    private function rowTo34Values(Row $row): array
    {
        $flat = $row->toArray();
        $out = [];
        for ($i = 1; $i <= 34; $i++) {
            $out[$i] = $flat[$i - 1] ?? null;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRowRecord(
        Row $row,
        int $excelRowIndex,
        string $snapshotDate,
        string $sourceFileName,
        string $importedAt,
        string $timestamps,
    ): array {
        $v = $this->rowTo34Values($row);

        return [
            'data_snapshot_date' => $snapshotDate,
            'source_row_number' => $excelRowIndex,
            'item_code' => $this->strCol($v[1] ?? null, 30),
            'drug_name_rule_name' => $this->strCol($v[2] ?? null, 255),
            'drug_creation_rule_name' => $this->strCol($v[3] ?? null, 255),
            'representative_standard_code' => $this->strCol($v[4] ?? null, 50),
            'individual_drug_name' => $this->strCol($v[5] ?? null, 500),
            'licensed_product_name' => $this->strCol($v[6] ?? null, 500),
            'manufacturer_name' => $this->strCol($v[7] ?? null, 200),
            'business_registration_number' => $this->strCol($v[8] ?? null, 20),
            'company_status' => $this->strCol($v[9] ?? null, 50),
            'license_item_number' => $this->strCol($v[10] ?? null, 50),
            'license_date' => $this->dateCol($v[11] ?? null),
            'cancel_status' => $this->strCol($v[12] ?? null, 50),
            'cancel_date' => $this->dateCol($v[13] ?? null),
            'item_category' => $this->strCol($v[14] ?? null, 50),
            'rx_otc_type' => $this->strCol($v[15] ?? null, 20),
            'report_permit_type' => $this->strCol($v[16] ?? null, 20),
            'finished_material_type' => $this->strCol($v[17] ?? null, 20),
            'narcotic_type' => $this->strCol($v[18] ?? null, 50),
            'efficacy_class' => $this->strCol($v[19] ?? null, 100),
            'atc_code' => $this->strCol($v[20] ?? null, 20),
            'main_ingredient_name' => $this->textCol($v[21] ?? null),
            'main_ingredient_count' => $this->strCol($v[22] ?? null, 20),
            'is_new_drug' => $this->strCol($v[23] ?? null, 20),
            'is_rare_drug' => $this->strCol($v[24] ?? null, 20),
            'main_ingredient_code' => $this->strCol($v[25] ?? null, 100),
            'current_insurance_code' => $this->strCol($v[26] ?? null, 50),
            'current_insurance_price' => $this->decimalCol($v[27] ?? null),
            'current_insurance_price_start_date' => $this->dateCol($v[28] ?? null),
            'reimbursement_mapping_status' => $this->strCol($v[29] ?? null, 50),
            'reference_drug_flag' => $this->strCol($v[30] ?? null, 20),
            'reference_drug_type_name' => $this->strCol($v[31] ?? null, 200),
            'reference_drug_notice_date' => $this->dateCol($v[32] ?? null),
            'bioequivalence_flag' => $this->strCol($v[33] ?? null, 20),
            'bioequivalence_notice_date' => $this->dateCol($v[34] ?? null),
            'source_file_name' => $this->strCol($sourceFileName, 255),
            'imported_at' => $importedAt,
            'created_at' => $timestamps,
            'updated_at' => $timestamps,
        ];
    }

    private function scalarToString(mixed $raw): string
    {
        if ($raw instanceof \DateTimeInterface) {
            return $raw->format('Y-m-d');
        }
        if (is_scalar($raw)) {
            return (string) $raw;
        }

        return '';
    }

    private function strCol(mixed $raw, int $max): ?string
    {
        if ($raw instanceof \DateTimeInterface) {
            $s = $raw->format('Y-m-d');
        } else {
            $s = trim($this->scalarToString($raw));
        }
        if ($s === '' || $s === '-') {
            return null;
        }
        if (mb_strlen($s) > $max) {
            return mb_substr($s, 0, $max);
        }

        return $s;
    }

    private function textCol(mixed $raw): ?string
    {
        if ($raw instanceof \DateTimeInterface) {
            $s = $raw->format('Y-m-d');
        } else {
            $s = trim($this->scalarToString($raw));
        }
        if ($s === '' || $s === '-') {
            return null;
        }
        $max = 60000;
        if (mb_strlen($s) > $max) {
            return mb_substr($s, 0, $max);
        }

        return $s;
    }

    private function dateCol(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        if ($raw instanceof \DateTimeInterface) {
            return $raw->format('Y-m-d');
        }
        if (is_numeric($raw)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $raw)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }
        $s = trim($this->scalarToString($raw));
        if ($s === '' || $s === '-') {
            return null;
        }
        try {
            return Carbon::parse($s)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function decimalCol(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            return number_format((float) $raw, 2, '.', '');
        }
        $s = str_replace([',', ' ', "\u{00a0}"], '', trim($this->scalarToString($raw)));
        if ($s === '' || $s === '-') {
            return null;
        }
        if (! is_numeric($s)) {
            return null;
        }

        return number_format((float) $s, 2, '.', '');
    }
}
