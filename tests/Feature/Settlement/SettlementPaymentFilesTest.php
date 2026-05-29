<?php

use App\Models\Company;
use App\Models\Settlement;
use App\Models\SettlementPaymentFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'pharma']);
    $this->sales = User::factory()->create(['role' => 'cso']);
});

function makeConfirmedSettlement(User $admin, ?Company $company = null): Settlement
{
    $company ??= Company::factory()->create();

    test()->actingAs($admin)
        ->post(route('settlements.store'), [
            'company_id' => $company->id,
            'period_month' => '2026-04',
        ]);

    $s = Settlement::where('company_id', $company->id)->firstOrFail();

    test()->actingAs($admin)->post(route('settlements.confirm', $s));

    return $s->fresh();
}

// ── 업로드 권한 ────────────────────────────────────────────────────────────────

test('관리자는 confirmed 정산에 지급 증빙 파일을 업로드할 수 있다', function () {
    Storage::fake('local');

    $settlement = makeConfirmedSettlement($this->admin);

    $this->actingAs($this->admin)
        ->post(route('settlements.payment-files.store', $settlement), [
            'file' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();

    expect($settlement->fresh()->paymentFiles()->count())->toBe(1);
    Storage::disk('local')->assertExists($settlement->fresh()->paymentFiles()->first()->path);
});

test('paid 상태에서도 추가 업로드가 가능하다', function () {
    Storage::fake('local');

    $settlement = makeConfirmedSettlement($this->admin);
    $this->actingAs($this->admin)
        ->post(route('settlements.pay', $settlement), ['paid_on' => now()->toDateString()]);

    $this->actingAs($this->admin)
        ->post(route('settlements.payment-files.store', $settlement), [
            'file' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect();

    expect($settlement->fresh()->paymentFiles()->count())->toBe(1);
});

test('draft 상태에서는 증빙 파일 업로드가 거부된다', function () {
    Storage::fake('local');

    $company = Company::factory()->create();
    $this->actingAs($this->admin)
        ->post(route('settlements.store'), ['company_id' => $company->id, 'period_month' => '2026-04']);
    $draft = Settlement::where('company_id', $company->id)->firstOrFail();

    $this->actingAs($this->admin)
        ->post(route('settlements.payment-files.store', $draft), [
            'file' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
        ])
        ->assertForbidden();
});

test('영업사원은 지급 증빙 파일을 업로드할 수 없다', function () {
    Storage::fake('local');

    $settlement = makeConfirmedSettlement($this->admin);

    $this->actingAs($this->sales)
        ->post(route('settlements.payment-files.store', $settlement), [
            'file' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
        ])
        ->assertForbidden();
});

test('실행 파일은 업로드할 수 없다', function () {
    Storage::fake('local');

    $settlement = makeConfirmedSettlement($this->admin);

    $this->actingAs($this->admin)
        ->post(route('settlements.payment-files.store', $settlement), [
            'file' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
        ])
        ->assertSessionHasErrors('file');
});

// ── 삭제 ──────────────────────────────────────────────────────────────────────

test('관리자는 지급 증빙 파일을 삭제할 수 있고 디스크에서도 제거된다', function () {
    Storage::fake('local');

    $settlement = makeConfirmedSettlement($this->admin);

    $this->actingAs($this->admin)
        ->post(route('settlements.payment-files.store', $settlement), [
            'file' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
        ]);

    $file = $settlement->fresh()->paymentFiles()->first();
    Storage::disk('local')->assertExists($file->path);

    $this->actingAs($this->admin)
        ->delete(route('settlements.payment-files.destroy', [$settlement, $file]))
        ->assertRedirect();

    Storage::disk('local')->assertMissing($file->path);
    expect(SettlementPaymentFile::withTrashed()->find($file->id)?->trashed())->toBeTrue();
});

test('다른 정산의 ID 로 파일 삭제 시도하면 404', function () {
    Storage::fake('local');

    $s1 = makeConfirmedSettlement($this->admin);
    $s2 = makeConfirmedSettlement($this->admin, Company::factory()->create());

    $this->actingAs($this->admin)
        ->post(route('settlements.payment-files.store', $s1), [
            'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        ]);

    $file = $s1->fresh()->paymentFiles()->first();

    $this->actingAs($this->admin)
        ->delete(route('settlements.payment-files.destroy', [$s2, $file]))
        ->assertNotFound();

    expect($s1->fresh()->paymentFiles()->count())->toBe(1);
});

// ── 다운로드 ──────────────────────────────────────────────────────────────────

test('관리자는 증빙 파일을 다운로드할 수 있다', function () {
    Storage::fake('local');

    $settlement = makeConfirmedSettlement($this->admin);

    $this->actingAs($this->admin)
        ->post(route('settlements.payment-files.store', $settlement), [
            'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        ]);

    $file = $settlement->fresh()->paymentFiles()->first();

    $this->actingAs($this->admin)
        ->get(route('settlements.payment-files.download', [$settlement, $file]))
        ->assertOk()
        ->assertHeader('content-disposition');
});

test('정산 조회 권한이 없는 영업사원은 다운로드할 수 없다', function () {
    Storage::fake('local');

    $settlement = makeConfirmedSettlement($this->admin);

    $this->actingAs($this->admin)
        ->post(route('settlements.payment-files.store', $settlement), [
            'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        ]);

    $file = $settlement->fresh()->paymentFiles()->first();

    $this->actingAs($this->sales)
        ->get(route('settlements.payment-files.download', [$settlement, $file]))
        ->assertForbidden();
});
