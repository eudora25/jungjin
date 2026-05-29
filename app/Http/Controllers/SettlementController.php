<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaySettlementRequest;
use App\Http\Requests\StoreSettlementRequest;
use App\Models\ChangeReason;
use App\Models\Company;
use App\Models\Performance;
use App\Models\Settlement;
use App\Services\Settlement\SettlementBuilder;
use App\Services\Settlement\SettlementExcelExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettlementController extends Controller
{
    public function __construct(
        private readonly SettlementBuilder $builder,
        private readonly SettlementExcelExporter $excelExporter,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Settlement::class);

        $month = $request->input('period_month');
        $companyId = $request->integer('company_id') ?: null;
        $paymentBatchNo = trim((string) $request->input('payment_batch_no'));

        $user = $request->user();

        $settlements = Settlement::query()
            ->with(['company:id,company_name'])
            ->when(! $user->managesCurrentTenant(), fn ($q) => $q->whereHas(
                'lines.performance',
                fn ($p) => $p->where('created_by', $user->id),
            ))
            ->when($month && preg_match('/^\d{4}-\d{2}$/', (string) $month), fn ($q) => $q->where('period_month', $month))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($paymentBatchNo !== '', fn ($q) => $q->where('payment_batch_no', $paymentBatchNo))
            ->orderByDesc('period_month')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Settlements/Index', [
            'settlements' => $settlements,
            'filters' => [
                'period_month' => $month,
                'company_id' => $companyId,
                'payment_batch_no' => $paymentBatchNo !== '' ? $paymentBatchNo : null,
            ],
            'can' => [
                'create' => $request->user()->can('create', Settlement::class),
            ],
        ]);
    }

    public function show(Settlement $settlement): Response
    {
        $this->authorize('view', $settlement);

        $settlement->load([
            'company:id,company_name,business_registration_number',
            'lines.performance.product:id,product_name,insurance_code',
            'paymentFiles.uploader:id,name',
        ]);

        $user = request()->user();

        $hasUnapplied = $settlement->calculated_at && Performance::query()
            ->where('company_id', $settlement->company_id)
            ->where('status', Performance::STATUS_APPROVED)
            ->whereYear('performance_date', substr($settlement->period_month, 0, 4))
            ->whereMonth('performance_date', substr($settlement->period_month, 5, 2))
            ->where('approved_at', '>', $settlement->calculated_at)
            ->exists();

        $paymentFiles = $settlement->paymentFiles->map(fn ($f) => [
            'id' => $f->id,
            'original_name' => $f->original_name,
            'size' => (int) $f->size,
            'mime_type' => $f->mime_type,
            'extension' => $f->extension,
            'uploaded_by_name' => $f->uploader?->name,
            'uploaded_at' => $f->created_at?->toIso8601String(),
        ]);

        return Inertia::render('Settlements/Show', [
            'settlement' => $settlement,
            'hasUnappliedPerformances' => $hasUnapplied,
            'paymentFiles' => $paymentFiles,
            'can' => [
                'create' => $user->can('create', Settlement::class),
                'export' => $user->can('export', $settlement),
                'recalculate' => $user->can('recalculate', $settlement),
                'confirm' => $user->can('confirm', $settlement),
                'pay' => $user->can('pay', $settlement),
                'cancel' => $user->can('cancel', $settlement),
                'uploadPaymentFile' => $user->can('uploadPaymentFile', $settlement),
            ],
        ]);
    }

    public function exportExcel(Request $request, Settlement $settlement): StreamedResponse
    {
        $this->authorize('export', $settlement);

        $this->loadExportRelations($settlement);

        $filename = sprintf('settlement_%s_%s.xlsx', $settlement->period_month, $settlement->company_id);

        return response()->streamDownload(function () use ($settlement) {
            $this->excelExporter->stream($settlement);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportPdf(Request $request, Settlement $settlement): StreamedResponse
    {
        $this->authorize('export', $settlement);

        $this->loadExportRelations($settlement);

        $pdf = Pdf::loadView('pdf.settlement', [
            'settlement' => $settlement,
        ])->setPaper('a4', 'portrait');

        $filename = sprintf('settlement_%s_%s.pdf', $settlement->period_month, $settlement->company_id);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function loadExportRelations(Settlement $settlement): void
    {
        $settlement->load([
            'company:id,company_name,business_registration_number',
            'lines.performance.product:id,product_name,insurance_code',
        ]);
    }

    public function store(StoreSettlementRequest $request): RedirectResponse
    {
        $this->authorize('create', Settlement::class);

        $company = Company::findOrFail($request->integer('company_id'));
        $periodMonth = (string) $request->input('period_month');

        try {
            $settlement = ChangeReason::with(
                '정산 생성/재계산',
                fn () => $this->builder->createOrRebuild($company, $periodMonth, $request->user()),
            );
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->withErrors(['period_month' => $e->getMessage()]);
        }

        return redirect()
            ->route('settlements.show', $settlement)
            ->with('success', "정산 {$settlement->settlement_no} 을(를) 계산했습니다.");
    }

    public function recalculate(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->authorize('recalculate', $settlement);

        ChangeReason::with('정산 재계산', function () use ($settlement, $request) {
            $this->builder->replaceLines($settlement, $request->user());
        });

        return back()->with('success', '정산을 재계산했습니다.');
    }

    public function confirm(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->authorize('confirm', $settlement);

        ChangeReason::with('정산 확정', function () use ($settlement, $request) {
            $settlement->update([
                'status' => Settlement::STATUS_CONFIRMED,
                'confirmed_at' => now(),
                'confirmed_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', '정산을 확정했습니다.');
    }

    public function pay(PaySettlementRequest $request, Settlement $settlement): RedirectResponse
    {
        // authorize 는 PaySettlementRequest::authorize() 에서 수행

        $data = $request->validated();

        ChangeReason::with('정산 지급 완료', function () use ($settlement, $request, $data) {
            $settlement->update([
                'status' => Settlement::STATUS_PAID,
                'paid_at' => now(),
                'paid_by' => $request->user()->id,
                'paid_on' => $data['paid_on'],
                'payment_method' => $data['payment_method'] ?? null,
                'payment_batch_no' => $data['payment_batch_no'] ?? null,
                'payment_note' => $data['payment_note'] ?? null,
                'updated_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', '정산을 지급 완료 처리했습니다.');
    }

    public function cancel(Request $request, Settlement $settlement): RedirectResponse
    {
        $this->authorize('cancel', $settlement);

        ChangeReason::with('정산 취소', function () use ($settlement, $request) {
            $settlement->update([
                'status' => Settlement::STATUS_CANCELLED,
                'updated_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', '정산을 취소했습니다.');
    }
}
