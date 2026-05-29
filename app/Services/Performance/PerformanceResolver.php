<?php

namespace App\Services\Performance;

use App\Models\Company;
use App\Models\Performance;
use App\Models\Product;
use App\Models\ProductPrice;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * 실적 스냅샷 해석기.
 *
 * `Product::effectiveSalePriceFor` / `effectiveCommissionRateFor` 는 "값"만 돌려주기 때문에,
 * 이 서비스는 **"그 값이 어디서 왔는가"(source)** 와 **연관 FK** 까지 함께 반환한다.
 * 실적을 저장할 때 이 결과를 그대로 스냅샷 컬럼에 박아 넣는다.
 */
class PerformanceResolver
{
    /**
     * @return array{
     *     unit_price: float,
     *     commission_rate: float|null,
     *     price_source: string,
     *     price_override_id: int|null,
     *     price_id: int|null,
     *     commission_source: string,
     *     commission_rate_id: int|null,
     * }
     */
    public function resolve(Company $company, Product $product, CarbonInterface|string|null $on = null): array
    {
        $on = $on ? Carbon::parse($on) : now();
        $override = $product->activeOverrideFor($company, $on);

        return array_merge(
            $this->resolvePrice($override, $product, $on),
            $this->resolveCommission($override, $company, $product, $on),
        );
    }

    /**
     * 해석 결과로 Performance 모델을 채워 반환 (save 전). 번호/날짜/수량은 호출부에서 주입.
     *
     * @param  array<string, mixed>  $base
     */
    public function fill(Performance $perf, Company $company, Product $product, array $base = []): Performance
    {
        $date = $base['performance_date'] ?? $perf->performance_date ?? now();
        $resolved = $this->resolve($company, $product, $date);

        $perf->fill(array_merge(
            $base,
            [
                'company_id' => $company->id,
                'product_id' => $product->id,
            ],
            $resolved,
        ));

        return $perf;
    }

    /**
     * @param  \App\Models\CompanyProductOverride|null  $override  resolve() 에서 한 번만 조회한 override
     * @return array{unit_price: float, price_source: string, price_override_id: int|null, price_id: int|null}
     */
    private function resolvePrice(mixed $override, Product $product, CarbonInterface $on): array
    {
        if ($override && $override->override_unit_price !== null) {
            return [
                'unit_price' => (float) $override->override_unit_price,
                'price_source' => Performance::PRICE_SOURCE_OVERRIDE,
                'price_override_id' => $override->id,
                'price_id' => null,
            ];
        }

        $sale = $product->currentPriceOf(ProductPrice::TYPE_SALE, $on);
        if ($sale) {
            return [
                'unit_price' => (float) $sale->amount,
                'price_source' => Performance::PRICE_SOURCE_PRODUCT_SALE,
                'price_override_id' => null,
                'price_id' => $sale->id,
            ];
        }

        // Fallback: products.price
        return [
            'unit_price' => $product->price !== null ? (float) $product->price : 0.0,
            'price_source' => Performance::PRICE_SOURCE_PRODUCTS_PRICE,
            'price_override_id' => null,
            'price_id' => null,
        ];
    }

    /**
     * @param  \App\Models\CompanyProductOverride|null  $override  resolve() 에서 한 번만 조회한 override
     * @return array{commission_rate: float|null, commission_source: string, commission_rate_id: int|null}
     */
    private function resolveCommission(mixed $override, Company $company, Product $product, CarbonInterface $on): array
    {
        if ($override && $override->override_commission_rate !== null) {
            return [
                'commission_rate' => (float) $override->override_commission_rate,
                'commission_source' => Performance::COMMISSION_SOURCE_OVERRIDE,
                'commission_rate_id' => null,
            ];
        }

        $matrix = $product->currentCommissionRate($on);
        if ($matrix) {
            $grade = strtolower((string) ($company->default_commission_grade ?? 'a'));
            $col = "commission_rate_{$grade}";
            $rate = isset($matrix->{$col}) ? (float) $matrix->{$col} : null;

            if ($rate !== null) {
                return [
                    'commission_rate' => $rate,
                    'commission_source' => Performance::COMMISSION_SOURCE_MATRIX,
                    'commission_rate_id' => $matrix->id,
                ];
            }
        }

        return [
            'commission_rate' => null,
            'commission_source' => Performance::COMMISSION_SOURCE_NONE,
            'commission_rate_id' => null,
        ];
    }
}
