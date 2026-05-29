<?php

use App\Models\Product;
use App\Models\ProductCommissionRate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->sales = User::factory()->create(['role' => 'sales']);
});

test('any authenticated user can list products', function () {
    Product::factory()->count(3)->create();

    $this->actingAs($this->sales)
        ->get('/products')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Products/Index')
            ->has('products.data', 3)
        );
});

test('admin can create a product with image', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post('/products', [
            'insurance_code' => '653801234',
            'product_code' => 'PRD-001',
            'product_name' => '테스트 제품',
            'manufacturer' => '신일제약',
            'category' => '비타민',
            'price' => 1500.50,
            'status' => 'active',
            'image' => UploadedFile::fake()->image('test.jpg', 200, 200),
        ])
        ->assertRedirect();

    $product = Product::sole();
    expect($product->product_name)->toBe('테스트 제품');
    expect($product->created_by)->toBe($this->admin->id);
    expect($product->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($product->image_path);
});

test('sales cannot create a product', function () {
    $this->actingAs($this->sales)
        ->post('/products', ['insurance_code' => 'X', 'product_code' => 'X', 'product_name' => 'X'])
        ->assertForbidden();

    expect(Product::count())->toBe(0);
});

test('admin can soft-delete a product', function () {
    $product = Product::factory()->create();

    $this->actingAs($this->admin)
        ->delete("/products/{$product->id}")
        ->assertRedirect('/products');

    expect(Product::count())->toBe(0);
    expect(Product::withTrashed()->count())->toBe(1);
});

test('list filters by category and status', function () {
    Product::factory()->create(['category' => '비타민', 'status' => 'active']);
    Product::factory()->create(['category' => '비타민', 'status' => 'discontinued']);
    Product::factory()->create(['category' => '진통제', 'status' => 'active']);

    $this->actingAs($this->admin)
        ->get('/products?category=비타민')
        ->assertInertia(fn ($page) => $page->has('products.data', 2));

    $this->actingAs($this->admin)
        ->get('/products?status=discontinued')
        ->assertInertia(fn ($page) => $page->has('products.data', 1));
});

test('admin can add commission rate to a product', function () {
    $product = Product::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('products.commission-rates.store', $product), [
            'base_month' => '2026-04',
            'commission_rate_a' => 12.5,
            'commission_rate_b' => 10,
            'commission_rate_c' => 8,
            'commission_rate_d' => 6,
            'commission_rate_e' => 4,
            'effective_from' => '2026-04-01',
            'effective_to' => null,
            'status' => 'active',
        ])
        ->assertRedirect();

    expect($product->commissionRates()->count())->toBe(1);
    $rate = $product->commissionRates->first();
    expect((float) $rate->commission_rate_a)->toBe(12.5);
    expect($rate->base_month)->toBe('2026-04');
});

test('sales cannot add commission rate', function () {
    $product = Product::factory()->create();

    $this->actingAs($this->sales)
        ->post(route('products.commission-rates.store', $product), [
            'base_month' => '2026-04',
            'commission_rate_a' => 1,
            'commission_rate_b' => 1,
            'commission_rate_c' => 1,
            'commission_rate_d' => 1,
            'commission_rate_e' => 1,
            'effective_from' => '2026-04-01',
        ])
        ->assertForbidden();

    expect($product->commissionRates()->count())->toBe(0);
});

test('admin can delete commission rate', function () {
    $product = Product::factory()->create();
    $rate = ProductCommissionRate::create([
        'product_id' => $product->id,
        'base_month' => '2026-04',
        'commission_rate_a' => 10,
        'commission_rate_b' => 8,
        'commission_rate_c' => 6,
        'commission_rate_d' => 4,
        'commission_rate_e' => 2,
        'effective_from' => '2026-04-01',
        'status' => 'active',
    ]);

    $this->actingAs($this->admin)
        ->delete(route('products.commission-rates.destroy', ['product' => $product, 'rate' => $rate]))
        ->assertRedirect();

    expect($product->commissionRates()->count())->toBe(0);
});

test('cannot delete a commission rate from a different product', function () {
    $productA = Product::factory()->create();
    $productB = Product::factory()->create();
    $rate = ProductCommissionRate::create([
        'product_id' => $productA->id,
        'base_month' => '2026-04',
        'commission_rate_a' => 10,
        'commission_rate_b' => 8,
        'commission_rate_c' => 6,
        'commission_rate_d' => 4,
        'commission_rate_e' => 2,
        'effective_from' => '2026-04-01',
        'status' => 'active',
    ]);

    $this->actingAs($this->admin)
        ->delete(route('products.commission-rates.destroy', ['product' => $productB, 'rate' => $rate]))
        ->assertNotFound();
});
