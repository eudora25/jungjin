<?php

use App\Models\ChangeReason;
use App\Models\Company;
use App\Models\CompanyProductOverride;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\Products\CompanyProductOverrideService;
use App\Services\Products\ProductPriceService;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->sales = User::factory()->create(['role' => 'sales']);
    $this->product = Product::factory()->create();
    $this->company = Company::factory()->create(['default_commission_grade' => 'b']);
});

test('거래처 예외 등록 시 같은 거래처의 직전 이력이 자동 마감된다', function () {
    $svc = app(CompanyProductOverrideService::class);

    $first = $svc->register($this->product, [
        'company_id' => $this->company->id,
        'override_unit_price' => 9000,
        'effective_from' => '2026-01-01',
    ], $this->admin->id);

    expect($first->effective_to)->toBeNull();

    $second = $svc->register($this->product, [
        'company_id' => $this->company->id,
        'override_unit_price' => 8500,
        'effective_from' => '2026-04-01',
    ], $this->admin->id);

    $first->refresh();

    expect($first->effective_to->toDateString())->toBe('2026-03-31');
    expect($second->effective_to)->toBeNull();
});

test('다른 거래처의 이력은 서로 영향을 주지 않는다', function () {
    $other = Company::factory()->create();
    $svc = app(CompanyProductOverrideService::class);

    $a = $svc->register($this->product, [
        'company_id' => $this->company->id,
        'override_unit_price' => 9000,
        'effective_from' => '2026-01-01',
    ]);
    $b = $svc->register($this->product, [
        'company_id' => $other->id,
        'override_unit_price' => 7000,
        'effective_from' => '2026-04-01',
    ]);

    expect($a->fresh()->effective_to)->toBeNull();
    expect($b->fresh()->effective_to)->toBeNull();
});

test('effectiveSalePriceFor 우선순위: override → product_prices(sale) → products.price', function () {
    // 1) 아무 데이터도 없으면 products.price 폴백
    $product = Product::factory()->create(['price' => 5000]);
    expect($product->effectiveSalePriceFor($this->company))->toEqual(5000.0);

    // 2) sale price 등록 시 그것이 우선
    $priceSvc = app(ProductPriceService::class);
    $priceSvc->register($product, [
        'price_type' => ProductPrice::TYPE_SALE,
        'amount' => 4500,
        'effective_from' => '2026-01-01',
    ]);
    expect($product->fresh()->effectiveSalePriceFor($this->company))->toEqual(4500.0);

    // 3) override 등록 시 override가 최우선
    $overrideSvc = app(CompanyProductOverrideService::class);
    $overrideSvc->register($product, [
        'company_id' => $this->company->id,
        'override_unit_price' => 4000,
        'effective_from' => '2026-02-01',
    ]);
    expect($product->fresh()->effectiveSalePriceFor($this->company, \Carbon\Carbon::parse('2026-03-01')))
        ->toEqual(4000.0);

    // 4) override 적용 종료 후엔 다시 sale 적용
    expect($product->fresh()->effectiveSalePriceFor($this->company, \Carbon\Carbon::parse('2026-01-15')))
        ->toEqual(4500.0);
});

test('effectiveCommissionRateFor 우선순위: override → 등급 매트릭스', function () {
    $product = Product::factory()->create();
    $product->commissionRates()->create([
        'base_month' => '2026-01',
        'commission_rate_a' => 10,
        'commission_rate_b' => 12,
        'commission_rate_c' => 14,
        'commission_rate_d' => 16,
        'commission_rate_e' => 18,
        'effective_from' => '2026-01-01',
        'effective_to' => null,
        'status' => 'active',
    ]);

    expect($product->effectiveCommissionRateFor($this->company))->toEqual(12.0);

    app(CompanyProductOverrideService::class)->register($product, [
        'company_id' => $this->company->id,
        'override_commission_rate' => 7.5,
        'effective_from' => '2026-02-01',
    ]);

    expect($product->fresh()->effectiveCommissionRateFor($this->company, \Carbon\Carbon::parse('2026-03-01')))
        ->toEqual(7.5);
});

