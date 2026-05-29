<?php

use App\Models\Product;
use App\Models\ProductFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'pharma']);
    $this->sales = User::factory()->create(['role' => 'cso']);
    $this->product = Product::factory()->create();
});

test('admin은 제품 첨부 파일을 업로드할 수 있다', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post(route('products.files.store', $this->product), [
            'file_type' => ProductFile::TYPE_LICENSE,
            'file' => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();

    $file = ProductFile::sole();
    expect($file->product_id)->toBe($this->product->id);
    expect($file->file_type)->toBe(ProductFile::TYPE_LICENSE);
    expect($file->original_name)->toBe('license.pdf');
    expect($file->uploaded_by)->toBe($this->admin->id);
    Storage::disk('public')->assertExists($file->path);
});

test('sales 는 첨부 파일 업로드 불가 (403)', function () {
    Storage::fake('public');

    $this->actingAs($this->sales)
        ->post(route('products.files.store', $this->product), [
            'file_type' => ProductFile::TYPE_LICENSE,
            'file' => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'),
        ])
        ->assertForbidden();

    expect(ProductFile::count())->toBe(0);
});

test('파일 업로드 시 file_type 이 화이트리스트 외값이면 검증 실패 (422)', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post(route('products.files.store', $this->product), [
            'file_type' => 'invalid_type',
            'file' => UploadedFile::fake()->create('license.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('file_type');
});

test('파일 업로드 시 10MB 초과는 거부된다 (422)', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post(route('products.files.store', $this->product), [
            'file_type' => ProductFile::TYPE_LICENSE,
            'file' => UploadedFile::fake()->create('big.pdf', 11 * 1024, 'application/pdf'),
        ])
        ->assertSessionHasErrors('file');
});

test('admin은 첨부 파일을 삭제할 수 있고 실제 파일도 제거된다', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post(route('products.files.store', $this->product), [
            'file_type' => ProductFile::TYPE_LICENSE,
            'file' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
        ]);

    $file = ProductFile::sole();
    Storage::disk('public')->assertExists($file->path);

    $this->actingAs($this->admin)
        ->delete(route('products.files.destroy', ['product' => $this->product, 'file' => $file]))
        ->assertRedirect();

    expect(ProductFile::count())->toBe(0);
    Storage::disk('public')->assertMissing($file->path);
});

test('sales 는 첨부 파일을 삭제할 수 없다 (403)', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post(route('products.files.store', $this->product), [
            'file_type' => ProductFile::TYPE_LICENSE,
            'file' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
        ]);

    $file = ProductFile::sole();

    $this->actingAs($this->sales)
        ->delete(route('products.files.destroy', ['product' => $this->product, 'file' => $file]))
        ->assertForbidden();

    expect(ProductFile::count())->toBe(1);
});

test('다른 제품의 파일을 삭제하려고 하면 404', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post(route('products.files.store', $this->product), [
            'file_type' => ProductFile::TYPE_LICENSE,
            'file' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
        ]);

    $file = ProductFile::sole();
    $other = Product::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('products.files.destroy', ['product' => $other, 'file' => $file]))
        ->assertNotFound();
});

test('인증된 사용자(sales 포함)는 파일을 다운로드할 수 있다', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post(route('products.files.store', $this->product), [
            'file_type' => ProductFile::TYPE_LICENSE,
            'file' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
        ]);

    $file = ProductFile::sole();

    $this->actingAs($this->sales)
        ->get(route('products.files.download', ['product' => $this->product, 'file' => $file]))
        ->assertOk();
});
