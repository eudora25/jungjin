<?php

namespace App\Services;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * 월간 보고서 Excel 내보내기 (GAP-6) — 1개 파일 + 3개 시트.
 *
 * 시트: 거래처별 / 영업사원별 / 제품별. StreamedResponse 콜백 안에서
 * `stream()` 을 호출하면 php://output 으로 직접 기록한다.
 */
class MonthlyReportExcelExporter
{
    private const PARTNER_TYPE_LABELS = [
        'company' => '업체',
        'pharmacy' => '약국',
        'hospital' => '병원',
    ];

    public function stream(Collection $byCompany, Collection $bySales, Collection $byProduct, string $from, string $to): void
    {
        $spreadsheet = new Spreadsheet;

        $this->writeCompanySheet($spreadsheet->getActiveSheet(), $byCompany, $from, $to);
        $this->writeSalesSheet($spreadsheet->createSheet(), $bySales, $from, $to);
        $this->writeProductSheet($spreadsheet->createSheet(), $byProduct, $from, $to);

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }

    private function writeCompanySheet(Worksheet $sheet, Collection $rows, string $from, string $to): void
    {
        $sheet->setTitle('거래처별');
        $headerRow = $this->writeHeaderBlock($sheet, '거래처별 요약', $from, $to, $rows->count());

        $sheet->fromArray([
            ['거래처명', '파트너 유형', '실적 건수', '수량 합계', '매출 합계', '수수료 합계', '평균 수수료율(%)'],
        ], null, "A{$headerRow}", true);

        $r = $headerRow + 1;
        $totalLine = $totalQty = 0;
        $totalSub = $totalComm = 0.0;

        foreach ($rows as $row) {
            $sheet->fromArray([[
                $row['company_name'],
                self::PARTNER_TYPE_LABELS[$row['partner_type']] ?? $row['partner_type'],
                $row['line_count'],
                $row['total_quantity'],
                $row['total_subtotal'],
                $row['total_commission'],
                $row['avg_commission_rate'],
            ]], null, "A{$r}", true);

            $totalLine += $row['line_count'];
            $totalQty += $row['total_quantity'];
            $totalSub += $row['total_subtotal'];
            $totalComm += $row['total_commission'];
            $r++;
        }

        $sheet->fromArray([[
            '합계', '', $totalLine, $totalQty, $totalSub, $totalComm, $this->rate($totalSub, $totalComm),
        ]], null, "A{$r}", true);

        $this->autoSize($sheet, 'G');
    }

    private function writeSalesSheet(Worksheet $sheet, Collection $rows, string $from, string $to): void
    {
        $sheet->setTitle('영업사원별');
        $headerRow = $this->writeHeaderBlock($sheet, '영업사원별 요약', $from, $to, $rows->count());

        $sheet->fromArray([
            ['영업사원명', '실적 건수', '수량 합계', '매출 합계', '수수료 합계', '평균 수수료율(%)'],
        ], null, "A{$headerRow}", true);

        $r = $headerRow + 1;
        $totalLine = $totalQty = 0;
        $totalSub = $totalComm = 0.0;

        foreach ($rows as $row) {
            $sheet->fromArray([[
                $row['user_name'],
                $row['line_count'],
                $row['total_quantity'],
                $row['total_subtotal'],
                $row['total_commission'],
                $row['avg_commission_rate'],
            ]], null, "A{$r}", true);

            $totalLine += $row['line_count'];
            $totalQty += $row['total_quantity'];
            $totalSub += $row['total_subtotal'];
            $totalComm += $row['total_commission'];
            $r++;
        }

        $sheet->fromArray([[
            '합계', $totalLine, $totalQty, $totalSub, $totalComm, $this->rate($totalSub, $totalComm),
        ]], null, "A{$r}", true);

        $this->autoSize($sheet, 'F');
    }

    private function writeProductSheet(Worksheet $sheet, Collection $rows, string $from, string $to): void
    {
        $sheet->setTitle('제품별');
        $headerRow = $this->writeHeaderBlock($sheet, '제품별 요약', $from, $to, $rows->count());

        $sheet->fromArray([
            ['제품명', '보험코드', '제조사', '실적 건수', '수량 합계', '매출 합계', '수수료 합계'],
        ], null, "A{$headerRow}", true);

        $r = $headerRow + 1;
        $totalLine = $totalQty = 0;
        $totalSub = $totalComm = 0.0;

        foreach ($rows as $row) {
            $sheet->fromArray([[
                $row['product_name'],
                $row['insurance_code'],
                $row['manufacturer'],
                $row['line_count'],
                $row['total_quantity'],
                $row['total_subtotal'],
                $row['total_commission'],
            ]], null, "A{$r}", true);

            $totalLine += $row['line_count'];
            $totalQty += $row['total_quantity'];
            $totalSub += $row['total_subtotal'];
            $totalComm += $row['total_commission'];
            $r++;
        }

        $sheet->fromArray([[
            '합계', '', '', $totalLine, $totalQty, $totalSub, $totalComm,
        ]], null, "A{$r}", true);

        $this->autoSize($sheet, 'G');
    }

    /**
     * 시트 상단 공통 헤더 블록을 쓰고, 컬럼 헤더가 시작될 행 번호를 반환한다.
     */
    private function writeHeaderBlock(Worksheet $sheet, string $title, string $from, string $to, int $count): int
    {
        $sheet->fromArray([
            [$title],
            ['기간', "{$from} ~ {$to}"],
            ['기준', '승인 완료 실적(approved)'],
            ['생성일시', now()->toDateTimeString()],
            ['건수', $count],
        ], null, 'A1', true);

        return 7; // 1~5 헤더 블록 + 6 공백 → 7 부터 컬럼 헤더
    }

    private function autoSize(Worksheet $sheet, string $lastColumn): void
    {
        foreach (range('A', $lastColumn) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function rate(float $subtotal, float $commission): float
    {
        return $subtotal > 0 ? round($commission / $subtotal * 100, 1) : 0.0;
    }
}
