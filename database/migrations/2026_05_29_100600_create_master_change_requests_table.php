<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-10 MT-8 — 공유 마스터(약국·병의원) 변경요청 승인 워크플로.
 * 제약사(pharma) admin 은 직접 수정 못 하고 변경요청을 제출 → platform 검토·승인 시 반영.
 * 설계: docs/modules/tenancy/MULTI_TENANCY.md §3.3
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_change_requests', function (Blueprint $table) {
            $table->comment('공유 마스터(약국·병의원) 변경요청 — pharma 요청 → platform 승인/반려');
            $table->id()->comment('변경요청 PK');
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete()->comment('요청 제약사(테넌트)');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete()->comment('요청자(제약사 admin)');
            $table->enum('target_type', ['pharmacy', 'hospital'])->index()->comment('대상 마스터 종류');
            $table->unsignedBigInteger('target_id')->nullable()->comment('수정 대상 PK (신규 등록이면 NULL)');
            $table->enum('request_type', ['create', 'update'])->comment('신규 등록 / 수정');
            $table->json('payload')->comment('제안 값(생성/변경 필드)');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index()->comment('검토 상태');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete()->comment('검토자(platform)');
            $table->timestamp('reviewed_at')->nullable()->comment('검토 일시');
            $table->text('review_note')->nullable()->comment('승인/반려 사유');
            $table->unsignedBigInteger('applied_target_id')->nullable()->comment('승인 후 생성/수정된 마스터 PK');
            $table->timestamps();
            $table->softDeletes()->comment('삭제 일시');

            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_change_requests');
    }
};
