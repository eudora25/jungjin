<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-10 MT-1 — 제약사(테넌트) 마스터.
 * 한 시스템에 여러 제약사가 입주하는 멀티테넌시의 최상위 엔티티.
 * 기존 거래처(companies)와는 완전히 별개(자사/제품 소유주).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->comment('제약사(테넌트) 마스터 — 멀티테넌시 최상위 엔티티 (자사/제품 소유주)');
            $table->id()->comment('제약사 PK');
            $table->string('name')->index()->comment('제약사명 (자사)');
            $table->string('code', 50)->nullable()->unique()->comment('사내/플랫폼 식별 코드 (선택)');
            $table->string('business_registration_number', 20)->nullable()->unique()->comment('사업자등록번호 (유일)');
            $table->enum('status', ['active', 'inactive'])->default('active')->index()->comment('입주 상태');
            $table->json('settings')->nullable()->comment('테넌트별 설정 (후속 확장용)');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('생성자 FK (super_admin)');
            $table->timestamps();
            $table->softDeletes()->comment('삭제 일시 (NULL이면 활성)');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
