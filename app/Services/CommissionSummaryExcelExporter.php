<?php

namespace App\Services;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * 영업사원별 수수료 합계 Excel 내보내기 (GAP-3).
 *
 * StreamedResponse 콜백 안에서 `streamSummary()` 를 호출하면 php://output 으로 직접 기록한다.
 */
class CommissionSummaryExcelExporter
{
    /**
     * @param  Collection<int, array{
     *     user_id: int,
     *     user_name: string,
     *     line_count: int,
     *     total_quantity: int,
     *     total_subtotal: float,
     *     total_commission: float,
     * }>  $rows
     */
    public function streamSummary(Collection $rows, string $from, string $to): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('CommissionSummary');

        $sheet->fromArray([
            ['기간', "{$from} ~ {$to}"],
            ['생성일시', now()->toDateTimeString()],
            ['대상자 수', $rows->count()],
        ], null, 'A1', true);

        $headerRow = 5;
        $sheet->fromArray([
            ['영업사원ID', '영업사원명', '실적 건수', '수량 합계', '매출 합계', '수수료 합계'],
        ], null, "A{$headerRow}", true);

        $r = $headerRow + 1;
        $totalLine = 0;
        $totalQty = 0;
        $totalSub = 0.0;
        $totalComm = 0.0;

        foreach ($rows as $row) {
            $sheet->fromArray([[
                $row['user_id'],
                $row['user_name'],
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
            '', '합계', $totalLine, $totalQty, $totalSub, $totalComm,
        ]], null, "A{$r}", true);

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }
}