test('admin 만 거래처 예외를 등록/수정/삭제할 수 있다', function () {
    actingAs($this->sales)
        ->post(route('products.overrides.store', $this->product), [
            'company_id' => $this->company->id,
            'override_unit_price' => 1000,
            'effective_from' => '2026-01-01',
        ])->assertForbidden();

    actingAs($this->admin)
        ->post(route('products.overrides.store', $this->product), [
            'company_id' => $this->company->id,
            'override_unit_price' => 1000,
            'effective_from' => '2026-01-01',
        ])->assertRedirect();

    expect(CompanyProductOverride::count())->toBe(1);
});

test('단가/수수료율 둘 다 비어있으면 거부된다', function () {
    actingAs($this->admin)
        ->post(route('products.overrides.store', $this->product), [
            'company_id' => $this->company->id,
            'effective_from' => '2026-01-01',
        ])
        ->assertSessionHasErrors('override_unit_price');
});

test('같은 거래처+시작일 조합은 중복 등록될 수 없다', function () {
    app(CompanyProductOverrideService::class)->register($this->product, [
        'company_id' => $this->company->id,
        'override_unit_price' => 1000,
        'effective_from' => '2026-01-01',
    ]);

    actingAs($this->admin)
        ->post(route('products.overrides.store', $this->product), [
            'company_id' => $this->company->id,
            'override_unit_price' => 1100,
            'effective_from' => '2026-01-01',
        ])
        ->assertSessionHasErrors('effective_from');
});

test('CompanyProductOverride 변경은 product.override 채널로 activity log에 기록된다', function () {
    app(CompanyProductOverrideService::class)->register($this->product, [
        'company_id' => $this->company->id,
        'override_unit_price' => 5000,
        'effective_from' => '2026-01-01',
        'reason' => '시범 단가',
    ], $this->admin->id);

    $log = Activity::query()
        ->where('subject_type', CompanyProductOverride::class)
        ->where('event', 'created')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->log_name)->toBe('product.override');
    expect($log->properties->get('reason'))->toBe('시범 단가');
});

test('NIMS 관련 컬럼 변경은 nims.product 채널로 분리 기록된다', function () {
    $product = Product::factory()->create([
        'drug_type' => Product::DRUG_TYPE_GENERAL,
    ]);

    ChangeReason::with('마약류 전환', function () use ($product) {
        $product->update(['drug_type' => Product::DRUG_TYPE_NARCOTIC]);
    });

    $log = Activity::query()
        ->where('subject_type', Product::class)
        ->where('subject_id', $product->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->log_name)->toBe('nims.product');
    expect($log->properties->get('reason'))->toBe('마약류 전환');
});

test('승인 액션은 product.approve 로 audit log에 기록된다', function () {
    $product = Product::factory()->create([
        'approval_status' => Product::APPROVAL_REVIEWED,
        'reviewed_at' => now(),
        'reviewed_by' => $this->admin->id,
    ]);

    actingAs($this->admin)
        ->post(route('products.approve', $product))
        ->assertRedirect();

    $log = Activity::query()
        ->where('subject_type', Product::class)
        ->where('subject_id', $product->id)
        ->where('event', 'approve')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->description)->toBe('product.approve');
    expect($log->causer_id)->toBe($this->admin->id);
});

test('반려 액션은 사유를 properties.reason 에 영구 저장한다', function () {
    $product = Product::factory()->create([
        'approval_status' => Product::APPROVAL_PENDING,
    ]);

    actingAs($this->admin)
        ->post(route('products.reject', $product), ['reason' => '보험코드 불일치'])
        ->assertRedirect();

    $log = Activity::query()
        ->where('subject_type', Product::class)
        ->where('subject_id', $product->id)
        ->where('event', 'reject')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->properties->get('reason'))->toBe('보험코드 불일치');
});

test('단종 액션은 사유와 대체품을 properties 에 기록한다', function () {
    $product = Product::factory()->create();
    $replacement = Product::factory()->create();

    actingAs($this->admin)
        ->post(route('products.discontinue', $product), [
            'replacement_product_id' => $replacement->id,
            'reason' => '제조사 단종',
        ])
        ->assertRedirect();

    $log = Activity::query()
        ->where('subject_type', Product::class)
        ->where('subject_id', $product->id)
        ->where('event', 'discontinue')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->properties->get('reason'))->toBe('제조사 단종');
    expect((int) $log->properties->get('replacement_product_id'))->toBe($replacement->id);
});
