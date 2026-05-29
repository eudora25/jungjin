<?php

use App\Models\Notice;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->sales = User::factory()->create(['role' => 'sales']);
});

test('any authenticated user can list notices', function () {
    Notice::factory()->count(3)->create();

    $this->actingAs($this->sales)
        ->get('/notices')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Notices/Index')
            ->has('notices.data', 3)
        );
});

test('admin can create a notice', function () {
    Storage::fake('local');

    $this->actingAs($this->admin)
        ->post('/notices', [
            'title' => '테스트 공지',
            'content' => '본문 내용입니다.',
            'is_pinned' => true,
            'attachments' => [UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')],
        ])
        ->assertRedirect();

    $notice = Notice::sole();
    expect($notice->title)->toBe('테스트 공지');
    expect($notice->is_pinned)->toBeTrue();
    expect($notice->created_by)->toBe($this->admin->id);
    expect($notice->files)->toHaveCount(1);
    Storage::disk('local')->assertExists($notice->files->first()->path);
});

test('sales cannot create a notice', function () {
    $this->actingAs($this->sales)
        ->post('/notices', ['title' => '막힐 공지'])
        ->assertForbidden();

    expect(Notice::count())->toBe(0);
});

test('viewing a notice increments view_count and records a read', function () {
    $notice = Notice::factory()->create(['view_count' => 0]);

    $this->actingAs($this->sales)->get("/notices/{$notice->id}")->assertOk();

    expect($notice->fresh()->view_count)->toBe(1);
    expect($notice->readers()->where('users.id', $this->sales->id)->exists())->toBeTrue();
});

test('admin can update a notice and remove attachments', function () {
    Storage::fake('local');

    $notice = Notice::factory()->create();
    $notice->files()->create([
        'original_name' => 'old.pdf',
        'stored_name' => 'old_stored.pdf',
        'path' => 'notices/'.$notice->id.'/old_stored.pdf',
        'size' => 100,
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'uploaded_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)
        ->put("/notices/{$notice->id}", [
            'title' => '수정된 제목',
            'content' => '수정 본문',
            'is_pinned' => false,
            'removed_file_ids' => [$notice->files->first()->id],
        ])
        ->assertRedirect();

    expect($notice->fresh()->title)->toBe('수정된 제목');
    expect($notice->fresh()->files()->count())->toBe(0);
});

test('admin can soft-delete a notice', function () {
    $notice = Notice::factory()->create();

    $this->actingAs($this->admin)
        ->delete("/notices/{$notice->id}")
        ->assertRedirect('/notices');

    expect(Notice::count())->toBe(0);
    expect(Notice::withTrashed()->count())->toBe(1);
});

test('sales cannot delete a notice', function () {
    $notice = Notice::factory()->create();

    $this->actingAs($this->sales)
        ->delete("/notices/{$notice->id}")
        ->assertForbidden();

    expect(Notice::count())->toBe(1);
});
