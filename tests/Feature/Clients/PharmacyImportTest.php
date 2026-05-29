<?php

use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function cp949(string $utf8): string
{
    $out = iconv('UTF-8', 'CP949//IGNORE', $utf8);
    if ($out === false) {
        throw new RuntimeException('iconv failed');
    }

    return $out;
}

test('관리자는 약국 CSV를 분석하고 적용할 수 있다 (CP949)', function () {
    Storage::fake('local');

    $admin = User::factory()->create(['role' => 'admin']);

    $utf8 = implode("\n", [
        '관리번호,사업장명,영업상태명,도로명우편번호,도로명주소,전화번호,지번주소,소재지우편번호',
        'PHMD-001,테스트약국,영업/정상,03100,서울 종로구 어딘가 1,0211112222,,',
        'PHMD-002,폐업약국,폐업,03100,서울 종로구 어딘가 2,0211113333,,',
        '',
    ]);

    $file = UploadedFile::fake()->createWithContent('pharmacies.csv', cp949($utf8));

    $res = $this->actingAs($admin)->post(route('pharmacies.import.handle'), [
        'mode' => 'analyze',
        'file' => $file,
    ]);

    $res->assertOk();
    $res->assertInertia(fn ($page) => $page
        ->component('Clients/Pharmacies/Import')
        ->has('analysis.token')
        ->where('analysis.summary.create', 2)
        ->where('analysis.summary.error', 0)
    );

    $token = $res->inertiaPage()['props']['analysis']['token'];

    $commit = $this->actingAs($admin)->post(route('pharmacies.import.handle'), [
        'mode' => 'commit',
        'token' => $token,
    ]);

    $commit->assertRedirect(route('pharmacies.index'));

    expect(Pharmacy::where('pharmacy_code', 'PHMD-001')->first())->not->toBeNull();
    expect(Pharmacy::where('pharmacy_code', 'PHMD-002')->first()?->status)->toBe('inactive');
});

test('영업사원은 약국 CSV import에 접근할 수 없다', function () {
    Storage::fake('local');

    $sales = User::factory()->create(['role' => 'sales']);
    $file = UploadedFile::fake()->createWithContent('pharmacies.csv', cp949('관리번호,사업장명,영업상태명'."\n".'PHMD-001,테스트약국,영업/정상'."\n"));

    $this->actingAs($sales)
        ->post(route('pharmacies.import.handle'), ['mode' => 'analyze', 'file' => $file])
        ->assertForbidden();
});

