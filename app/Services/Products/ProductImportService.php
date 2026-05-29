<?php

namespace App\Services\Products;

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProductImportService
{
    public const ALLOWED_HEADERS = [
        'insurance_code', 'standard_code', 'barcode_gtin', 'product_code',
        'product_name', 'generic_name', 'manufacturer', 'category',
        'drug_type', 'storage_condition', 'strength', 'unit', 'pack_size',
        'nims_item_code', 'status', 'remarks',
        'insurance_price', 'cost_price', 'sale_price',
        'price_effective_from', 'price_source',
    ];

    public function __construct(private readonly ProductPriceService $priceService)
    {
    }

    /**
     * CSV 파싱.
     *
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
     * 헤더 유효성 검사.
     *
     * @return array<int, string>
     */
    public function validateHeaders(array $headers): array
    {
        $errors = [];

        $hasKey = (bool) array_intersect(['insurance_code', 'standard_code', 'barcode_gtin'], $headers);
        if (! $hasKey) {
            $errors[] = '키 컬럼(insurance_code / standard_code / barcode_gtin) 중 최소 1개가 필요합니다.';
        }

        if (! in_array('product_name', $headers, true)) {
            $errors[] = 'product_name 컬럼은 필수입니다.';
        }

        $unknown = array_values(array_diff($headers, self::ALLOWED_HEADERS));
        if (! empty($unknown)) {
            $errors[] = '알 수 없는 컬럼: '.implode(', ', $unknown);
        }

        return $errors;
    }

    /**
     * Dry-run: 행별 결과를 계산만 하고 저장은 하지 않는다.
     *
     * @return array{summary: array<string,int>, results: array<int, array<string, mixed>>}
     */
    public function analyze(array $rows): array
    {
        $summary = ['create' => 0, 'update' => 0, 'price_added' => 0, 'error' => 0];
        $results = [];

        foreach ($rows as $row) {
            $errors = $this->validateRow($row);

            if (! empty($errors)) {
                $summary['error']++;
                $results[] = [
                    'line' => $row['_line'] ?? null,
                    'action' => 'error',
                    'identifier' => $this->identifierOf($row),
                    'errors' => $errors,
                    'price_changes' => [],
                ];

                continue;
            }

            $existing = $this->findExisting($row);
            $action = $existing ? 'update' : 'create';
            $summary[$action]++;

            $priceChanges = [];
            foreach ([ProductPrice::TYPE_INSURANCE, ProductPrice::TYPE_COST, ProductPrice::TYPE_SALE] as $type) {
                $col = $this->priceColumn($type);
                $val = $row[$col] ?? null;
                if ($val === null || $val === '') {
                    continue;
                }
                $priceChanges[] = [
                    'type' => $type,
                    'amount' => (float) $val,
                    'effective_from' => $row['price_effective_from'] ?? now()->toDateString(),
                ];
                $summary['price_added']++;
            }

            $results[] = [
                'line' => $row['_line'] ?? null,
                'action' => $action,
                'identifier' => $this->identifierOf($row),
                'product_id' => $existing?->id,
                'errors' => [],
                'price_changes' => $priceChanges,
            ];
        }

        return ['summary' => $summary, 'results' => $results];
    }

    /**
     * 실제 적용. 한 행이라도 검증 실패가 있으면 어떤 것도 저장하지 않고 반환.
     *
     * @return array{committed: bool, summary: array<string,int>, results: array<int, array<string,mixed>>, created_ids?: array<int,int>, updated_ids?: array<int,int>}
     */
    public function import(array $rows, ?int $userId = null): array
    {
        $analyzed = $this->analyze($rows);

        if ($analyzed['summary']['error'] > 0) {
            return ['committed' => false] + $analyzed;
        }

        $createdIds = [];
        $updatedIds = [];

        DB::transaction(function () use ($rows, $userId, &$createdIds, &$updatedIds) {
            foreach ($rows as $row) {
                $existing = $this->findExisting($row);
                $payload = $this->productPayload($row, isCreate: ! $existing);
                $payload['updated_by'] = $userId;

                if ($existing) {
                    $existing->update($payload);
                    $product = $existing;
                    $updatedIds[] = $product->id;
                } else {
                    $payload['created_by'] = $userId;
                    $payload['approval_status'] = Product::APPROVAL_DRAFT;
                    $product = Product::create($payload);
                    $createdIds[] = $product->id;
                }

                $effFrom = $row['price_effective_from'] ?? now()->toDateString();
                $source = $row['price_source'] ?? null;

                foreach ([ProductPrice::TYPE_INSURANCE, ProductPrice::TYPE_COST, ProductPrice::TYPE_SALE] as $type) {
                    $col = $this->priceColumn($type);
                    $amount = $row[$col] ?? null;
                    if ($amount === null || $amount === '') {
                        continue;
                    }

                    $exists = $product->prices()
                        ->ofType($type)
                        ->whereDate('effective_from', $effFrom)
                        ->exists();
                    if ($exists) {
                        continue;
                    }

                    $this->priceService->register($product, [
                        'price_type' => $type,
                        'amount' => (float) $amount,
                        'effective_from' => $effFrom,
                        'source' => $source,
                    ], $userId);
                }
            }
        });

        return [
            'committed' => true,
            'summary' => $analyzed['summary'],
            'results' => $analyzed['results'],
            'created_ids' => $createdIds,
            'updated_ids' => $updatedIds,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function validateRow(array $row): array
    {
        $errors = [];

        if ($this->keyOf($row) === '') {
            $errors[] = '키(insurance_code / standard_code / barcode_gtin) 중 최소 1개는 비어있지 않아야 합니다.';
        }

        if (empty($row['product_name'])) {
            $errors[] = 'product_name 은 필수입니다.';
        }

        $drugType = $row['drug_type'] ?? null;
        if ($drugType !== null && $drugType !== '' && ! in_array($drugType, [
            Product::DRUG_TYPE_GENERAL,
            Product::DRUG_TYPE_ETC,
            Product::DRUG_TYPE_NARCOTIC,
            Product::DRUG_TYPE_PSYCHOTROPIC,
        ], true)) {
            $errors[] = "drug_type 값이 유효하지 않습니다: {$drugType}";
        }

        $storage = $row['storage_condition'] ?? null;
        if ($storage !== null && $storage !== '' && ! in_array($storage, ['room', 'cold', 'frozen'], true)) {
            $errors[] = "storage_condition 값이 유효하지 않습니다: {$storage}";
        }

        $status = $row['status'] ?? null;
        if ($status !== null && $status !== '' && ! in_array($status, [
            Product::STATUS_ACTIVE,
            Product::STATUS_INACTIVE,
            Product::STATUS_DISCONTINUED,
        ], true)) {
            $errors[] = "status 값이 유효하지 않습니다: {$status}";
        }

        foreach (['insurance_price', 'cost_price', 'sale_price'] as $c) {
            $v = $row[$c] ?? null;
            if ($v !== null && $v !== '') {
                if (! is_numeric($v) || (float) $v <= 0) {
                    $errors[] = "{$c} 는 0보다 큰 숫자여야 합니다.";
                }
            }
        }

        if (! empty($row['price_effective_from'])) {
            try {
                Carbon::parse($row['price_effective_from']);
            } catch (\Throwable) {
                $errors[] = "price_effective_from 형식이 잘못되었습니다: {$row['price_effective_from']}";
            }
        }

        if (! empty($row['pack_size']) && ! is_numeric($row['pack_size'])) {
            $errors[] = "pack_size 는 정수여야 합니다: {$row['pack_size']}";
        }

        return $errors;
    }

    protected function findExisting(array $row): ?Product
    {
        if (! empty($row['insurance_code'])) {
            $p = Product::where('insurance_code', $row['insurance_code'])->first();
            if ($p) {
                return $p;
            }
        }
        if (! empty($row['standard_code'])) {
            $p = Product::where('standard_code', $row['standard_code'])->first();
            if ($p) {
                return $p;
            }
        }
        if (! empty($row['barcode_gtin'])) {
            $p = Product::where('barcode_gtin', $row['barcode_gtin'])->first();
            if ($p) {
                return $p;
            }
        }

        return null;
    }

    protected function productPayload(array $row, bool $isCreate): array
    {
        $payload = [];
        $stringCols = [
            'insurance_code', 'standard_code', 'barcode_gtin', 'product_code',
            'product_name', 'generic_name', 'manufacturer', 'category',
            'drug_type', 'storage_condition', 'strength', 'unit', 'nims_item_code',
            'remarks', 'status',
        ];

        foreach ($stringCols as $c) {
            if (array_key_exists($c, $row) && $row[$c] !== null && $row[$c] !== '') {
                $payload[$c] = $row[$c];
            }
        }

        if (isset($row['pack_size']) && $row['pack_size'] !== '' && is_numeric($row['pack_size'])) {
            $payload['pack_size'] = (int) $row['pack_size'];
        }

        if ($isCreate && empty($payload['status'])) {
            $payload['status'] = Product::STATUS_ACTIVE;
        }

        // 신규 생성 시 product_code 가 없으면 insurance_code 로 폴백 (NOT NULL 제약 회피)
        if ($isCreate && empty($payload['product_code']) && ! empty($payload['insurance_code'])) {
            $payload['product_code'] = $payload['insurance_code'];
        }

        return $payload;
    }

    protected function priceColumn(string $type): string
    {
        return match ($type) {
            ProductPrice::TYPE_INSURANCE => 'insurance_price',
            ProductPrice::TYPE_COST => 'cost_price',
            ProductPrice::TYPE_SALE => 'sale_price',
        };
    }

    protected function keyOf(array $row): string
    {
        foreach (['insurance_code', 'standard_code', 'barcode_gtin'] as $k) {
            if (! empty($row[$k])) {
                return (string) $row[$k];
            }
        }

        return '';
    }

    protected function identifierOf(array $row): string
    {
        return (string) ($row['product_name'] ?? $this->keyOf($row));
    }
}
