<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->comment('거래처 업체 (UI상 "업체", 내부적으로 companies)');
            $table->id()->comment('업체 PK');
            $table->string('company_name')->index()->comment('업체명');
            $table->string('business_registration_number', 20)->nullable()->index()->comment('사업자등록번호');
            $table->string('representative_name', 100)->nullable()->comment('대표자명');
            $table->string('company_group', 100)->nullable()->index()->comment('업체구분');
            $table->enum('default_commission_grade', ['A', 'B', 'C', 'D', 'E'])->nullable()->comment('기본 수수료 등급');

            $table->string('postcode', 10)->nullable()->comment('우편번호');
            $table->text('business_address')->nullable()->comment('사업장 소재지');

            $table->string('contact_person_name', 100)->nullable()->comment('담당자명');
            $table->string('landline_phone', 20)->nullable()->comment('대표 전화번호');
            $table->string('mobile_phone', 20)->nullable()->comment('담당자 휴대폰');
            $table->string('mobile_phone_2', 20)->nullable()->comment('보조 휴대폰');
            $table->string('email')->nullable()->comment('이메일 (기본 연락)');
            $table->string('receive_email')->nullable()->comment('공지 수신 전용 이메일');
            $table->string('assigned_pharmacist_contact')->nullable()->comment('담당 제약사 관리자');

            $table->text('remarks')->nullable()->comment('비고');

            $table->enum('status', ['active', 'inactive'])->default('active')->index()->comment('업체 활성 상태');
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])
                ->default('pending')
                ->index()
                ->comment('승인 상태');
            $table->timestamp('approved_at')->nullable()->comment('승인 완료 일시');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->comment('승인자 FK (users.id)');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('생성자 FK (users.id)');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->comment('최종 수정자 FK (users.id)');

            $table->timestamps();
            $table->softDeletes()->comment('삭제 일시 (NULL이면 활성)');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
