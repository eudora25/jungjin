<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportPerformancesRequest;
use App\Models\Performance;
use App\Services\Performance\PerformanceImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceImportController extends Controller
{
    private const STORAGE_DIR = 'imports/performances';

    private const TOKEN_TTL_MIN = 30;

    public function __construct(private readonly PerformanceImportService $service) {}

    public function form(): Response
    {
        $this->authorize('create', Performance::class);

        return Inertia::render('Performance/Import', [
            'allowedHeaders' => PerformanceImportService::ALLOWED_HEADERS,
            'sampleHeader' => implode(',', PerformanceImportService::ALLOWED_HEADERS),
        ]);
    }

    public function handle(ImportPerformancesRequest $request): RedirectResponse|Response
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
                $parsed = $this->service->parseCsv($absolutePath);
            } catch (\Throwable $e) {
                Storage::disk('local')->delete($stored);

                return back()->withErrors(['file' => 'CSV 파싱 실패: '.$e->getMessage()]);
            }

            $headerErrors = $this->service->validateHeaders($parsed['headers']);
            if (! empty($headerErrors)) {
                Storage::disk('local')->delete($stored);

                return back()->withErrors(['file' => implode("\n", $headerErrors)]);
            }

            $analyzed = $this->service->analyze($parsed['rows']);

            return Inertia::render('Performance/Import', [
                'allowedHeaders' => PerformanceImportService::ALLOWED_HEADERS,
                'sampleHeader' => implode(',', PerformanceImportService::ALLOWED_HEADERS),
                'analysis' => [
                    'token' => $token,
                    'filename' => $upload->getClientOriginalName(),
                    'headers' => $parsed['headers'],
                    'row_count' => count($parsed['rows']),
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
            $parsed = $this->service->parseCsv($absolutePath);
            $result = $this->service->import($parsed['rows'], $userId);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => '적용 중 오류: '.$e->getMessage()]);
        } finally {
            Storage::disk('local')->delete($stored);
        }

        if (! $result['committed']) {
            return back()->with('error', '검증 오류가 있어 적용되지 않았습니다. 분석 결과를 확인해주세요.');
        }

        $msg = sprintf('실적 CSV 적용 완료 — 생성 %d건', $result['summary']['create']);

        return redirect()->route('performance.index')->with('success', $msg);
    }

    public function sample(): HttpResponse
    {
        $this->authorize('create', Performance::class);

        $headers = implode(',', PerformanceImportService::ALLOWED_HEADERS);
        $exampleMap = [
            'performance_date' => now()->toDateString(),
            'company_biz_no' => '123-45-67890',
            'company_name' => '예시약국',
            'insurance_code' => 'A12345678',
            'product_code' => 'P-001',
            'quantity' => '10',
            'note' => '예시 비고',
        ];

        $exampleRow = implode(',', array_map(
            static fn ($k) => $exampleMap[$k] ?? '',
            PerformanceImportService::ALLOWED_HEADERS,
        ));

        $csv = "\xEF\xBB\xBF".$headers."\n".$exampleRow."\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="performances_import_sample.csv"',
        ]);
    }
}
