<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportPharmaciesRequest;
use App\Services\Clients\PharmacyImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 플랫폼 — 약국 공공데이터 CSV 일괄 등록 (super_admin). (GAP-10 마스터 CRUD)
 * 기존 admin import 와 동일 서비스를 재사용하되 super_admin 전용 라우트.
 */
class PharmacyImportController extends Controller
{
    private const STORAGE_DIR = 'imports/pharmacies';

    private const TOKEN_TTL_MIN = 30;

    public function __construct(private readonly PharmacyImportService $service) {}

    public function form(Request $request): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        return Inertia::render('Clients/Pharmacies/Import', [
            'requiredHeaders' => PharmacyImportService::REQUIRED_HEADERS,
            'analysis' => null,
            'handleRoute' => 'platform.pharmacies.import.handle',
            'indexRoute' => 'platform.pharmacies.index',
        ]);
    }

    public function handle(ImportPharmaciesRequest $request): RedirectResponse|Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $mode = $request->validated('mode');
        $userId = $request->user()->id;

        if ($mode === 'analyze') {
            $upload = $request->file('file');
            if ($upload === null) {
                return back()->withErrors(['file' => 'CSV 파일을 선택해주세요.']);
            }

            $token = (string) Str::uuid();
            $stored = $upload->storeAs(self::STORAGE_DIR, $token.'.csv', 'local');
            $absolutePath = Storage::disk('local')->path($stored);

            try {
                $analyzed = $this->service->analyzeFile($absolutePath);
            } catch (\Throwable $e) {
                Storage::disk('local')->delete($stored);

                return back()->withErrors(['file' => 'CSV 분석 실패: '.$e->getMessage()]);
            }

            if (! empty($analyzed['errors'])) {
                Storage::disk('local')->delete($stored);

                return back()->withErrors(['file' => implode("\n", $analyzed['errors'])]);
            }

            return Inertia::render('Clients/Pharmacies/Import', [
                'requiredHeaders' => PharmacyImportService::REQUIRED_HEADERS,
                'handleRoute' => 'platform.pharmacies.import.handle',
                'indexRoute' => 'platform.pharmacies.index',
                'analysis' => [
                    'token' => $token,
                    'filename' => $upload->getClientOriginalName(),
                    'headers' => $analyzed['headers'],
                    'row_count' => $analyzed['row_count'],
                    'summary' => $analyzed['summary'],
                    'results' => $analyzed['results'],
                    'expires_at' => Carbon::now()->addMinutes(self::TOKEN_TTL_MIN)->toIso8601String(),
                ],
            ]);
        }

        $token = $request->validated('token');
        if (! $token) {
            return back()->withErrors(['token' => '분석 토큰이 없습니다. 다시 분석해주세요.']);
        }

        $stored = self::STORAGE_DIR.'/'.$token.'.csv';
        if (! Storage::disk('local')->exists($stored)) {
            return back()->withErrors(['token' => '분석 결과가 만료되었습니다. CSV 를 다시 업로드해주세요.']);
        }

        $absolutePath = Storage::disk('local')->path($stored);

        try {
            @set_time_limit(0);
            @ini_set('max_execution_time', '0');
            $result = $this->service->importFile($absolutePath, $userId);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => '적용 중 오류: '.$e->getMessage()]);
        } finally {
            Storage::disk('local')->delete($stored);
        }

        if (! $result['committed']) {
            return back()->with('error', '검증 오류가 있어 적용되지 않았습니다. 분석 결과를 확인해주세요.');
        }

        $msg = sprintf(
            '약국 CSV 적용 완료 — 신규 %d / 수정 %d (비활성 %d 포함)',
            $result['summary']['create'],
            $result['summary']['update'],
            $result['summary']['inactive'],
        );

        return redirect()->route('platform.pharmacies.index')->with('success', $msg);
    }
}
