<?php

namespace App\Services\Performance;

use App\Models\ChangeReason;
use App\Models\Company;
use App\Models\Performance;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 실적(Performance) CSV 일괄 등록 서비스.
 *
 * `ProductImportService` 와 동일한 파이프라인(parseCsv → validateHeaders → analyze → import).
 * 실적은 upsert 가 아니라 항상 **신규 draft** 로 생성된다. `PerformanceResolver` 로 스냅샷을 해석해 저장.
 */
class PerformanceImportService
{
    public const ALLOWED_HEADERS = [
        'performance_date',
        'company_biz_no', 'company_name',
        'insurance_code', 'product_code',
        'quantity',
        'note',
    ];

    public function __construct(private readonly PerformanceResolver $resolver)
    {
    }

    /**
     * @return array{headers: array<int,string>, rows: array<int, array<string, mixed>>}
     */
    public function parseCsv(string $absolutePath): array
    {
        $handle = @fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('CSV 파일을 열 수 없습니다.');
        }

        $headers = [];
        $rows = [];
        $lineNo = 0;

        while (($cols = fgetcsv($handle)) !== false) {
            $lineNo++;

            if ($lineNo === 1) {
                if (isset($cols[0])) {
                    $cols[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cols[0]);
                }
                $headers = array_map(fn ($c) => strtolower(trim((string) $c)), $cols);

                continue;
            }

            if (count($cols) === 1 && trim((string) $cols[0]) === '') {
                continue;
            }

            $row = ['_line' => $lineNo];
            foreach ($headers as $i => $h) {
                $val = $cols[$i] ?? null;
                $row[$h] = $val === null ? null : trim((string) $val);
            }
            $rows[] = $row;
        }
        fclose($handle);

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return array<int, string>
     */
    public function validateHeaders(array $headers): array
    {
        $errors = [];

        if (! in_array('performance_date', $headers, true)) {
            $errors[] = 'performance_date 컬럼은 필수입니다.';
        }

        if (! in_array('quantity', $headers, true)) {
            $errors[] = 'quantity 컬럼은 필수입니다.';
        }

        $hasCompanyKey = (bool) array_intersect(['company_biz_no', 'company_name'], $headers);
        if (! $hasCompanyKey) {
            $errors[] = '거래처 키(company_biz_no / company_name) 중 최소 1개가 필요합니다.';
        }

        $hasProductKey = (bool) array_intersect(['insurance_code', 'product_code'], $headers);
        if (! $hasProductKey) {
            $errors[] = '제품 키(insurance_code / product_code) 중 최소 1개가 필요합니다.';
        }

        $unknown = array_values(array_diff($headers, self::ALLOWED_HEADERS));
        if (! empty($unknown)) {
            $errors[] = '알 수 없는 컬럼: '.implode(', ', $unknown);
        }

        return $errors;
    }

    /**
     * Dry-run. 행별로 검증·해석만 수행하고 저장하지 않는다.
     *
     * @return array{summary: array<string,int>, results: array<int, array<string, mixed>>}
     */
    public function analyze(array $rows): array
    {
        $summary = ['create' => 0, 'error' => 0];
        $results = [];

        foreach ($rows as $row) {
            $errors = [];

            $date = $this->parseDate($row['performance_date'] ?? null, $errors);
            $quantity = $this->parseQuantity($row['quantity'] ?? null, $errors);
            $company = $this->findCompany($row, $errors);
            $product = $this->findProduct($row, $errors);

            if (! empty($errors)) {
                $summary['error']++;
                $results[] = [
                    'line' => $row['_line'] ?? null,
                    'action' => 'error',
                    'identifier' => $this->identifierOf($row),
                    'errors' => $errors,
                    'preview' => null,
                ];

                continue;
            }

            $summary['create']++;

            $resolved = $this->resolver->resolve($company, $product, $date);
            $subtotal = $quantity * (float) $resolved['unit_price'];
            $commission = $resolved['commission_rate'] !== null
                ? $subtotal * ((float) $resolved['commission_rate']) / 100
                : null;

            $results[] = [
                'line' => $row['_line'] ?? null,
                'action' => 'create',
                'identifier' => $this->identifierOf($row),
                'company' => ['id' => $company->id, 'company_name' => $company->company_name],
                'product' => ['id' => $product->id, 'product_name' => $product->product_name, 'insurance_code' => $product->insurance_code],
                'errors' => [],
                'preview' => [
                    'performance_date' => $date->toDateString(),
                    'quantity' => $quantity,
                    'unit_price' => round((float) $resolved['unit_price'], 2),
                    'commission_rate' => $resolved['commission_rate'],
                    'subtotal' => round($subtotal, 2),
                    'commission_amount' => $commission !== null ? round($commission, 2) : null,
                    'price_source' => $resolved['price_source'],
                    'commission_source' => $resolved['commission_source'],
                ],
            ];
        }

        return ['summary' => $summary, 'results' => $results];
    }

