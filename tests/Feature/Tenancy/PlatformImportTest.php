<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * GAP-10 마스터 CRUD — super_admin 의 공유 마스터 CSV 일괄 등록(/platform). (공공데이터 LOCALDATA 포맷)
 */
function superImporter(): User
{
    return User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
}

/** 공공데이터(약국) 최소 CSV — EUC-KR(CP949) 인코딩 */
function pharmacyCsv(): string
{
    $utf8 = "관리번호,사업장명,영업상태명,도로명주소,도로명우편번호\n"
        ."PH-0001,테스트약국,영업/정상,서울시 강남구 1,06000\n"
        ."PH-0002,둘째약국,폐업,서울시 송파구 2,05500\n";

    return mb_convert_encoding($utf8, 'CP949', 'UTF-8');
}

test('super_admin 은 플랫폼 약국 import 폼에 접근한다', function () {
    $this->actingAs(superImporter())
        ->get(route('platform.pharmacies.import.form'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Clients/Pharmacies/Import')
            ->where('handleRoute', 'platform.pharmacies.import.handle'));
});

test('super_admin 은 공공데이터 CSV 를 분석(dry-run)한다', function () {
    $file = UploadedFile::fake()->createWithContent('약국.csv', pharmacyCsv());

    $this->actingAs(superImporter())
        ->post(route('platform.pharmacies.import.handle'), ['mode' => 'analyze', 'file' => $file])
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Clients/Pharmacies/Import')
            ->where('analysis.row_count', 2)
            ->where('analysis.summary.create', 2));

    // dry-run 은 DB 에 반영되지 않음
    $this->assertDatabaseMissing('pharmacies', ['pharmacy_name' => '테스트약국']);
});

test('super_admin 은 분석 후 확정 적용하면 약국이 생성된다', function () {
    $super = superImporter();
    $file = UploadedFile::fake()->createWithContent('약국.csv', pharmacyCsv());

    // 1) analyze → 토큰 확보
    $analyze = $this->actingAs($super)
        ->post(route('platform.pharmacies.import.handle'), ['mode' => 'analyze', 'file' => $file]);
    $token = $analyze->viewData('page')['props']['analysis']['token'];

    // 2) commit
    $this->actingAs($super)
        ->post(route('platform.pharmacies.import.handle'), ['mode' => 'commit', 'token' => $token])
        ->assertRedirect(route('platform.pharmacies.index'));

    $this->assertDatabaseHas('pharmacies', ['pharmacy_code' => 'PH-0001', 'pharmacy_name' => '테스트약국', 'status' => 'active']);
    $this->assertDatabaseHas('pharmacies', ['pharmacy_code' => 'PH-0002', 'status' => 'inactive']);
});

test('admin 은 플랫폼 import 에 접근할 수 없다', function () {
    $admin = User::factory()->create(['role' => 'pharma']);

    $this->actingAs($admin)->get(route('platform.pharmacies.import.form'))->assertForbidden();
    $this->actingAs($admin)->get(route('platform.hospitals.import.form'))->assertForbidden();
});
