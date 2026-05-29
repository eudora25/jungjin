<?php

use App\Models\ChangeReason;
use App\Models\Company;
use App\Models\CompanyProductOverride;
use App\Models\Performance;
use App\Models\Product;
use App\Models\ProductCommissionRate;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\Performance\PerformanceResolver;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->company = Company::factory()->create(['default_commission_grade' => 'B']);
    $this->product = Product::factory()->create(['price' => 10000]);
});

/* ---------------------------------------------------------------- *
 | 1. 단가(unit_price) 스냅샷 우선순위                                |
 * ---------------------------------------------------------------- */

test('단가 우선순위: override > product_prices(sale) > products.price', function () {
    // (a) fallback: products.price
    $resolver = app(PerformanceResolver::class);
    $resolved = $resolver->resolve($this->company, $this->product, '2026-04-20');

    expect($resolved['unit_price'])->toBe(10000.0)
        ->and($resolved['price_source'])->toBe(Performance::PRICE_SOURCE_PRODUCTS_PRICE)
        ->and($resolved['price_id'])->toBeNull()
        ->and($resolved['price_override_id'])->toBeNull();

    // (b) product_prices(sale) 가 있으면 그걸로
    $sale = ProductPrice::create([
        'product_id' => $this->product->id,
        'price_type' => ProductPrice::TYPE_SALE,
        'amount' => 9500,
        'effective_from' => '2026-01-01',
    ]);
    $resolved = $resolver->resolve($this->company, $this->product->fresh(), '2026-04-20');

    expect($resolved['unit_price'])->toBe(9500.0)
        ->and($resolved['price_source'])->toBe(Performance::PRICE_SOURCE_PRODUCT_SALE)
        ->and($resolved['price_id'])->toBe($sale->id);

    // (c) override 가 있으면 최우선
    $override = CompanyProductOverride::factory()
        ->priceOnly()
        ->create([
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'override_unit_price' => 8800,
            'effective_from' => '2026-04-01',
        ]);

    $resolved = $resolver->resolve($this->company, $this->product->fresh(), '2026-04-20');

    expect($resolved['unit_price'])->toBe(8800.0)
        ->and($resolved['price_source'])->toBe(Performance::PRICE_SOURCE_OVERRIDE)
        ->and($resolved['price_override_id'])->toBe($override->id);
});

/* ---------------------------------------------------------------- *
 | 2. 수수료율(commission_rate) 스냅샷 우선순위                       |
 * ---------------------------------------------------------------- */

