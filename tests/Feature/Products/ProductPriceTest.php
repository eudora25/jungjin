<?php

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\Products\ProductPriceService;
use Carbon\Carbon;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'pharma']);
    $this->sales = User::factory()->create(['role' => 'cso']);
    $this->product = Product::factory()->create();
});

test('새 가격을 등록하면 같은 종류의 직전 활성 이력이 자동으로 마감된다', function () {
    /** @var ProductPriceService $svc */
    $svc = app(ProductPriceService::class);

    $first = $svc->register($this->product, [
        'price_type' => ProductPrice::TYPE_SALE,
        'amount' => 1000,
        'effective_from' => '2026-01-01',
    ], $this->admin->id);

    expect($first->effective_to)->toBeNull();

    $second = $svc->register($this->product, [
        'price_type' => ProductPrice::TYPE_SALE,
        'amount' => 1500,
        'effective_from' => '2026-03-01',
    ], $this->admin->id);

    $first->refresh();

    expect($first->effective_to->toDateString())->toBe('2026-02-28');
    expect($second->effective_to)->toBeNull();
});

test('다른 가격 종류는 서로의 마감에 영향을 주지 않는다', function () {
    /** @var ProductPriceService $svc */
    $svc = app(ProductPriceService::class);

    $sale = $svc->register($this->product, [
        'price_type' => ProductPrice::TYPE_SALE,
        'amount' => 1000,
        'effective_from' => '2026-01-01',
    ]);

    $svc->register($this->product, [
        'price_type' => ProductPrice::TYPE_INSURANCE,
        'amount' => 800,
        'effective_from' => '2026-03-01',
    ]);

    $sale->refresh();
    expect($sale->effective_to)->toBeNull();
});

test('currentPriceOf 는 시점에 적용 중인 이력을 정확히 돌려준다', function () {
    /** @var ProductPriceService $svc */
    $svc = app(ProductPriceService::class);

    $svc->register($this->product, [
        'price_type' => ProductPrice::TYPE_SALE,
        'amount' => 1000,
        'effective_from' => '2026-01-01',
    ]);
    $svc->register($this->product, [
        'price_type' => ProductPrice::TYPE_SALE,
        'amount' => 2000,
        'effective_from' => '2026-04-01',
    ]);

    $product = $this->product->fresh();

    expect($product->currentPriceOf(ProductPrice::TYPE_SALE, Carbon::parse('2026-02-15'))->amount)
        ->toEqual(1000);
    expect($product->currentPriceOf(ProductPrice::TYPE_SALE, Carbon::parse('2026-04-15'))->amount)
        ->toEqual(2000);
    expect($product->currentPriceOf(ProductPrice::TYPE_SALE, Carbon::parse('2025-12-31')))
        ->toBeNull();
});

test('admin 은 가격 이력을 등록할 수 있다 (HTTP)', function () {
    $this->actingAs($this->admin)
        ->post(route('products.prices.store', $this->product), [
            'price_type' => 'sale',
            'amount' => 1500,
            'effective_from' => '2026-04-20',
            'source' => '단가 인상',
        ])
        ->assertRedirect();

    expect(ProductPrice::where('product_id', $this->product->id)->count())->toBe(1);
});

test('sales 는 가격 이력을 등록할 수 없다 (403)', function () {
    $this->actingAs($this->sales)
        ->post(route('products.prices.store', $this->product), [
            'price_type' => 'sale',
            'amount' => 1500,
            'effective_from' => '2026-04-20',
        ])
        ->assertForbidden();

    expect(ProductPrice::where('product_id', $this->product->id)->count())->toBe(0);
});

test('동일한 (종류 + 시작일) 조합은 422 에러로 차단된다', function () {
    $this->actingAs($this->admin)
        ->post(route('products.prices.store', $this->product), [
            'price_type' => 'sale',
            'amount' => 1000,
            'effective_from' => '2026-04-20',
        ])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->post(route('products.prices.store', $this->product), [
            'price_type' => 'sale',
            'amount' => 2000,
            'effective_from' => '2026-04-20',
        ])
        ->assertSessionHasErrors('effective_from');
});

test('가격 0 이하 는 검증 실패한다', function () {
    $this->actingAs($this->admin)
        ->post(route('products.prices.store', $this->product), [
            'price_type' => 'sale',
            'amount' => 0,
            'effective_from' => '2026-04-20',
        ])
        ->assertSessionHasErrors('amount');
});

test('가격 수정은 amount/source/note/effective_to 만 변경된다', function () {
    /** @var ProductPriceService $svc */
    $svc = app(ProductPriceService::class);

    $price = $svc->register($this->product, [
        'price_type' => ProductPrice::TYPE_SALE,
        'amount' => 1000,
        'effective_from' => '2026-01-01',
    ]);

    $this->actingAs($this->admin)
        ->put(route('products.prices.update', ['product' => $this->product->id, 'price' => $price->id]), [
            'amount' => 1234,
            'source' => '수정 사유',
            'effective_to' => '2026-12-31',
        ])
        ->assertRedirect();

    $price->refresh();
    expect((float) $price->amount)->toBe(1234.0);
    expect($price->source)->toBe('수정 사유');
    expect($price->effective_to->toDateString())->toBe('2026-12-31');
    expect($price->effective_from->toDateString())->toBe('2026-01-01');
});

test('가격 삭제는 admin 만 가능하다', function () {
    /** @var ProductPriceService $svc */
    $svc = app(ProductPriceService::class);
    $price = $svc->register($this->product, [
        'price_type' => ProductPrice::TYPE_SALE,
        'amount' => 1000,
        'effective_from' => '2026-01-01',
    ]);

    $this->actingAs($this->sales)
        ->delete(route('products.prices.destroy', ['product' => $this->product->id, 'price' => $price->id]))
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->delete(route('products.prices.destroy', ['product' => $this->product->id, 'price' => $price->id]))
        ->assertRedirect();

    expect(ProductPrice::find($price->id))->toBeNull();
});

test('latestPricesByType 는 종류별 1건씩 묶어서 돌려준다', function () {
    /** @var ProductPriceService $svc */
    $svc = app(ProductPriceService::class);

    $svc->register($this->product, ['price_type' => 'insurance', 'amount' => 500, 'effective_from' => '2026-01-01']);
    $svc->register($this->product, ['price_type' => 'cost', 'amount' => 200, 'effective_from' => '2026-01-01']);
    $svc->register($this->product, ['price_type' => 'sale', 'amount' => 1500, 'effective_from' => '2026-01-01']);
    $svc->register($this->product, ['price_type' => 'sale', 'amount' => 1800, 'effective_from' => '2026-04-01']);

    $latest = $this->product->fresh()->latestPricesByType();

    expect((float) $latest['insurance']->amount)->toBe(500.0);
    expect((float) $latest['cost']->amount)->toBe(200.0);
    expect((float) $latest['sale']->amount)->toBe(1800.0);
});
