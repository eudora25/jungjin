<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CommissionSummaryExcelExporter;
use App\Services\CommissionSummaryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 영업사원별 수수료 명세 (GAP-3).
 *
 *  - index            : admin 전체 영업사원 합계
 *  - exportExcel      : admin 합계 Excel 다운로드
 *  - statement        : 본인 또는 admin → 영업사원 1인의 라인 명세
 *  - statementPdf     : 위 명세서 PDF 다운로드
 */
class CommissionSummaryController extends Controller
{
    public function __construct(
        private readonly CommissionSummaryService $service,
        private readonly CommissionSummaryExcelExporter $excel,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('view-commission-summary');

        [$from, $to, $month] = $this->resolveFilters($request);

        $rows = $this->service->summaryByUser($from, $to);

        $totals = [
            'line_count' => (int) $rows->sum('line_count'),
            'total_quantity' => (int) $rows->sum('total_quantity'),
            'total_subtotal' => (float) $rows->sum('total_subtotal'),
            'total_commission' => (float) $rows->sum('total_commission'),
        ];

        return Inertia::render('CommissionSummary/Index', [
            'rows' => $rows->values(),
            'totals' => $totals,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'month' => $month,
            ],
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $this->authorize('export-commission-summary');

        [$from, $to] = $this->resolveFilters($request);
        $rows = $this->service->summaryByUser($from, $to);

        $filename = sprintf('commission_summary_%s_%s.xlsx', $from, $to);

        return response()->streamDownload(function () use ($rows, $from, $to) {
            $this->excel->streamSummary($rows, $from, $to);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function statement(Request $request, User $user): Response
    {
        $this->authorize('view-commission-statement', $user);

        [$from, $to, $month] = $this->resolveFilters($request);

        $data = $this->service->statementFor($user->id, $from, $to);

        $lines = $data['lines']->map(fn ($p) => [
            'id' => $p->id,
            'performance_no' => $p->performance_no,
            'performance_date' => $p->performance_date?->toDateString(),
            'company_name' => $p->company?->company_name,
            'product_name' => $p->product?->product_name,
            'insurance_code' => $p->product?->insurance_code,
            'quantity' => (int) $p->quantity,
            'unit_price' => (string) $p->unit_price,
            'subtotal' => (string) $p->subtotal,
            'commission_rate' => $p->commission_rate !== null ? (string) $p->commission_rate : null,
            'commission_amount' => $p->commission_amount !== null ? (string) $p->commission_amount : null,
        ]);

        return Inertia::render('CommissionSummary/Statement', [
            'targetUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'lines' => $lines,
            'totals' => $data['totals'],
            'filters' => [
                'from' => $from,
                'to' => $to,
                'month' => $month,
            ],
        ]);
    }

    public function statementPdf(Request $request, User $user): StreamedResponse
    {
        $this->authorize('export-commission-statement', $user);

        [$from, $to] = $this->resolveFilters($request);
        $data = $this->service->statementFor($user->id, $from, $to);

        $pdf = Pdf::loadView('pdf.commission_statement', [
            'targetUser' => $user,
            'lines' => $data['lines'],
            'totals' => $data['totals'],
            'from' => $from,
            'to' => $to,
        ])->setPaper('a4', 'portrait');

        $filename = sprintf('commission_statement_%s_%s_%s.pdf', $user->id, $from, $to);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
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
        ) {
            return [$fromIn, $toIn, null];
        }

        $defaultMonth = now()->format('Y-m');
        [$from, $to] = $this->service->monthRange($defaultMonth);

        return [$from, $to, $defaultMonth];
    }
}
