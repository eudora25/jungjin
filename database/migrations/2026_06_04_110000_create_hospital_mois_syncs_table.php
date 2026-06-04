<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospital_mois_syncs', function (Blueprint $table) {
            $table->comment('병의원 행안부(MOIS) API 증분 동기화 실행 이력 (platform 공유 마스터)');
            $table->id()->comment('PK');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('수동 트리거 운영자 FK (스케줄 실행은 null)');
            $table->string('trigger', 20)->comment('트리거 종류: schedule(스케줄러)/manual(수동)');
            $table->json('params')->nullable()->comment('실행 파라미터(since·대상 업종 목록 등)');
            $table->string('status', 20)->default('pending')->comment('처리 상태: pending/processing/completed/failed');
            $table->json('report')->nullable()->comment('업종별 결과 요약(fetched/inserted/updated/closed/skipped/failed)');
            $table->text('error')->nullable()->comment('실패 시 오류 메시지');
            $table->timestamp('started_at')->nullable()->comment('처리 시작 시각');
            $table->timestamp('finished_at')->nullable()->comment('처리 종료 시각');
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_mois_syncs');
    }
};
