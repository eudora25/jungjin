<?php

use App\Models\Company;
use App\Models\Performance;
use App\Models\Product;
use App\Models\User;
use App\Services\Performance\PerformanceImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'pharma']);
    $this->company = Company::factory()->create([
        'company_name' => '테스트약국',
        'business_registration_number' => '111-22-33333',
        'default_commission_grade' => 'B',
    ]);
    $this->product = Product::factory()->create([
        'insurance_code' => 'INS-001',
        'product_code' => 'P-001',
        'price' => 10000,
    ]);
    $this->service = app(PerformanceImportService::class);
});

test('헤더 검증: 필수 컬럼 누락 시 에러', function () {
    $errors = $this->service->validateHeaders(['company_name', 'quantity']);
    expect($errors)->toContain('performance_date 컬럼은 필수입니다.')
        ->and($errors)->toContain('제품 키(insurance_code / product_code) 중 최소 1개가 필요합니다.');
});

test('헤더 검증: 허용 컬럼만 있으면 통과', function () {
    $errors = $this->service->validateHeaders([
        'performance_date', 'company_biz_no', 'insurance_code', 'quantity',
    ]);
    expect($errors)->toBe([]);
});

test('analyze: 정상 행은 create 로 분류되고 스냅샷 미리보기가 계산된다', function () {
    $rows = [[
        '_line' => 2,
        'performance_date' => '2026-04-20',
        'company_biz_no' => '111-22-33333',
        'insurance_code' => 'INS-001',
        'quantity' => '5',
    ]];

    $result = $this->service->analyze($rows);

    expect($result['summary']['create'])->toBe(1)
        ->and($result['summary']['error'])->toBe(0)
        ->and($result['results'][0]['action'])->toBe('create')
        ->and($result['results'][0]['preview']['unit_price'])->toBe(10000.0)
        ->and($result['results'][0]['preview']['subtotal'])->toBe(50000.0);
});

test('analyze: 존재하지 않는 거래처는 에러', function () {
    $rows = [[
        '_line' => 2,
        'performance_date' => '2026-04-20',
        'company_biz_no' => '999-99-99999',
        'insurance_code' => 'INS-001',
        'quantity' => '5',
    ]];

    $result = $this->service->analyze($rows);

    expect($result['summary']['error'])->toBe(1)
        ->and($result['results'][0]['errors'])->toContain('거래처를 찾을 수 없습니다. (company_biz_no / company_name 확인)');
});

test('analyze: quantity 가 0 이면 에러', function () {
    $rows = [[
        '_line' => 2,
        'performance_date' => '2026-04-20',
        'company_biz_no' => '111-22-33333',
        'insurance_code' => 'INS-001',
        'quantity' => '0',
    ]];

    $result = $this->service->analyze($rows);

    expect($result['summary']['error'])->toBe(1)
        ->and($result['results'][0]['errors'])->toContain('quantity 는 0 일 수 없습니다. (반품은 음수로 입력)');
});

test('import: 오류가 한 건이라도 있으면 전체 롤백', function () {
    $rows = [
        ['_line' => 2, 'performance_date' => '2026-04-20', 'company_biz_no' => '111-22-33333', 'insurance_code' => 'INS-001', 'quantity' => '5'],
        ['_line' => 3, 'performance_date' => '2026-04-20', 'company_biz_no' => '999-99-99999', 'insurance_code' => 'INS-001', 'quantity' => '5'],
    ];

    $result = $this->service->import($rows, $this->admin->id);

    expect($result['committed'])->toBeFalse()
        ->and(Performance::count())->toBe(0);
});

test('import: 모두 통과하면 저장되고 ChangeReason 이 채워진다', function () {
    $rows = [
        ['_line' => 2, 'performance_date' => '2026-04-20', 'company_name' => '테스트약국', 'product_code' => 'P-001', 'quantity' => '5', 'note' => '첫 건'],
        ['_line' => 3, 'performance_date' => '2026-04-20', 'company_biz_no' => '111-22-33333', 'insurance_code' => 'INS-001', 'quantity' => '-2'],
    ];

    $result = $this->service->import($rows, $this->admin->id);

    expect($result['committed'])->toBeTrue()
        ->and($result['summary']['create'])->toBe(2)
        ->and(Performance::count())->toBe(2)
        ->and(Performance::first()->status)->toBe(Performance::STATUS_DRAFT)
        ->and(Performance::first()->created_by)->toBe($this->admin->id);

    $activities = Activity::where('log_name', 'performance')->get();
    expect($activities->first()->properties['reason'] ?? null)->toBe('실적 CSV 일괄 등록');
});

test('HTTP: analyze 모드는 분석 결과를 Inertia prop 으로 반환', function () {
    Storage::fake('local');

    $csv = "performance_date,company_biz_no,insurance_code,quantity\n"
        ."2026-04-20,111-22-33333,INS-001,5\n";

    $tmp = tempnam(sys_get_temp_dir(), 'perfimport_');
    file_put_contents($tmp, $csv);
    $file = new UploadedFile($tmp, 'performances.csv', 'text/csv', null, true);

    $response = $this->actingAs($this->admin)
        ->post(route('performance.import.handle'), [
            'file' => $file,
            'mode' => 'analyze',
        ]);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Performance/Import')
        ->has('analysis.token')
        ->where('analysis.summary.create', 1)
        ->where('analysis.summary.error', 0)
    );

    expect(Performance::count())->toBe(0);
});

test('HTTP: commit 은 실적을 draft 로 저장하고 목록으로 리다이렉트', function () {
    Storage::fake('local');

    $csv = "performance_date,company_biz_no,insurance_code,quantity\n"
        ."2026-04-20,111-22-33333,INS-001,3\n";

    $tmp = tempnam(sys_get_temp_dir(), 'perfimport_');
    file_put_contents($tmp, $csv);
    $file = new UploadedFile($tmp, 'performances.csv', 'text/csv', null, true);

    $analyze = $this->actingAs($this->admin)
        ->post(route('performance.import.handle'), [
            'file' => $file,
            'mode' => 'analyze',
        ]);

    $token = $analyze->viewData('page')['props']['analysis']['token'];
    expect($token)->not->toBeNull();

    $this->actingAs($this->admin)
        ->post(route('performance.import.handle'), [
            'token' => $token,
            'mode' => 'commit',
        ])
        ->assertRedirect(route('performance.index'));

    expect(Performance::count())->toBe(1)
        ->and(Performance::first()->quantity)->toBe(3)
        ->and(Performance::first()->status)->toBe(Performance::STATUS_DRAFT);
});
