<?php

use App\Models\Performance;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->sales = User::factory()->create(['role' => 'sales']);
});

test('실적 소유자는 draft 실적에 증빙 파일을 첨부할 수 있다', function () {
    Storage::fake('local');

    $perf = Performance::factory()->create([
        'status' => Performance::STATUS_DRAFT,
        'created_by' => $this->sales->id,
        'updated_by' => $this->sales->id,
    ]);

    $this->actingAs($this->sales)
        ->post(route('performance.files.store', $perf), [
            'file' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();

    expect($perf->fresh()->files()->count())->toBe(1);
    Storage::disk('local')->assertExists($perf->fresh()->files()->first()->path);
});

test('admin은 어떤 실적에도 증빙 파일을 첨부할 수 있다', function () {
    Storage::fake('local');

    $perf = Performance::factory()->submitted()->create();

    $this->actingAs($this->admin)
        ->post(route('performance.files.store', $perf), [
            'file' => UploadedFile::fake()->create('doc.jpg', 50, 'image/jpeg'),
        ])
        ->assertRedirect();

    expect($perf->fresh()->files()->count())->toBe(1);
});

test('타인의 실적에 파일을 첨부할 수 없다', function () {
    Storage::fake('local');

    $other = User::factory()->create(['role' => 'sales']);
    $perf = Performance::factory()->create([
        'status' => Performance::STATUS_DRAFT,
        'created_by' => $other->id,
        'updated_by' => $other->id,
    ]);

    $this->actingAs($this->sales)
        ->post(route('performance.files.store', $perf), [
            'file' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
        ])
        ->assertForbidden();
});

test('증빙 파일은 최대 5개까지 첨부 가능하다', function () {
    Storage::fake('local');

    $perf = Performance::factory()->create([
        'status' => Performance::STATUS_DRAFT,
        'created_by' => $this->sales->id,
        'updated_by' => $this->sales->id,
    ]);

    for ($i = 0; $i < 5; $i++) {
        $perf->files()->create([
            'original_name' => "file{$i}.pdf",
            'stored_name' => "stored{$i}.pdf",
            'path' => "performances/{$perf->id}/stored{$i}.pdf",
            'size' => 100,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'uploaded_by' => $this->sales->id,
        ]);
    }

    $this->actingAs($this->sales)
        ->post(route('performance.files.store', $perf), [
            'file' => UploadedFile::fake()->create('extra.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('file');

    expect($perf->fresh()->files()->count())->toBe(5);
});

test('인증된 사용자는 자신이 볼 수 있는 실적의 파일을 다운로드할 수 있다', function () {
    Storage::fake('local');

    $perf = Performance::factory()->create([
        'status' => Performance::STATUS_DRAFT,
        'created_by' => $this->sales->id,
        'updated_by' => $this->sales->id,
    ]);

    $file = $perf->files()->create([
        'original_name' => 'receipt.pdf',
        'stored_name' => 'stored.pdf',
        'path' => "performances/{$perf->id}/stored.pdf",
        'size' => 100,
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'uploaded_by' => $this->sales->id,
    ]);
    Storage::disk('local')->put($file->path, 'dummy');

    $this->actingAs($this->sales)
        ->get(route('performance.files.download', [$perf->id, $file->id]))
        ->assertOk();
});

test('실적 소유자는 증빙 파일을 삭제할 수 있다', function () {
    Storage::fake('local');

    $perf = Performance::factory()->create([
        'status' => Performance::STATUS_DRAFT,
        'created_by' => $this->sales->id,
        'updated_by' => $this->sales->id,
    ]);

    $file = $perf->files()->create([
        'original_name' => 'receipt.pdf',
        'stored_name' => 'stored.pdf',
        'path' => "performances/{$perf->id}/stored.pdf",
        'size' => 100,
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'uploaded_by' => $this->sales->id,
    ]);

    $this->actingAs($this->sales)
        ->delete(route('performance.files.destroy', [$perf->id, $file->id]))
        ->assertRedirect();

    expect($perf->fresh()->files()->count())->toBe(0);
});
