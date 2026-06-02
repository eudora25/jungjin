<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // B-5 진료과목정보 (1:N)
        Schema::create('hospital_specialties', function (Blueprint $table) {
            $table->comment('병의원 진료과목 — 심평원 진료과목정보(과목별 전문의수)');
            $table->id()->comment('PK');
            $table->foreignId('hospital_id')->comment('병의원 FK')->constrained('hospitals')->cascadeOnDelete();
            $table->string('dept_code', 20)->comment('진료과목코드');
            $table->string('dept_name', 100)->nullable()->comment('진료과목명');
            $table->unsignedInteger('specialist_count')->nullable()->comment('과목별 전문의수');
            $table->unsignedInteger('selective_doctor_count')->nullable()->comment('선택진료 의사수');
            $table->timestamps();
            $table->unique(['hospital_id', 'dept_code']);
        });

        // B-7 의료장비정보 (1:N)
        Schema::create('hospital_equipments', function (Blueprint $table) {
            $table->comment('병의원 의료장비 — 심평원 의료장비정보');
            $table->id()->comment('PK');
            $table->foreignId('hospital_id')->comment('병의원 FK')->constrained('hospitals')->cascadeOnDelete();
            $table->string('equipment_code', 20)->comment('장비코드');
            $table->string('equipment_name', 255)->nullable()->comment('장비명');
            $table->unsignedInteger('quantity')->nullable()->comment('장비 대수');
            $table->timestamps();
            $table->unique(['hospital_id', 'equipment_code']);
        });

        // B-3 시설정보 (1:1) — 설립구분 + 병상 상세
        Schema::create('hospital_facilities', function (Blueprint $table) {
            $table->comment('병의원 시설·병상 상세 — 심평원 시설정보 (기관당 1행)');
            $table->id()->comment('PK');
            $table->foreignId('hospital_id')->comment('병의원 FK (1:1)')->unique()->constrained('hospitals')->cascadeOnDelete();
            $table->string('establishment_code', 10)->nullable()->comment('설립구분코드');
            $table->string('establishment_name', 50)->nullable()->comment('설립구분명 (개인/법인 등)');
            $table->unsignedInteger('general_upper_beds')->nullable()->comment('일반입원실 상급병상수');
            $table->unsignedInteger('general_normal_beds')->nullable()->comment('일반입원실 일반병상수');
            $table->unsignedInteger('adult_icu_beds')->nullable()->comment('성인 중환자 병상수');
            $table->unsignedInteger('child_icu_beds')->nullable()->comment('소아 중환자 병상수');
            $table->unsignedInteger('newborn_icu_beds')->nullable()->comment('신생아 중환자 병상수');
            $table->unsignedInteger('delivery_room_beds')->nullable()->comment('분만실 병상수');
            $table->unsignedInteger('operating_room_beds')->nullable()->comment('수술실 병상수');
            $table->unsignedInteger('emergency_room_beds')->nullable()->comment('응급실 병상수');
            $table->unsignedInteger('physical_therapy_beds')->nullable()->comment('물리치료실 병상수');
            $table->unsignedInteger('psych_closed_upper_beds')->nullable()->comment('정신과 폐쇄 상급병상수');
            $table->unsignedInteger('psych_closed_normal_beds')->nullable()->comment('정신과 폐쇄 일반병상수');
            $table->unsignedInteger('psych_open_upper_beds')->nullable()->comment('정신과 개방 상급병상수');
            $table->unsignedInteger('psych_open_normal_beds')->nullable()->comment('정신과 개방 일반병상수');
            $table->unsignedInteger('isolation_beds')->nullable()->comment('격리병실 병상수');
            $table->unsignedInteger('sterile_treatment_beds')->nullable()->comment('무균치료실 병상수');
            $table->timestamps();
        });

        // B-4 세부정보 (1:1) — 위치·주차·휴진·응급실·진료시간
        Schema::create('hospital_hours', function (Blueprint $table) {
            $table->comment('병의원 진료시간·편의 — 심평원 세부정보 (기관당 1행)');
            $table->id()->comment('PK');
            $table->foreignId('hospital_id')->comment('병의원 FK (1:1)')->unique()->constrained('hospitals')->cascadeOnDelete();
            // 심평원 세부정보는 자유 텍스트 — 실데이터 길이 편차가 커 넉넉히 잡음
            $table->string('location_building', 255)->nullable()->comment('위치 공공건물(장소)명');
            $table->string('location_direction', 255)->nullable()->comment('위치 방향');
            $table->string('location_distance', 255)->nullable()->comment('위치 거리');
            $table->unsignedInteger('parking_capacity')->nullable()->comment('주차 가능대수');
            $table->boolean('parking_fee_required')->nullable()->comment('주차 비용 부담여부');
            $table->string('parking_note', 500)->nullable()->comment('주차 기타 안내사항');
            $table->string('closed_sunday', 500)->nullable()->comment('휴진안내 일요일');
            $table->string('closed_holiday', 500)->nullable()->comment('휴진안내 공휴일');
            $table->boolean('er_day_available')->nullable()->comment('응급실 주간 운영여부');
            $table->string('er_day_phone1', 60)->nullable()->comment('응급실 주간 전화번호1');
            $table->string('er_day_phone2', 60)->nullable()->comment('응급실 주간 전화번호2');
            $table->boolean('er_night_available')->nullable()->comment('응급실 야간 운영여부');
            $table->string('er_night_phone1', 60)->nullable()->comment('응급실 야간 전화번호1');
            $table->string('er_night_phone2', 60)->nullable()->comment('응급실 야간 전화번호2');
            $table->string('lunch_weekday', 255)->nullable()->comment('점심시간 평일');
            $table->string('lunch_saturday', 255)->nullable()->comment('점심시간 토요일');
            $table->string('reception_weekday', 255)->nullable()->comment('접수시간 평일');
            $table->string('reception_saturday', 255)->nullable()->comment('접수시간 토요일');
            $table->json('treatment_hours')->nullable()->comment('요일별 진료 시작/종료 — {"mon":{"start":"0900","end":"1800"}, ...}');
            $table->timestamps();
        });

        // B-6 교통정보 (1:N)
        Schema::create('hospital_transports', function (Blueprint $table) {
            $table->comment('병의원 교통편 — 심평원 교통정보');
            $table->id()->comment('PK');
            $table->foreignId('hospital_id')->comment('병의원 FK')->constrained('hospitals')->cascadeOnDelete();
            $table->string('transport_type', 100)->nullable()->comment('교통편명 (지하철/버스 등)');
            $table->string('route_no', 100)->nullable()->comment('노선번호');
            $table->string('stop_name', 255)->nullable()->comment('하차지점');
            $table->string('direction', 100)->nullable()->comment('방향');
            $table->string('distance', 255)->nullable()->comment('거리');
            $table->string('remarks', 500)->nullable()->comment('비고');
            $table->timestamps();
            $table->index('hospital_id', 'hospital_transports_hospital_idx');
        });

        // B-9 간호등급정보 (1:N)
        Schema::create('hospital_nursing_grades', function (Blueprint $table) {
            $table->comment('병의원 간호등급 — 심평원 간호등급정보');
            $table->id()->comment('PK');
            $table->foreignId('hospital_id')->comment('병의원 FK')->constrained('hospitals')->cascadeOnDelete();
            $table->string('insurance_type_code', 10)->nullable()->comment('유형코드 (건강보험/의료급여)');
            $table->string('insurance_type_name', 50)->nullable()->comment('유형명');
            $table->string('nursing_grade', 10)->nullable()->comment('간호등급');
            $table->timestamps();
            $table->unique(['hospital_id', 'insurance_type_code']);
        });

        // B-8 식대가산정보 (1:N)
        Schema::create('hospital_meal_surcharges', function (Blueprint $table) {
            $table->comment('병의원 식대가산 — 심평원 식대가산정보');
            $table->id()->comment('PK');
            $table->foreignId('hospital_id')->comment('병의원 FK')->constrained('hospitals')->cascadeOnDelete();
            $table->string('type_code', 10)->nullable()->comment('유형코드 (영양사/조리사 등)');
            $table->string('type_name', 50)->nullable()->comment('유형명');
            $table->boolean('general_meal_surcharge')->nullable()->comment('일반식 가산여부');
            $table->unsignedInteger('calc_headcount')->nullable()->comment('산정인원수');
            $table->string('therapeutic_meal_grade', 20)->nullable()->comment('치료식 등급');
            $table->timestamps();
            $table->unique(['hospital_id', 'type_code']);
        });

        // B-10 특수진료정보 (1:N)
        Schema::create('hospital_special_treatments', function (Blueprint $table) {
            $table->comment('병의원 특수진료/사업참여 — 심평원 특수진료정보');
            $table->id()->comment('PK');
            $table->foreignId('hospital_id')->comment('병의원 FK')->constrained('hospitals')->cascadeOnDelete();
            $table->string('search_code', 20)->comment('검색코드');
            $table->string('search_name', 255)->nullable()->comment('검색코드명 (HPV 예방접종 등)');
            $table->timestamps();
            $table->unique(['hospital_id', 'search_code']);
        });

        // B-11 전문병원지정분야 (1:N)
        Schema::create('hospital_specialized_fields', function (Blueprint $table) {
            $table->comment('전문병원 지정분야 — 심평원 전문병원지정분야');
            $table->id()->comment('PK');
            $table->foreignId('hospital_id')->comment('병의원 FK')->constrained('hospitals')->cascadeOnDelete();
            $table->string('field_code', 20)->comment('지정분야 코드');
            $table->string('field_name', 100)->nullable()->comment('지정분야명 (척추 등)');
            $table->timestamps();
            $table->unique(['hospital_id', 'field_code']);
        });

        // B-12 기타인력정보 (1:N)
        Schema::create('hospital_other_staff', function (Blueprint $table) {
            $table->comment('병의원 기타인력 — 심평원 기타인력정보');
            $table->id()->comment('PK');
            $table->foreignId('hospital_id')->comment('병의원 FK')->constrained('hospitals')->cascadeOnDelete();
            $table->string('staff_code', 20)->comment('기타인력코드');
            $table->string('staff_name', 100)->nullable()->comment('기타인력명 (약사 등)');
            $table->unsignedInteger('staff_count')->nullable()->comment('기타인력수');
            $table->timestamps();
            $table->unique(['hospital_id', 'staff_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_other_staff');
        Schema::dropIfExists('hospital_specialized_fields');
        Schema::dropIfExists('hospital_special_treatments');
        Schema::dropIfExists('hospital_meal_surcharges');
        Schema::dropIfExists('hospital_nursing_grades');
        Schema::dropIfExists('hospital_transports');
        Schema::dropIfExists('hospital_hours');
        Schema::dropIfExists('hospital_facilities');
        Schema::dropIfExists('hospital_equipments');
        Schema::dropIfExists('hospital_specialties');
    }
};
