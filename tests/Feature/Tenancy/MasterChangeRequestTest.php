<?php

use App\Models\MasterChangeRequest;
use App\Models\Pharmacy;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->platform = User::factory()->create(['role' => 'platform']);
    $this->pharma = User::factory()->create(['role' => 'pharma', 'tenant_id' => $this->tenant->id]);
    $this->cso = User::factory()->create(['role' => 'cso', 'tenant_id' => $this->tenant->id]);
});

test('pharma 가 약국 신규 변경요청을 제출한다', function () {
    $this->actingAs($this->pharma)
        ->post(route('master-change-requests.store'), [
            'target_type' => 'pharmacy',
            'request_type' => 'create',
            'payload' => ['pharmacy_name' => '신규요청약국'],
        ])
        ->assertRedirect();

    $req = MasterChangeRequest::first();
    expect($req)->not->toBeNull()
        ->and($req->status)->toBe('pending')
        ->and($req->requested_by)->toBe($this->pharma->id)
        ->and($req->tenant_id)->toBe($this->tenant->id);

    // 승인 전에는 실제 마스터에 반영되지 않는다
    expect(Pharmacy::where('pharmacy_name', '신규요청약국')->exists())->toBeFalse();
});

test('cso 는 변경요청을 제출할 수 없다', function () {
    $this->actingAs($this->cso)
        ->post(route('master-change-requests.store'), [
            'target_type' => 'pharmacy',
            'request_type' => 'create',
            'payload' => ['pharmacy_name' => '권한없음'],
        ])
        ->assertForbidden();
});

test('약국명 없는 변경요청은 거부된다', function () {
    $this->actingAs($this->pharma)
        ->post(route('master-change-requests.store'), [
            'target_type' => 'pharmacy',
            'request_type' => 'create',
            'payload' => [],
        ])
        ->assertSessionHasErrors('payload.pharmacy_name');
});

test('platform 이 신규 요청을 승인하면 약국이 생성된다', function () {
    $req = MasterChangeRequest::create([
        'tenant_id' => $this->tenant->id,
        'requested_by' => $this->pharma->id,
        'target_type' => 'pharmacy',
        'request_type' => 'create',
        'payload' => ['pharmacy_name' => '승인될약국', 'status' => 'active'],
        'status' => 'pending',
    ]);

    $this->actingAs($this->platform)
        ->post(route('platform.master-requests.approve', $req))
        ->assertRedirect();

    $pharmacy = Pharmacy::where('pharmacy_name', '승인될약국')->first();
    expect($pharmacy)->not->toBeNull();

    $req->refresh();
    expect($req->status)->toBe('approved')
        ->and($req->reviewed_by)->toBe($this->platform->id)
        ->and($req->applied_target_id)->toBe($pharmacy->id);
});

test('platform 이 수정 요청을 승인하면 약국이 수정된다', function () {
    $pharmacy = Pharmacy::factory()->create(['pharmacy_name' => '수정전약국']);

    $req = MasterChangeRequest::create([
        'tenant_id' => $this->tenant->id,
        'requested_by' => $this->pharma->id,
        'target_type' => 'pharmacy',
        'request_type' => 'update',
        'target_id' => $pharmacy->id,
        'payload' => ['pharmacy_name' => '수정후약국'],
        'status' => 'pending',
    ]);

    $this->actingAs($this->platform)
        ->post(route('platform.master-requests.approve', $req))
        ->assertRedirect();

    expect($pharmacy->fresh()->pharmacy_name)->toBe('수정후약국');
    expect($req->fresh()->applied_target_id)->toBe($pharmacy->id);
});

test('platform 이 요청을 반려한다', function () {
    $req = MasterChangeRequest::create([
        'tenant_id' => $this->tenant->id,
        'requested_by' => $this->pharma->id,
        'target_type' => 'pharmacy',
        'request_type' => 'create',
        'payload' => ['pharmacy_name' => '반려될약국'],
        'status' => 'pending',
    ]);

    $this->actingAs($this->platform)
        ->post(route('platform.master-requests.reject', $req), ['review_note' => '정보 부족'])
        ->assertRedirect();

    $req->refresh();
    expect($req->status)->toBe('rejected')
        ->and($req->review_note)->toBe('정보 부족')
        ->and($req->reviewed_by)->toBe($this->platform->id);

    expect(Pharmacy::where('pharmacy_name', '반려될약국')->exists())->toBeFalse();
});

test('pharma 는 변경요청을 승인할 수 없다', function () {
    $req = MasterChangeRequest::create([
        'tenant_id' => $this->tenant->id,
        'requested_by' => $this->pharma->id,
        'target_type' => 'pharmacy',
        'request_type' => 'create',
        'payload' => ['pharmacy_name' => '무단승인'],
        'status' => 'pending',
    ]);

    $this->actingAs($this->pharma)
        ->post(route('platform.master-requests.approve', $req))
        ->assertForbidden();

    expect($req->fresh()->status)->toBe('pending');
});
