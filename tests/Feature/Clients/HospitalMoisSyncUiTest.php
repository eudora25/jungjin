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
            ->where('services.0.last_synced_at', '20260604043000')
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

test('pharma·cso 는 MOIS 동기화 화면에 접근할 수 없다', function () {
    $tenant = Tenant::factory()->create();
    $pharma = User::factory()->create(['role' => 'pharma', 'tenant_id' => $tenant->id]);
    $cso = User::factory()->create(['role' => 'cso', 'tenant_id' => $tenant->id]);

    $this->actingAs($pharma)->get(route('platform.hospitals.mois-sync.index'))->assertForbidden();
    $this->actingAs($cso)->get(route('platform.hospitals.mois-sync.index'))->assertForbidden();
    $this->actingAs($pharma)->post(route('platform.hospitals.mois-sync.store'), ['services' => ['clinics']])->assertForbidden();
});
