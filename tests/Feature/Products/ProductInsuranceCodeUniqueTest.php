<?php

use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

test('동일한 insurance_code 로는 두 번째 제품을 등록할 수 없다 (422)', function () {
    Product::factory()->create(['insurance_code' => '653801234']);

    $this->actingAs($this->admin)
        ->post(route('products.store'), [
            'insurance_code' => '653801234',
            'product_code' => 'PRD-NEW',
            'product_name' => '중복 코드 시도',
        ])
        ->assertSessionHasErrors('insurance_code');

    expect(Product::count())->toBe(1);
});

test('자기 자신의 insurance_code 로 update 하면 unique 검증을 통과한다', function () {
    $product = Product::factory()->create(['insurance_code' => '653801234']);

    $this->actingAs($this->admin)
        ->put(route('products.update', $product), [
            'insurance_code' => '653801234',
            'product_code' => $product->product_code,
            'product_name' => $product->product_name,
        ])
        ->assertRedirect();
});

test('다른 제품의 insurance_code 로 update 하려 하면 unique 검증 실패 (422)', function () {
    Product::factory()->create(['insurance_code' => '111111111']);
    $target = Product::factory()->create(['insurance_code' => '222222222']);

    $this->actingAs($this->admin)
        ->put(route('products.update', $target), [
            'insurance_code' => '111111111',
            'product_code' => $target->product_code,
            'product_name' => $target->product_name,
        ])
        ->assertSessionHasErrors('insurance_code');

    expect($target->fresh()->insurance_code)->toBe('222222222');
});

test('신규 등록 시 approval_status 입력은 무시되고 항상 draft 로 시작한다', function () {
    $this->actingAs($this->admin)
        ->post(route('products.store'), [
            'insurance_code' => '900000001',
            'product_code' => 'PRD-Z',
            'product_name' => '강제 draft 테스트',
            'approval_status' => Product::APPROVAL_APPROVED, // 입력은 무시되어야 함
        ])
        ->assertRedirect();

    $product = Product::sole();
    expect($product->approval_status)->toBe(Product::APPROVAL_DRAFT);
});