test('수수료율 우선순위: override > matrix × grade > none', function () {
    $resolver = app(PerformanceResolver::class);

    // (a) 아무것도 없으면 none
    $resolved = $resolver->resolve($this->company, $this->product, '2026-04-20');
    expect($resolved['commission_rate'])->toBeNull()
        ->and($resolved['commission_source'])->toBe(Performance::COMMISSION_SOURCE_NONE);

    // (b) matrix — company.grade=B 컬럼의 값이 적용되어야
    $matrix = ProductCommissionRate::create([
        'product_id' => $this->product->id,
        'base_month' => '2026-04',
        'commission_rate_a' => 10,
        'commission_rate_b' => 12.5,
        'commission_rate_c' => 15,
        'commission_rate_d' => 18,
        'commission_rate_e' => 20,
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    $resolved = $resolver->resolve($this->company, $this->product->fresh(), '2026-04-20');
    expect($resolved['commission_rate'])->toBe(12.5)
        ->and($resolved['commission_source'])->toBe(Performance::COMMISSION_SOURCE_MATRIX)
        ->and($resolved['commission_rate_id'])->toBe($matrix->id);

    // (c) override 가 있으면 최우선
    CompanyProductOverride::factory()
        ->commissionOnly()
        ->create([
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'override_commission_rate' => 7.5,
            'effective_from' => '2026-04-01',
        ]);

    $resolved = $resolver->resolve($this->company, $this->product->fresh(), '2026-04-20');
    expect($resolved['commission_rate'])->toBe(7.5)
        ->and($resolved['commission_source'])->toBe(Performance::COMMISSION_SOURCE_OVERRIDE);
});

/* ---------------------------------------------------------------- *
 | 3. 시점(performance_date) 기반 해석                                |
 * ---------------------------------------------------------------- */

test('과거 시점으로 해석하면 그 시점에 유효했던 가격이 적용된다', function () {
    ProductPrice::create([
        'product_id' => $this->product->id,
        'price_type' => ProductPrice::TYPE_SALE,
        'amount' => 9000,
        'effective_from' => '2026-01-01',
        'effective_to' => '2026-02-28',
    ]);
    ProductPrice::create([
        'product_id' => $this->product->id,
        'price_type' => ProductPrice::TYPE_SALE,
        'amount' => 9500,
        'effective_from' => '2026-03-01',
    ]);

    $resolver = app(PerformanceResolver::class);

    expect($resolver->resolve($this->company, $this->product->fresh(), '2026-02-15')['unit_price'])->toBe(9000.0);
    expect($resolver->resolve($this->company, $this->product->fresh(), '2026-04-20')['unit_price'])->toBe(9500.0);
});

/* ---------------------------------------------------------------- *
 | 4. Performance 저장 시 스냅샷과 가상 컬럼(subtotal, commission_amount) |
 * ---------------------------------------------------------------- */

test('Performance 저장 시 subtotal 과 commission_amount 가 DB 에서 계산된다', function () {
    // 세팅: 단가 10000, 수수료율 10%
    ProductPrice::create([
        'product_id' => $this->product->id,
        'price_type' => ProductPrice::TYPE_SALE,
        'amount' => 10000,
        'effective_from' => '2026-01-01',
    ]);
    ProductCommissionRate::create([
        'product_id' => $this->product->id,
        'base_month' => '2026-04',
        'commission_rate_a' => 10, 'commission_rate_b' => 10,
        'commission_rate_c' => 10, 'commission_rate_d' => 10, 'commission_rate_e' => 10,
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    /** @var PerformanceResolver $resolver */
    $resolver = app(PerformanceResolver::class);
    $perf = new Performance();
    $resolver->fill($perf, $this->company, $this->product->fresh(), [
        'performance_no' => Performance::nextNumberFor('2026-04-20'),
        'performance_date' => '2026-04-20',
        'quantity' => 7,
        'created_by' => $this->admin->id,
    ]);
    $perf->save();
    $perf->refresh();

    expect((float) $perf->unit_price)->toBe(10000.0)
        ->and((float) $perf->commission_rate)->toBe(10.0)
        ->and((float) $perf->subtotal)->toBe(70000.0)
        ->and((float) $perf->commission_amount)->toBe(7000.0)
        ->and($perf->price_source)->toBe(Performance::PRICE_SOURCE_PRODUCT_SALE)
        ->and($perf->commission_source)->toBe(Performance::COMMISSION_SOURCE_MATRIX);
});

test('commission_rate 가 null 이면 commission_amount 도 null', function () {
    ProductPrice::create([
        'product_id' => $this->product->id,
        'price_type' => ProductPrice::TYPE_SALE,
        'amount' => 5000,
        'effective_from' => '2026-01-01',
    ]);

    /** @var PerformanceResolver $resolver */
    $resolver = app(PerformanceResolver::class);
    $perf = new Performance();
    $resolver->fill($perf, $this->company, $this->product->fresh(), [
        'performance_no' => Performance::nextNumberFor('2026-04-20'),
        'performance_date' => '2026-04-20',
        'quantity' => 3,
        'created_by' => $this->admin->id,
    ]);
    $perf->save();
    $perf->refresh();

    expect($perf->commission_rate)->toBeNull()
        ->and($perf->commission_amount)->toBeNull()
        ->and((float) $perf->subtotal)->toBe(15000.0);
});

/* ---------------------------------------------------------------- *
 | 5. Audit log                                                       |
 * ---------------------------------------------------------------- */

test('Performance 생성 시 activity_log 에 performance 채널로 기록된다', function () {
    ProductPrice::create([
        'product_id' => $this->product->id,
        'price_type' => ProductPrice::TYPE_SALE,
        'amount' => 1000,
        'effective_from' => '2026-01-01',
    ]);

    ChangeReason::with('2026-04-20 E2E 테스트', function () {
        $resolver = app(PerformanceResolver::class);
        $perf = new Performance();
        $resolver->fill($perf, $this->company, $this->product->fresh(), [
            'performance_no' => Performance::nextNumberFor('2026-04-20'),
            'performance_date' => '2026-04-20',
            'quantity' => 2,
            'created_by' => $this->admin->id,
        ]);
        $perf->save();
    });

    $log = Activity::where('log_name', 'performance')
        ->where('subject_type', Performance::class)
        ->where('event', 'created')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->properties->get('reason'))->toBe('2026-04-20 E2E 테스트');
});

/* ---------------------------------------------------------------- *
 | 6. 번호 발급                                                       |
 * ---------------------------------------------------------------- */

test('실적 번호는 YYYYMMDD-NNNN 형식으로 순증한다', function () {
    Performance::factory()->create([
        'performance_no' => Performance::nextNumberFor('2026-04-20'),
        'performance_date' => '2026-04-20',
    ]);
    Performance::factory()->create([
        'performance_no' => Performance::nextNumberFor('2026-04-20'),
        'performance_date' => '2026-04-20',
    ]);

    $next = Performance::nextNumberFor('2026-04-20');
    expect($next)->toBe('20260420-0003');
});
