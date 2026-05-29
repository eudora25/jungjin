<?php

use App\Models\Product;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'pharma']);
    $this->sales = User::factory()->create(['role' => 'cso']);
});

test('admin은 draft 제품의 검수를 요청할 수 있다', function () {
    $product = Product::factory()->create(['approval_status' => Product::APPROVAL_DRAFT]);

    $this->actingAs($this->admin)
        ->post(route('products.submit', $product))
        ->assertRedirect();

    expect($product->fresh()->approval_status)->toBe(Product::APPROVAL_PENDING);
});

test('admin은 rejected 제품도 다시 검수 요청할 수 있다', function () {
    $product = Product::factory()->create(['approval_status' => Product::APPROVAL_REJECTED]);

    $this->actingAs($this->admin)
        ->post(route('products.submit', $product))
        ->assertRedirect();

    expect($product->fresh()->approval_status)->toBe(Product::APPROVAL_PENDING);
});

test('이미 pending인 제품은 다시 검수 요청할 수 없다 (403)', function () {
    $product = Product::factory()->create(['approval_status' => Product::APPROVAL_PENDING]);

    $this->actingAs($this->admin)
        ->post(route('products.submit', $product))
        ->assertForbidden();
});

test('sales는 검수 요청을 할 수 없다 (403)', function () {
    $product = Product::factory()->create(['approval_status' => Product::APPROVAL_DRAFT]);

    $this->actingAs($this->sales)
        ->post(route('products.submit', $product))
        ->assertForbidden();
});

test('전체 승인 흐름: draft → pending → reviewed → approved', function () {
    $product = Product::factory()->create(['approval_status' => Product::APPROVAL_DRAFT]);

    // 검수 요청
    $this->actingAs($this->admin)
        ->post(route('products.submit', $product))
        ->assertRedirect();
    expect($product->fresh()->approval_status)->toBe(Product::APPROVAL_PENDING);

    // 검수 완료
    $this->actingAs($this->admin)
        ->post(route('products.review', $product))
        ->assertRedirect();
    $product->refresh();
    expect($product->approval_status)->toBe(Product::APPROVAL_REVIEWED);
    expect($product->reviewed_at)->not->toBeNull();
    expect($product->reviewed_by)->toBe($this->admin->id);

    // 최종 승인
    $this->actingAs($this->admin)
        ->post(route('products.approve', $product))
        ->assertRedirect();
    $product->refresh();
    expect($product->approval_status)->toBe(Product::APPROVAL_APPROVED);
    expect($product->approved_at)->not->toBeNull();
    expect($product->approved_by)->toBe($this->admin->id);
});

test('reviewed 가 아닌 상태에서는 최종 승인 불가 (403)', function () {
    $product = Product::factory()->create(['approval_status' => Product::APPROVAL_PENDING]);

    $this->actingAs($this->admin)
        ->post(route('products.approve', $product))
        ->assertForbidden();
});

test('반려 시 사유는 필수이며 (422), 사유 입력 시 rejected 로 전환된다', function () {
    $product = Product::factory()->create(['approval_status' => Product::APPROVAL_PENDING]);

    // 사유 없이 → 422
    $this->actingAs($this->admin)
        ->post(route('products.reject', $product), [])
        ->assertSessionHasErrors('reason');

    expect($product->fresh()->approval_status)->toBe(Product::APPROVAL_PENDING);

    // 사유 입력 → rejected
    $this->actingAs($this->admin)
        ->post(route('products.reject', $product), ['reason' => 'NIMS 정보 불일치'])
        ->assertRedirect();

    $product->refresh();
    expect($product->approval_status)->toBe(Product::APPROVAL_REJECTED);

    // P3-S5 이후: 사유는 remarks 부기가 아닌 audit log properties.reason 에 저장됨
    $reject = Activity::query()
        ->where('subject_type', Product::class)
        ->where('subject_id', $product->id)
        ->where('event', 'reject')
        ->latest('id')
        ->first();
    expect($reject)->not->toBeNull();
    expect($reject->properties->get('reason'))->toBe('NIMS 정보 불일치');
});

test('단종 처리 시 status가 discontinued로 바뀌고 대체품/사유가 반영된다', function () {
    $product = Product::factory()->create(['status' => Product::STATUS_ACTIVE]);
    $replacement = Product::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('products.discontinue', $product), [
            'replacement_product_id' => $replacement->id,
            'reason' => '제조사 단종 통보',
        ])
        ->assertRedirect();

    $product->refresh();
    expect($product->status)->toBe(Product::STATUS_DISCONTINUED);
    expect($product->replacement_product_id)->toBe($replacement->id);

    // P3-S5 이후: 사유는 audit log properties.reason 에 저장됨
    $disc = Activity::query()
        ->where('subject_type', Product::class)
        ->where('subject_id', $product->id)
        ->where('event', 'discontinue')
        ->latest('id')
        ->first();
    expect($disc)->not->toBeNull();
    expect($disc->properties->get('reason'))->toBe('제조사 단종 통보');
});

test('단종 처리 시 자기 자신을 대체품으로 지정하면 검증 실패 (422)', function () {
    $product = Product::factory()->create(['status' => Product::STATUS_ACTIVE]);

    $this->actingAs($this->admin)
        ->post(route('products.discontinue', $product), [
            'replacement_product_id' => $product->id,
        ])
        ->assertSessionHasErrors('replacement_product_id');

    expect($product->fresh()->status)->toBe(Product::STATUS_ACTIVE);
});

test('drug_type 이 narcotic 이면 nims_managed 가 자동으로 true 가 된다', function () {
    $product = Product::factory()->create(['drug_type' => Product::DRUG_TYPE_NARCOTIC]);

    expect($product->fresh()->nims_managed)->toBeTrue();
});

test('NIMS 관리 제품의 핵심 컬럼 변경 시 change_reason 이 필수이다', function () {
    $product = Product::factory()->create([
        'drug_type' => Product::DRUG_TYPE_NARCOTIC,
        'product_name' => '원래 이름',
        'manufacturer' => '한미약품',
    ]);

    // 사유 없이 핵심 컬럼 변경 시도 → 422
    $this->actingAs($this->admin)
        ->put(route('products.update', $product), [
            'insurance_code' => $product->insurance_code,
            'product_code' => $product->product_code,
            'product_name' => '바뀐 이름',
            'manufacturer' => $product->manufacturer,
        ])
        ->assertSessionHasErrors('change_reason');

    expect($product->fresh()->product_name)->toBe('원래 이름');

    // 사유 포함 → 정상
    $this->actingAs($this->admin)
        ->put(route('products.update', $product), [
            'insurance_code' => $product->insurance_code,
            'product_code' => $product->product_code,
            'product_name' => '바뀐 이름',
            'manufacturer' => $product->manufacturer,
            'change_reason' => 'NIMS 등록명 변경 반영',
        ])
        ->assertRedirect();

    expect($product->fresh()->product_name)->toBe('바뀐 이름');
});
