<?php

use App\Jobs\SyncHospitalMoisJob;
use App\Models\HospitalMoisCursor;
use App\Models\HospitalMoisSync;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

function moisPlatformUser(): User
{
    return User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
}

test('platform 은 MOIS 동기화 이력 화면을 조회한다', function () {
    $admin = moisPlatformUser();

    HospitalMoisSync::create([
        'created_by' => $admin->id,
        'trigger' => HospitalMoisSync::TRIGGER_MANUAL,
        'status' => HospitalMoisSync::STATUS_COMPLETED,
        'report' => ['clinics' => ['fetched' => 10, 'inserted' => 3, 'updated' => 2, 'closed' => 1, 'skipped' => 4]],
        'finished_at' => '2026-06-04 04:30:05',
    ]);
    HospitalMoisCursor::create(['api_id' => '15154874', 'last_synced_at' => '20260604043000']);

    $this->actingAs($admin)
        ->get(route('platform.hospitals.mois-sync.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Platform/Hospitals/MoisSync')
            ->has('syncs', 1)
            ->has('services', 3)
            ->where('enabled', false)
            ->where('services.0.key', 'clinics')
            ->where('services.0.last_synced_at', '20260604043000')
            ->where('services.0.last_run_status', 'completed')
            ->whereNot('services.0.last_run_at', null)
            // 이력에 없는 업종(병원)은 마지막 실행 정보가 비어 있다
            ->where('services.1.last_run_status', null)
            ->where('services.1.last_run_at', null)
        );
});

test('platform 이 동기화를 트리거하면 큐 잡이 디스패치되고 리다이렉트된다', function () {
    Bus::fake();
    $admin = moisPlatformUser();

    $this->actingAs($admin)
        ->post(route('platform.hospitals.mois-sync.store'), [
            'services' => ['clinics'],
            'dry_run' => false,
        ])
        ->assertRedirect(route('platform.hospitals.mois-sync.index'))
        ->assertSessionHas('success');

    Bus::assertDispatched(SyncHospitalMoisJob::class, function ($job) use ($admin) {
        return $job->options['trigger'] === HospitalMoisSync::TRIGGER_MANUAL
            && $job->options['user_id'] === $admin->id
            && $job->options['services'] === ['clinics'];
    });
});

test('잘못된 업종 키는 검증 실패한다', function () {
    Bus::fake();
    $admin = moisPlatformUser();

    $this->actingAs($admin)
        ->post(route('platform.hospitals.mois-sync.store'), ['services' => ['unknown_svc']])
        ->assertSessionHasErrors('services.0');

    Bus::assertNotDispatched(SyncHospitalMoisJob::class);
});

test('JSON 요청은 pending 이력행을 만들고 id 를 반환하며 잡에 sync_id 를 넘긴다', function () {
    Bus::fake();
    $admin = moisPlatformUser();

    $response = $this->actingAs($admin)
        ->postJson(route('platform.hospitals.mois-sync.store'), [
            'services' => ['clinics'],
            'dry_run' => false,
        ])
        ->assertOk()
        ->assertJson(['status' => HospitalMoisSync::STATUS_PENDING]);

    $id = $response->json('id');
    expect($id)->toBeInt();

    $sync = HospitalMoisSync::find($id);
    expect($sync)->not->toBeNull();
    expect($sync->status)->toBe(HospitalMoisSync::STATUS_PENDING);
    expect($sync->params['services'])->toBe(['clinics']);

    Bus::assertDispatched(SyncHospitalMoisJob::class, fn ($job) => ($job->options['sync_id'] ?? null) === $id);
});

test('status 엔드포인트는 진행상태를 JSON 으로 반환한다', function () {
    $admin = moisPlatformUser();

    $sync = HospitalMoisSync::create([
        'created_by' => $admin->id,
        'trigger' => HospitalMoisSync::TRIGGER_MANUAL,
        'status' => HospitalMoisSync::STATUS_COMPLETED,
        'report' => ['clinics' => ['inserted' => 2]],
    ]);

    $this->actingAs($admin)
        ->getJson(route('platform.hospitals.mois-sync.status', $sync->id))
        ->assertOk()
        ->assertJson([
            'id' => $sync->id,
            'status' => HospitalMoisSync::STATUS_COMPLETED,
            'report' => ['clinics' => ['inserted' => 2]],
        ]);
});

test('pharma 는 status 엔드포인트에 접근할 수 없다', function () {
    $tenant = Tenant::factory()->create();
    $pharma = User::factory()->create(['role' => 'pharma', 'tenant_id' => $tenant->id]);
    $sync = HospitalMoisSync::create([
        'trigger' => HospitalMoisSync::TRIGGER_SCHEDULE,
        'status' => HospitalMoisSync::STATUS_PROCESSING,
    ]);

    $this->actingAs($pharma)
        ->getJson(route('platform.hospitals.mois-sync.status', $sync->id))
        ->assertForbidden();
});

test('pharma·cso 는 MOIS 동기화 화면에 접근할 수 없다', function () {
    $tenant = Tenant::factory()->create();
    $pharma = User::factory()->create(['role' => 'pharma', 'tenant_id' => $tenant->id]);
    $cso = User::factory()->create(['role' => 'cso', 'tenant_id' => $tenant->id]);

    $this->actingAs($pharma)->get(route('platform.hospitals.mois-sync.index'))->assertForbidden();
    $this->actingAs($cso)->get(route('platform.hospitals.mois-sync.index'))->assertForbidden();
    $this->actingAs($pharma)->post(route('platform.hospitals.mois-sync.store'), ['services' => ['clinics']])->assertForbidden();
});
