<?php

use App\Models\Hospital;
use App\Models\HospitalEquipment;
use App\Models\HospitalHour;
use App\Models\HospitalPublicDataImport;
use App\Models\HospitalSpecialty;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** 실제 xlsx 를 만든 뒤 업로드용 UploadedFile 로 감싼다 (확장자 .xlsx 유지). */
function uploaded_xlsx(array $headers, array $rows, string $name): UploadedFile
{
    $path = write_xlsx($headers, $rows);

    return new UploadedFile($path, $name, null, null, true);
}

test('platform 은 병원정보(B-1) 업로드로 ykiho 를 부착한다 (큐 sync)', function () {
    Storage::fake('local');
    $platform = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $h = Hospital::factory()->create(['hospital_name' => '봄안과의원', 'postcode' => '41151', 'ykiho' => null]);

    $file = uploaded_xlsx(
        ['암호화요양기호', '요양기관명', '종별코드', '시도코드', '시군구코드', '읍면동', '우편번호', '병원홈페이지', '좌표(X)', '좌표(Y)'],
        [['YK-1', '(VOM)봄안과의원', '31', '230000', '230002', '율하동', '41151', 'http://bom.test', '128.69', '35.86']],
        'institution.xlsx',
    );

    $this->actingAs($platform)
        ->post(route('platform.hospitals.public-data.store'), ['kind' => 'institution', 'file' => $file])
        ->assertRedirect(route('platform.hospitals.public-data.index'));

    $import = HospitalPublicDataImport::first();
    expect($import->kind)->toBe('institution');
    expect($import->status)->toBe('completed');
    expect($import->report['matched'])->toBe(1);

    expect($h->fresh()->ykiho)->toBe('YK-1');
    expect($h->fresh()->clazz_code)->toBe('31');
});

test('platform 은 상세(진료과목) 업로드로 정규화 적재한다', function () {
    Storage::fake('local');
    $platform = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $h = Hospital::factory()->create(['ykiho' => 'YK-1']);

    $file = uploaded_xlsx(
        ['암호화요양기호', '요양기관명', '진료과목코드', '진료과목코드명', '과목별 전문의수', '선택진료 의사수'],
        [['YK-1', '병원', '01', '내과', '2', '0'], ['YK-1', '병원', '12', '외과', '1', '0']],
        'specialties.xlsx',
    );

    $this->actingAs($platform)
        ->post(route('platform.hospitals.public-data.store'), ['kind' => 'specialties', 'file' => $file])
        ->assertRedirect();

    expect(HospitalPublicDataImport::first()->status)->toBe('completed');
    expect($h->specialties()->count())->toBe(2);
});

test('업로드 파일이 처리 후 삭제된다', function () {
    Storage::fake('local');
    $platform = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    Hospital::factory()->create(['hospital_name' => '봄안과의원', 'postcode' => '41151']);

    $file = uploaded_xlsx(
        ['암호화요양기호', '요양기관명', '우편번호'],
        [['YK-1', '봄안과의원', '41151']],
        'institution.xlsx',
    );

    $this->actingAs($platform)
        ->post(route('platform.hospitals.public-data.store'), ['kind' => 'institution', 'file' => $file]);

    $import = HospitalPublicDataImport::first();
    expect(Storage::disk('local')->exists($import->stored_path))->toBeFalse();
});

test('잘못된 적재 유형은 거부된다', function () {
    Storage::fake('local');
    $platform = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);

    $file = uploaded_xlsx(['암호화요양기호'], [['YK-1']], 'x.xlsx');

    $this->actingAs($platform)
        ->post(route('platform.hospitals.public-data.store'), ['kind' => 'unknown_kind', 'file' => $file])
        ->assertSessionHasErrors('kind');
});

test('xlsx 가 아닌 파일은 거부된다', function () {
    Storage::fake('local');
    $platform = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);

    $this->actingAs($platform)
        ->post(route('platform.hospitals.public-data.store'), [
            'kind' => 'institution',
            'file' => UploadedFile::fake()->create('data.csv', 10),
        ])
        ->assertSessionHasErrors('file');
});

test('병의원 상세 화면에 보강 정보(진료과목·장비·진료시간)가 포함된다', function () {
    $platform = User::factory()->create(['role' => 'platform', 'tenant_id' => null]);
    $h = Hospital::factory()->create([
        'ykiho' => 'YK-1', 'doctor_count' => 5, 'bed_count' => 40, 'homepage' => 'http://x.test',
    ]);
    HospitalSpecialty::create(['hospital_id' => $h->id, 'dept_code' => '01', 'dept_name' => '내과', 'specialist_count' => 2]);
    HospitalEquipment::create(['hospital_id' => $h->id, 'equipment_code' => 'B302', 'equipment_name' => '초음파', 'quantity' => 1]);
    HospitalHour::create(['hospital_id' => $h->id, 'parking_capacity' => 30, 'treatment_hours' => ['mon' => ['start' => '0900', 'end' => '1800']]]);

    $this->actingAs($platform)
        ->get(route('platform.hospitals.show', $h))
        ->assertInertia(fn ($page) => $page
            ->component('Platform/Hospitals/Show')
            ->where('hospital.ykiho', 'YK-1')
            ->where('hospital.doctor_count', 5)
            ->has('hospital.specialties', 1)
            ->has('hospital.equipments', 1)
            ->where('hospital.hours.parking_capacity', 30)
        );
});

test('pharma 는 보강 업로드에 접근할 수 없다', function () {
    $pharma = User::factory()->create(['role' => 'pharma']);

    $this->actingAs($pharma)->get(route('platform.hospitals.public-data.index'))->assertForbidden();

    $file = uploaded_xlsx(['암호화요양기호'], [['YK-1']], 'x.xlsx');
    $this->actingAs($pharma)
        ->post(route('platform.hospitals.public-data.store'), ['kind' => 'institution', 'file' => $file])
        ->assertForbidden();
});
