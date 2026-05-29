<?php

namespace App\Services\Settlement;

use App\Models\Settlement;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SettlementExcelExporter
{
    /**
     * Writes an .xlsx binary to stdout (for StreamedResponse).
     */
    public function stream(Settlement $settlement): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Settlement');

        $paymentMethodLabel = match ($settlement->payment_method) {
            Settlement::PAYMENT_METHOD_BANK_TRANSFER => '계좌이체',
            Settlement::PAYMENT_METHOD_CASH => '현금',
            Settlement::PAYMENT_METHOD_OTHER => '기타',
            default => null,
        };

        $sheet->fromArray([
            ['정산번호', $settlement->settlement_no],
            ['정산월', $settlement->period_month],
            ['거래처', $settlement->company?->company_name],
            ['사업자번호', $settlement->company?->business_registration_number],
            ['상태', $settlement->status],
            ['라인수', $settlement->line_count],
            ['수량 합계', $settlement->total_quantity],
            ['매출 합계', (float) $settlement->total_subtotal],
            ['수수료 합계', (float) $settlement->total_commission],
            ['계산일시', $settlement->calculated_at?->toDateTimeString()],
            ['지급일', $settlement->paid_on?->toDateString()],
            ['지급 수단', $paymentMethodLabel],
            ['지급 묶음(Batch)', $settlement->payment_batch_no],
            ['지급 메모', $settlement->payment_note],
        ], null, 'A1', true);

        $startRow = 17;
        $sheet->fromArray([
            ['실적번호', '실적ID', '제품명', '보험코드', '수량', '단가(스냅샷)', '매출', '수수료율(스냅샷)', '수수료'],
        ], null, "A{$startRow}", true);

        $r = $startRow + 1;
        foreach ($settlement->lines as $line) {
            $perf = $line->performance;
            $prod = $perf?->product;

            $sheet->fromArray([[
                $perf?->performance_no,
                $line->performance_id,
                $prod?->product_name,
                $prod?->insurance_code,
                (int) $line->quantity,
                (float) $line->snapshot_unit_price,
                (float) $line->subtotal,
                $line->snapshot_commission_rate !== null ? (float) $line->snapshot_commission_rate : null,
                $line->commission_amount !== null ? (float) $line->commission_amount : null,
            ]], null, "A{$r}", true);

            $r++;
        }

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }
}

