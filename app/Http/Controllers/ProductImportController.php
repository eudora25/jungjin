<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportProductsRequest;
use App\Models\Product;
use App\Services\Products\ProductImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductImportController extends Controller
{
    private const STORAGE_DIR = 'imports/products';

    /** 분석 결과 토큰 보존 시간 (분) */
    private const TOKEN_TTL_MIN = 30;

    public function __construct(private readonly ProductImportService $service) {}

    public function form(): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('Products/Import', [
            'allowedHeaders' => ProductImportService::ALLOWED_HEADERS,
            'sampleHeader' => implode(',', ProductImportService::ALLOWED_HEADERS),
        ]);
    }

    /**
     * 단일 엔드포인트.
     *  - mode=analyze: 파일 업로드 → 임시 저장 → 토큰 발급 + 결과 반환
     *  - mode=commit:  토큰으로 임시 파일 읽고 실제 적용
     */
    public function handle(ImportProductsRequest $request): RedirectResponse|Response
    {
        $mode = $request->validated('mode');
        $userId = $request->user()->id;

        // 1) 분석
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

            return Inertia::render('Products/Import', [
                'allowedHeaders' => ProductImportService::ALLOWED_HEADERS,
                'sampleHeader' => implode(',', ProductImportService::ALLOWED_HEADERS),
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

        // 2) 커밋
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

        $msg = sprintf(
            'CSV 적용 완료 — 신규 %d / 수정 %d / 가격 추가 %d',
            $result['summary']['create'],
            $result['summary']['update'],
            $result['summary']['price_added'],
        );

        return redirect()->route('products.index')->with('success', $msg);
    }

    /**
     * 샘플 CSV 다운로드 (헤더 + 예시 1행)
     */
    public function sample(): HttpResponse
    {
        $this->authorize('create', Product::class);

        $headers = implode(',', ProductImportService::ALLOWED_HEADERS);
        $exampleMap = [
            'insurance_code' => 'A12345678',
            'standard_code' => 'KD12345',
            'barcode_gtin' => '8801234567890',
            'product_code' => 'P-001',
            'product_name' => '예시정 100mg',
            'generic_name' => '예시성분',
            'manufacturer' => '예시제약',
            'category' => '진통제',
            'drug_type' => 'general',
            'storage_condition' => 'room',
            'strength' => '100mg',
            'unit' => '정',
            'pack_size' => '30',
            'nims_item_code' => '',
            'status' => 'active',
            'remarks' => '',
            'insurance_price' => '500',
            'cost_price' => '200',
            'sale_price' => '1500',
            'price_effective_from' => now()->toDateString(),
            'price_source' => 'CSV 일괄 등록',
        ];

        $exampleRow = implode(',', array_map(
            static fn ($k) => $exampleMap[$k] ?? '',
            ProductImportService::ALLOWED_HEADERS,
        ));

        $csv = "\xEF\xBB\xBF".$headers."\n".$exampleRow."\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="products_import_sample.csv"',
        ]);
    }
}
