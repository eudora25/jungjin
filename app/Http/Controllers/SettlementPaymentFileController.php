<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSettlementPaymentFileRequest;
use App\Models\Settlement;
use App\Models\SettlementPaymentFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GAP-5-4: 정산 지급 증빙 파일 업로드/삭제/다운로드.
 *
 * - 업로드/삭제: admin만 (Settlement 가 confirmed 또는 paid 상태)
 * - 다운로드: Settlement 조회 권한자(admin + 본인 실적 포함 sales)
 * - 저장 위치: `storage/app/settlement-payment-files/{settlement_id}/{uuid}.ext` (private 디스크)
 * - URL 변조 방지: file.settlement_id 와 라우트의 settlement.id 일치 검증 → 404
 */
class SettlementPaymentFileController extends Controller
{
    public function store(StoreSettlementPaymentFileRequest $request, Settlement $settlement): RedirectResponse
    {
        $this->authorize('uploadPaymentFile', $settlement);

        $upload = $request->file('file');
        $extension = strtolower((string) $upload->getClientOriginalExtension());
        $stored = Str::uuid()->toString().($extension ? '.'.$extension : '');
        $path = $upload->storeAs("settlement-payment-files/{$settlement->id}", $stored, 'local');

        $settlement->paymentFiles()->create([
            'original_name' => $upload->getClientOriginalName(),
            'stored_name' => $stored,
            'path' => $path,
            'size' => $upload->getSize(),
            'mime_type' => $upload->getMimeType() ?? 'application/octet-stream',
            'extension' => $extension ?: null,
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', '지급 증빙 파일을 첨부했습니다.');
    }

    public function destroy(Settlement $settlement, SettlementPaymentFile $file): RedirectResponse
    {
        $this->authorize('uploadPaymentFile', $settlement);

        if ($file->settlement_id !== $settlement->id) {
            abort(404);
        }

        Storage::disk('local')->delete($file->path);
        $file->delete();

        return back()->with('success', '지급 증빙 파일을 삭제했습니다.');
    }

    public function download(Settlement $settlement, SettlementPaymentFile $file): StreamedResponse
    {
        $this->authorize('view', $settlement);

        if ($file->settlement_id !== $settlement->id) {
            abort(404);
        }

        if (! $file->path || ! Storage::disk('local')->exists($file->path)) {
            abort(404);
        }

        return Storage::disk('local')->download($file->path, $file->original_name);
    }
}
