<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitals', function (Blueprint $table) {
            $table->comment('병의원 마스터 — 제품 납품처 중 병원/의원/치과/한의원 유형');
            $table->id()->comment('병의원 PK');
            $table->string('hospital_code', 50)->nullable()->unique()->comment('병의원 코드 (사내 식별자, 선택)');
            $table->string('hospital_name')->index()->comment('병의원명');
            $table->string('business_registration_number', 20)->nullable()->unique()->comment('사업자등록번호 (유일)');
            $table->enum('hospital_type', [
                'general_hospital', 'hospital', 'clinic', 'dental', 'oriental', 'other',
            ])->nullable()->index()->comment('병의원 유형 (종합병원/병원/의원/치과/한의원/기타)');
            $table->string('specialty', 100)->nullable()->comment('전문 분야');
            $table->string('representative_name', 100)->nullable()->comment('원장/대표자명');
            $table->string('postcode', 10)->nullable()->comment('우편번호');
            $table->text('address')->nullable()->comment('주소');
            $table->string('phone', 20)->nullable()->comment('대표 전화');
            $table->string('contact_person_name', 100)->nullable()->comment('담당자명');
            $table->string('contact_phone', 20)->nullable()->comment('담당자 연락처');
            $table->string('email')->nullable()->comment('이메일');
            $table->text('remarks')->nullable()->comment('비고');
            $table->enum('status', ['active', 'inactive'])->default('active')->index()->comment('병의원 상태');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('생성자 FK');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->comment('최종 수정자 FK');
            $table->timestamps();
            $table->softDeletes()->comment('삭제 일시 (NULL이면 활성)');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitals');
    }
};
