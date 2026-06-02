<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            $table->string('ykiho', 191)->nullable()->unique()->after('hospital_code')
                ->comment('심평원(HIRA) 암호화요양기호 — 연관 데이터 조인키, 기관명+우편번호 매칭 성공 시 채움 (base64 약 70~80자)');
            $table->string('clazz_code', 10)->nullable()->after('hospital_type')
                ->comment('종별코드 (원천 코드 보존, code_definitions 와 병행)');
            $table->string('sido_code', 10)->nullable()->after('clazz_code')->comment('시도코드');
            $table->string('sigungu_code', 10)->nullable()->after('sido_code')->comment('시군구코드');
            $table->string('eupmyeondong', 50)->nullable()->after('sigungu_code')->comment('읍면동');
            $table->date('opened_on')->nullable()->after('phone')->comment('개설(인허가)일자');
            $table->date('closed_on')->nullable()->after('opened_on')->comment('폐업일자');
            $table->date('suspend_begin_on')->nullable()->after('closed_on')->comment('휴업 시작일자');
            $table->date('suspend_end_on')->nullable()->after('suspend_begin_on')->comment('휴업 종료일자');
            $table->unsignedInteger('doctor_count')->nullable()->after('suspend_end_on')->comment('의료인(의사) 수');
            $table->unsignedInteger('bed_count')->nullable()->after('doctor_count')->comment('병상 수');
            $table->unsignedInteger('inpatient_room_count')->nullable()->after('bed_count')->comment('입원실 수');
            $table->decimal('total_area', 12, 2)->nullable()->after('inpatient_room_count')->comment('총면적(㎡)');
            $table->decimal('latitude', 10, 7)->nullable()->after('total_area')->comment('위도(WGS84) — 심평원 좌표');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude')->comment('경도(WGS84) — 심평원 좌표');
            $table->string('homepage')->nullable()->after('longitude')->comment('병원 홈페이지 URL');
            $table->string('license_authority_code', 10)->nullable()->after('homepage')->comment('개방자치단체코드 (인허가 출처)');
            $table->timestamp('source_synced_at')->nullable()->after('license_authority_code')->comment('공공데이터 갱신시점 (재적재 비교용)');

            $table->index('clazz_code');
            $table->index(['sido_code', 'sigungu_code']);
        });
    }

    public function down(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            $table->dropIndex(['clazz_code']);
            $table->dropIndex(['sido_code', 'sigungu_code']);
            $table->dropColumn([
                'ykiho', 'clazz_code', 'sido_code', 'sigungu_code', 'eupmyeondong',
                'opened_on', 'closed_on', 'suspend_begin_on', 'suspend_end_on',
                'doctor_count', 'bed_count', 'inpatient_room_count', 'total_area',
                'latitude', 'longitude', 'homepage', 'license_authority_code', 'source_synced_at',
            ]);
        });
    }
};
