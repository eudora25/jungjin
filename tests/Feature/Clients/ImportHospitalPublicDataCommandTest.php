<?php

use App\Models\Hospital;

/**
 * 임시 샘플 디렉터리 구성 — CSV(A) + 심평원 하위폴더(B-1, 진료과목) 일부만.
 */
function fake_samples_dir(): string
{
    $base = sys_get_temp_dir().'/samples_'.bin2hex(random_bytes(4));
    $hira = $base.'/전국 병의원 및 약국 현황 2026.3 2';
    mkdir($hira, 0777, true);

    // A) 인허가 CSV (CP949)
    $utf8 = implode("\n", [
        '관리번호,사업장명,영업상태명,의료기관종별명,진료과목내용명,도로명우편번호,도로명주소,전화번호',
        'C-1,봄안과의원,영업/정상,의원,"안과",41151,대구 동구 안심로 58,0539639991',
        '',
    ]);
    file_put_contents($base.'/건강_병원.csv', iconv('UTF-8', 'CP949//IGNORE', $utf8));

    // B-1) 병원정보
    $inst = write_xlsx(
        ['암호화요양기호', '요양기관명', '종별코드', '시도코드', '시군구코드', '읍면동', '우편번호', '병원홈페이지', '좌표(X)', '좌표(Y)'],
        [['YK-1', '(VOM)봄안과의원', '31', '230000', '230002', '율하동', '41151', 'http://bom.test', '128.69', '35.86']],
    );
    rename($inst, $hira.'/1.병원정보서비스(2026.3.).xlsx');

    // B-5) 진료과목
    $spec = write_xlsx(
        ['암호화요양기호', '요양기관명', '진료과목코드', '진료과목코드명', '과목별 전문의수', '선택진료 의사수'],
        [['YK-1', '봄안과의원', '12', '안과', '3', '0']],
    );
    rename($spec, $hira.'/5.의료기관별상세정보서비스_03_진료과목정보(2026.3.).xlsx');

    return $base;
}

test('hospitals:import-public-data 가 A→B-1→상세를 순차 적재한다', function () {
    $dir = fake_samples_dir();

    $this->artisan('hospitals:import-public-data', ['--dir' => $dir])
        ->assertSuccessful();

    $h = Hospital::where('hospital_code', 'C-1')->first();
    expect($h)->not->toBeNull();
    // B-1 매칭으로 ykiho·보강 컬럼 부착
    expect($h->ykiho)->toBe('YK-1');
    expect($h->clazz_code)->toBe('31');
    expect($h->homepage)->toBe('http://bom.test');
    // B-5 진료과목 연결
    expect($h->specialties()->where('dept_code', '12')->value('specialist_count'))->toBe(3);
});

test('--step 옵션으로 단계만 실행한다 (b1 단독은 매칭 대상 없으면 무변경)', function () {
    $dir = fake_samples_dir();

    // a 단계 없이 b1 만 → CSV 미적재로 매칭 대상 없음
    $this->artisan('hospitals:import-public-data', ['--dir' => $dir, '--step' => 'b1'])
        ->assertSuccessful();

    expect(Hospital::count())->toBe(0);
});
