<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportHospitalsRequest;
use App\Models\Hospital;
use App\Services\Clients\HospitalImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HospitalImportController extends Controller
{
    private const STORAGE_DIR = 'imports/hospitals';

    private const TOKEN_TTL_MIN = 30;

    public function __construct(private readonly HospitalImportService $service)
    {
    }

    public function form(): Response
    {
        $this->authorize('create', Hospital::class);

        return Inertia::render('Clients/Hospitals/Import', [
            'requiredHeaders' => HospitalImportService::REQUIRED_HEADERS,
            'analysis' => null,
        ]);
    }

    public function handle(ImportHospitalsRequest $request): RedirectResponse|Response
    {
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

            return Inertia::render('Clients/Hospitals/Import', [
                'requiredHeaders' => HospitalImportService::REQUIRED_HEADERS,
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
            '병의원 CSV 적용 완료 — 신규 %d / 수정 %d (비활성 %d 포함)',
            $result['summary']['create'],
            $result['summary']['update'],
            $result['summary']['inactive'],
        );

        return redirect()->route('hospitals.index')->with('success', $msg);
    }
}

