<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacies', function (Blueprint $table) {
            $table->comment('약국 마스터 — 제품 납품처 중 약국 유형');
            $table->id()->comment('약국 PK');
            $table->string('pharmacy_code', 50)->nullable()->unique()->comment('약국 코드 (사내 식별자, 선택)');
            $table->string('pharmacy_name')->index()->comment('약국명');
            $table->string('business_registration_number', 20)->nullable()->unique()->comment('사업자등록번호 (유일)');
            $table->string('representative_name', 100)->nullable()->comment('대표자명');
            $table->string('postcode', 10)->nullable()->comment('우편번호');
            $table->text('address')->nullable()->comment('주소');
            $table->string('landline_phone', 20)->nullable()->comment('대표 전화');
            $table->string('mobile_phone', 20)->nullable()->comment('대표자 휴대폰');
            $table->string('contact_person_name', 100)->nullable()->comment('담당자명');
            $table->string('contact_phone', 20)->nullable()->comment('담당자 연락처');
            $table->string('email')->nullable()->comment('이메일');
            $table->text('remarks')->nullable()->comment('비고');
            $table->enum('status', ['active', 'inactive'])->default('active')->index()->comment('약국 상태');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('생성자 FK');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->comment('최종 수정자 FK');
            $table->timestamps();
            $table->softDeletes()->comment('삭제 일시 (NULL이면 활성)');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacies');
    }
};
