<?php

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function cp949_h(string $utf8): string
{
    $out = iconv('UTF-8', 'CP949//IGNORE', $utf8);
    if ($out === false) {
        throw new RuntimeException('iconv failed');
    }

    return $out;
}

test('관리자는 병의원 CSV를 분석하고 적용할 수 있다 (CP949) - 유형 매핑 포함', function () {
    Storage::fake('local');

    $admin = User::factory()->create(['role' => 'platform']);

    $utf8 = implode("\n", [
        '관리번호,사업장명,영업상태명,의료기관종별명,진료과목내용명,도로명우편번호,도로명주소,전화번호',
        'H-001,테스트종합병원,영업/정상,종합병원,"내과, 신경과",03100,서울 종로구 어딘가 1,0211112222',
        'H-002,테스트치과의원,영업/정상,치과의원,"치과",03100,서울 종로구 어딘가 2,0211113333',
        '',
    ]);

    $file = UploadedFile::fake()->createWithContent('hospitals.csv', cp949_h($utf8));

    $res = $this->actingAs($admin)->post(route('hospitals.import.handle'), [
        'mode' => 'analyze',
        'file' => $file,
    ]);

    $res->assertOk();
    $res->assertInertia(fn ($page) => $page
        ->component('Clients/Hospitals/Import')
        ->has('analysis.token')
        ->where('analysis.summary.create', 2)
        ->where('analysis.summary.error', 0)
    );

    $token = $res->inertiaPage()['props']['analysis']['token'];

    $commit = $this->actingAs($admin)->post(route('hospitals.import.handle'), [
        'mode' => 'commit',
        'token' => $token,
    ]);
    $commit->assertRedirect(route('hospitals.index'));

    $a = Hospital::where('hospital_code', 'H-001')->first();
    expect($a)->not->toBeNull();
    expect($a->hospital_type)->toBe('general_hospital');
    expect($a->specialty)->toBe('내과');

    $b = Hospital::where('hospital_code', 'H-002')->first();
    expect($b)->not->toBeNull();
    expect($b->hospital_type)->toBe('dental');
});

test('인허가 CSV의 일자·병상수·의료인수·면적 컬럼을 흡수한다', function () {
    Storage::fake('local');

    $admin = User::factory()->create(['role' => 'platform']);

    $utf8 = implode("\n", [
        '개방자치단체코드,관리번호,사업장명,영업상태명,의료기관종별명,진료과목내용명,도로명우편번호,도로명주소,전화번호,인허가일자,폐업일자,휴업시작일자,휴업종료일자,병상수,의료인수,입원실수,총면적,데이터갱신시점',
        '3000000,H-100,큰병원,영업/정상,종합병원,"내과, 외과",03100,서울 종로구 1,0211110000,1990-03-15,,,,120,45,30,8500.50,2026-04-01 22:30:12',
        '',
    ]);

    $file = UploadedFile::fake()->createWithContent('hospitals.csv', cp949_h($utf8));

    $analyze = $this->actingAs($admin)->post(route('hospitals.import.handle'), [
        'mode' => 'analyze',
        'file' => $file,
    ]);
    $token = $analyze->inertiaPage()['props']['analysis']['token'];

    $this->actingAs($admin)->post(route('hospitals.import.handle'), [
        'mode' => 'commit',
        'token' => $token,
    ])->assertRedirect(route('hospitals.index'));

    $h = Hospital::where('hospital_code', 'H-100')->first();
    expect($h)->not->toBeNull();
    expect($h->opened_on?->format('Y-m-d'))->toBe('1990-03-15');
    expect($h->bed_count)->toBe(120);
    expect($h->doctor_count)->toBe(45);
    expect($h->inpatient_room_count)->toBe(30);
    expect((float) $h->total_area)->toBe(8500.50);
    expect($h->license_authority_code)->toBe('3000000');
    expect($h->source_synced_at?->format('Y-m-d H:i:s'))->toBe('2026-04-01 22:30:12');
});
