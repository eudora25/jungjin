<?php

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->sales = User::factory()->create(['role' => 'sales']);
    Storage::fake('local');
});

/**
 * 임시 CSV 파일 생성 헬퍼.
 */
function makeCsvUpload(string $contents, string $name = 'products.csv'): UploadedFile
{
    $tmp = tempnam(sys_get_temp_dir(), 'csvimport_');
    file_put_contents($tmp, $contents);

    return new UploadedFile($tmp, $name, 'text/csv', null, true);
}

test('admin 이 CSV 분석을 요청하면 결과가 화면에 노출된다 (analyze)', function () {
    $csv = "insurance_code,product_name,sale_price\nA00000001,테스트정 100mg,1500\n";

    $response = $this->actingAs($this->admin)
        ->post(route('products.import.handle'), [
            'file' => makeCsvUpload($csv),
            'mode' => 'analyze',
        ]);

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Products/Import')
            ->has('analysis.token')
            ->where('analysis.row_count', 1)
            ->where('analysis.summary.create', 1)
            ->where('analysis.summary.error', 0),
    );
});

test('CSV commit 으로 신규 제품 + 가격 이력이 함께 생성된다', function () {
    $csv = "insurance_code,product_name,sale_price,price_effective_from\nA00000010,바테스정,2000,2026-04-20\n";

    // 1) analyze
    $response = $this->actingAs($this->admin)
        ->post(route('products.import.handle'), [
            'file' => makeCsvUpload($csv),
            'mode' => 'analyze',
        ]);

    $token = $response->viewData('page')['props']['analysis']['token'];
    expect($token)->not->toBeNull();

    // 2) commit
    $this->actingAs($this->admin)
        ->post(route('products.import.handle'), [
            'token' => $token,
            'mode' => 'commit',
        ])
        ->assertRedirect(route('products.index'));

    $product = Product::where('insurance_code', 'A00000010')->first();
    expect($product)->not->toBeNull();
    expect($product->approval_status)->toBe(Product::APPROVAL_DRAFT);
    expect($product->prices()->count())->toBe(1);
    expect($product->prices()->first()->price_type)->toBe(ProductPrice::TYPE_SALE);
    expect((float) $product->prices()->first()->amount)->toBe(2000.0);
});

test('기존 제품(insurance_code)은 update 동작으로 처리된다', function () {
    $existing = Product::factory()->create([
        'insurance_code' => 'A00000020',
        'product_name' => '기존정',
        'manufacturer' => '구제약',
    ]);

    $csv = "insurance_code,product_name,manufacturer\nA00000020,개정된정,신제약\n";

    $analyze = $this->actingAs($this->admin)
        ->post(route('products.import.handle'), [
            'file' => makeCsvUpload($csv),
            'mode' => 'analyze',
        ]);

    $token = $analyze->viewData('page')['props']['analysis']['token'];

    $this->actingAs($this->admin)
        ->post(route('products.import.handle'), [
            'token' => $token,
            'mode' => 'commit',
        ])
        ->assertRedirect();

    $existing->refresh();
    expect($existing->product_name)->toBe('개정된정');
    expect($existing->manufacturer)->toBe('신제약');
});

test('한 행이라도 검증 오류가 있으면 어떤 행도 적용되지 않는다 (all-or-nothing)', function () {
    $csv = "insurance_code,product_name,sale_price\n"
        ."A00000030,정상정,1000\n"
        ."A00000031,오류정,-500\n"; // 음수

    $response = $this->actingAs($this->admin)
        ->post(route('products.import.handle'), [
            'file' => makeCsvUpload($csv),
            'mode' => 'analyze',
        ]);

    $response->assertInertia(
        fn ($page) => $page
            ->where('analysis.summary.error', 1)
            ->where('analysis.summary.create', 1),
    );

    $token = $response->viewData('page')['props']['analysis']['token'];

    $this->actingAs($this->admin)
        ->post(route('products.import.handle'), [
            'token' => $token,
            'mode' => 'commit',
        ])
        ->assertRedirect();

    expect(Product::where('insurance_code', 'A00000030')->exists())->toBeFalse();
    expect(Product::where('insurance_code', 'A00000031')->exists())->toBeFalse();
});

test('알 수 없는 컬럼이 있는 CSV 는 분석 단계에서 거부된다', function () {
    $csv = "insurance_code,product_name,unknown_col\nA00000040,이상정,X\n";

    $this->actingAs($this->admin)
        ->post(route('products.import.handle'), [
            'file' => makeCsvUpload($csv),
            'mode' => 'analyze',
        ])
        ->assertSessionHasErrors('file');
});

test('sales 사용자는 CSV 일괄 등록 화면에 접근할 수 없다', function () {
    $this->actingAs($this->sales)
        ->get(route('products.import.form'))
        ->assertForbidden();
});

test('샘플 CSV 다운로드는 헤더 + 예시 1행을 반환한다', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('products.import.sample'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $body = $response->getContent();
    expect($body)->toContain('insurance_code');
    expect($body)->toContain('sale_price');
    expect(substr_count($body, "\n"))->toBeGreaterThanOrEqual(2);
});

test('CSV 의 가격 컬럼은 직전 자동 마감 로직과 함께 동작한다', function () {
    $product = Product::factory()->create(['insurance_code' => 'A00000050']);

    // 첫 번째 가격 (직접 등록)
    app(\App\Services\Products\ProductPriceService::class)->register($product, [
        'price_type' => 'sale',
        'amount' => 1000,
        'effective_from' => '2026-01-01',
    ]);

    // CSV 로 새 가격 등록 (2026-04-01)
    $csv = "insurance_code,product_name,sale_price,price_effective_from\nA00000050,{$product->product_name},2000,2026-04-01\n";

    $analyze = $this->actingAs($this->admin)
        ->post(route('products.import.handle'), [
            'file' => makeCsvUpload($csv),
            'mode' => 'analyze',
        ]);

    $token = $analyze->viewData('page')['props']['analysis']['token'];

    $this->actingAs($this->admin)
        ->post(route('products.import.handle'), [
            'token' => $token,
            'mode' => 'commit',
        ])
        ->assertRedirect();

    $prices = $product->prices()->ofType('sale')->reorder('effective_from', 'asc')->get();

    expect($prices)->toHaveCount(2);
    expect($prices[0]->effective_to->toDateString())->toBe('2026-03-31');
    expect((float) $prices[1]->amount)->toBe(2000.0);
    expect($prices[1]->effective_to)->toBeNull();
});
