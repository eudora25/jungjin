<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospital_public_data_imports', function (Blueprint $table) {
            $table->comment('병의원 심평원(HIRA) Excel 보강 업로드·처리 이력 (platform 큐 적재)');
            $table->id()->comment('PK');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('업로드한 운영자 FK');
            $table->string('kind', 30)->comment('적재 유형: institution(B-1) 또는 상세유형(facilities/hours/specialties 등)');
            $table->string('original_filename')->comment('업로드 원본 파일명');
            $table->string('stored_path')->comment('서버 저장 경로 (처리 후 삭제)');
            $table->string('status', 20)->default('pending')->comment('처리 상태: pending/processing/completed/failed');
            $table->json('report')->nullable()->comment('처리 결과 요약(매칭률·연결/스킵 건수 등)');
            $table->text('error')->nullable()->comment('실패 시 오류 메시지');
            $table->timestamp('started_at')->nullable()->comment('처리 시작 시각');
            $table->timestamp('finished_at')->nullable()->comment('처리 종료 시각');
            $table->timestamps();

            $table->index('kind');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_public_data_imports');
    }
};