    /**
     * 실제 적용. 검증 오류가 한 건이라도 있으면 저장하지 않고 반환.
     *
     * @return array{committed: bool, summary: array<string,int>, results: array<int, array<string,mixed>>, created_ids?: array<int,int>}
     */
    public function import(array $rows, ?int $userId = null): array
    {
        $analyzed = $this->analyze($rows);

        if ($analyzed['summary']['error'] > 0) {
            return ['committed' => false] + $analyzed;
        }

        $createdIds = [];

        ChangeReason::with('실적 CSV 일괄 등록', function () use ($rows, $userId, &$createdIds) {
            DB::transaction(function () use ($rows, $userId, &$createdIds) {
                foreach ($rows as $row) {
                    $errors = [];
                    $date = $this->parseDate($row['performance_date'] ?? null, $errors);
                    $quantity = $this->parseQuantity($row['quantity'] ?? null, $errors);
                    $company = $this->findCompany($row, $errors);
                    $product = $this->findProduct($row, $errors);

                    // 방어적: analyze 에서 통과했으므로 여기서는 발생 안 함
                    if (! empty($errors)) {
                        throw new \RuntimeException('행 '.($row['_line'] ?? '?').' 검증 실패: '.implode(', ', $errors));
                    }

                    $perf = new Performance();
                    $this->resolver->fill($perf, $company, $product, [
                        'performance_no' => Performance::nextNumberFor($date),
                        'performance_date' => $date->toDateString(),
                        'quantity' => $quantity,
                        'note' => $row['note'] ?? null,
                        'status' => Performance::STATUS_DRAFT,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);

                    $perf->save();
                    $createdIds[] = $perf->id;
                }
            });
        });

        return [
            'committed' => true,
            'summary' => $analyzed['summary'],
            'results' => $analyzed['results'],
            'created_ids' => $createdIds,
        ];
    }

    private function parseDate(?string $raw, array &$errors): ?Carbon
    {
        if ($raw === null || $raw === '') {
            $errors[] = 'performance_date 는 필수입니다.';

            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            $errors[] = "performance_date 형식이 잘못되었습니다: {$raw}";

            return null;
        }
    }

    private function parseQuantity(?string $raw, array &$errors): ?int
    {
        if ($raw === null || $raw === '') {
            $errors[] = 'quantity 는 필수입니다.';

            return null;
        }

        if (! preg_match('/^-?\d+$/', $raw)) {
            $errors[] = "quantity 는 정수여야 합니다: {$raw}";

            return null;
        }

        $q = (int) $raw;
        if ($q === 0) {
            $errors[] = 'quantity 는 0 일 수 없습니다. (반품은 음수로 입력)';

            return null;
        }

        return $q;
    }

    private function findCompany(array $row, array &$errors): ?Company
    {
        $biz = $row['company_biz_no'] ?? null;
        if ($biz !== null && $biz !== '') {
            $hits = Company::where('business_registration_number', $biz)->get();
            if ($hits->count() === 1) {
                return $hits->first();
            }
            if ($hits->count() > 1) {
                $errors[] = "company_biz_no={$biz} 에 매칭되는 거래처가 여러 건입니다.";

                return null;
            }
        }

        $name = $row['company_name'] ?? null;
        if ($name !== null && $name !== '') {
            $hits = Company::where('company_name', $name)->get();
            if ($hits->count() === 1) {
                return $hits->first();
            }
            if ($hits->count() > 1) {
                $errors[] = "company_name={$name} 에 매칭되는 거래처가 여러 건입니다. business_registration_number 로 지정하세요.";

                return null;
            }
        }

        $errors[] = '거래처를 찾을 수 없습니다. (company_biz_no / company_name 확인)';

        return null;
    }

    private function findProduct(array $row, array &$errors): ?Product
    {
        $ins = $row['insurance_code'] ?? null;
        if ($ins !== null && $ins !== '') {
            $hits = Product::where('insurance_code', $ins)->get();
            if ($hits->count() === 1) {
                return $hits->first();
            }
            if ($hits->count() > 1) {
                $errors[] = "insurance_code={$ins} 에 매칭되는 제품이 여러 건입니다.";

                return null;
            }
        }

        $code = $row['product_code'] ?? null;
        if ($code !== null && $code !== '') {
            $hits = Product::where('product_code', $code)->get();
            if ($hits->count() === 1) {
                return $hits->first();
            }
            if ($hits->count() > 1) {
                $errors[] = "product_code={$code} 에 매칭되는 제품이 여러 건입니다. insurance_code 로 지정하세요.";

                return null;
            }
        }

        $errors[] = '제품을 찾을 수 없습니다. (insurance_code / product_code 확인)';

        return null;
    }

    private function identifierOf(array $row): string
    {
        $parts = [];
        if (! empty($row['performance_date'])) {
            $parts[] = $row['performance_date'];
        }
        $company = $row['company_name'] ?? $row['company_biz_no'] ?? null;
        if ($company) {
            $parts[] = $company;
        }
        $product = $row['insurance_code'] ?? $row['product_code'] ?? null;
        if ($product) {
            $parts[] = $product;
        }

        return implode(' / ', $parts) ?: '(빈 행)';
    }
}
