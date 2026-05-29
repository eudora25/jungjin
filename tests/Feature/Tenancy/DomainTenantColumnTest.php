<?php

use App\Models\Company;
use App\Models\Performance;
use App\Models\Product;
use App\Models\SalesQuota;
use App\Models\Settlement;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-10 MT-4 — 도메인 루트 테이블 tenant_id 부착.
 */
test('5개 도메인 테이블에 tenant_id 컬럼이 존재한다', function () {
    foreach (['products', 'companies', 'performances', 'settlements', 'sales_quotas'] as $t) {
        expect(Schema::hasColumn($t, 'tenant_id'))->toBeTrue("{$t}.tenant_id 누락");
    }
});

test('팩토리는 기본 제약사로 tenant_id 를 채운다', function () {
    $default = Tenant::default();

    expect(Product::factory()->create()->tenant_id)->toBe($default->id)
        ->and(Company::factory()->create()->tenant_id)->toBe($default->id)
        ->and(Performance::factory()->create()->tenant_id)->toBe($default->id)
        ->and(Settlement::factory()->create()->tenant_id)->toBe($default->id)
        ->and(SalesQuota::factory()->create()->tenant_id)->toBe($default->id);
});

test('tenant() 관계로 제약사를 조회할 수 있다', function () {
    $tenant = Tenant::factory()->create(['name' => '종근당']);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    expect($product->tenant->is($tenant))->toBeTrue()
        ->and($product->tenant->name)->toBe('종근당');
});

test('tenant_id FK — 존재하지 않는 제약사 ID 는 거부된다', function () {
    expect(fn () => Product::factory()->create(['tenant_id' => 999999]))
        ->toThrow(QueryException::class);
});

test('현재 tenant_id 는 nullable — NOT NULL 전환은 MT-3(자동 주입) 이후', function () {
    // MT-4 단계: 앱 생성 경로가 아직 tenant_id 를 세팅하지 않으므로 nullable 유지.
    expect(Product::factory()->create(['tenant_id' => null])->tenant_id)->toBeNull();
});
