<?php

namespace App\Http\Controllers;

use App\Services\MonthlyReportExcelExporter;
use App\Services\MonthlyReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 월간 보고서 (GAP-6).
 *
 *  - monthly        : admin 거래처/영업사원/제품 3종 요약 미리보기
 *  - exportMonthly  : 위 3종을 1개 파일 3개 시트 Excel 로 다운로드
 *
 * 상세 스펙: docs/modules/reports/MONTHLY_REPORT.md
 */
class ReportsController extends Controller
{
    public function __construct(
        private readonly MonthlyReportService $service,
        private readonly MonthlyReportExcelExporter $excel,
    ) {}

    public function monthly(Request $request): Response
    {
        $this->authorize('view-monthly-report');

        [$from, $to, $month] = $this->resolveFilters($request);

        return Inertia::render('Reports/Monthly', [
            'byCompany' => $this->service->byCompany($from, $to),
            'bySales' => $this->service->bySalesUser($from, $to),
            'byProduct' => $this->service->byProduct($from, $to),
            'totals' => $this->service->totals($from, $to),
            'filters' => [
                'from' => $from,
                'to' => $to,
                'month' => $month,
            ],
        ]);
    }

    public function exportMonthly(Request $request): StreamedResponse
    {
        $this->authorize('export-monthly-report');

        [$from, $to] = $this->resolveFilters($request);

        $byCompany = $this->service->byCompany($from, $to);
        $bySales = $this->service->bySalesUser($from, $to);
        $byProduct = $this->service->byProduct($from, $to);

        activity('report')
            ->causedBy($request->user())
            ->event('export')
            ->withProperties(['from' => $from, 'to' => $to])
            ->log('report.monthly.export');

        $filename = sprintf('monthly_report_%s_%s.xlsx', $from, $to);

        return response()->streamDownload(function () use ($byCompany, $bySales, $byProduct, $from, $to) {
            $this->excel->stream($byCompany, $bySales, $byProduct, $from, $to);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * month(YYYY-MM) 우선, 없으면 from/to(YYYY-MM-DD), 둘 다 없으면 당월.
     *
     * @return array{0: string, 1: string, 2: string|null}
     */
    private function resolveFilters(Request $request): array
    {
        $month = $request->input('month');
        $fromIn = $request->input('from');
        $toIn = $request->input('to');

        if ($month && preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
            [$from, $to] = $this->service->monthRange($month);

            return [$from, $to, $month];
        }

        if (
            $fromIn && $toIn
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $fromIn)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $toIn)
            && $fromIn <= $toIn
        ) {
            return [$fromIn, $toIn, null];
        }

        $defaultMonth = now()->format('Y-m');
        [$from, $to] = $this->service->monthRange($defaultMonth);

        return [$from, $to, $defaultMonth];
    }
}
