<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->comment('Queue 작업 대기열');
            $table->id()->comment('작업 PK');
            $table->string('queue')->index()->comment('큐 이름 (default, high, low 등)');
            $table->longText('payload')->comment('직렬화된 작업 페이로드');
            $table->unsignedTinyInteger('attempts')->comment('재시도 횟수');
            $table->unsignedInteger('reserved_at')->nullable()->comment('워커가 점유한 시각 (unix timestamp)');
            $table->unsignedInteger('available_at')->comment('실행 가능 시각 (unix timestamp, 지연 작업용)');
            $table->unsignedInteger('created_at')->comment('작업 등록 시각 (unix timestamp)');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->comment('Queue 작업 배치 (다건 묶음 실행)');
            $table->string('id')->primary()->comment('배치 UUID');
            $table->string('name')->comment('배치 이름');
            $table->integer('total_jobs')->comment('전체 작업 수');
            $table->integer('pending_jobs')->comment('대기 중 작업 수');
            $table->integer('failed_jobs')->comment('실패한 작업 수');
            $table->longText('failed_job_ids')->comment('실패 작업 ID 목록 (JSON)');
            $table->mediumText('options')->nullable()->comment('배치 옵션 (직렬화)');
            $table->integer('cancelled_at')->nullable()->comment('취소 시각 (unix timestamp)');
            $table->integer('created_at')->comment('배치 생성 시각 (unix timestamp)');
            $table->integer('finished_at')->nullable()->comment('배치 완료 시각 (unix timestamp)');
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->comment('실패한 Queue 작업 이력');
            $table->id()->comment('실패 이력 PK');
            $table->string('uuid')->unique()->comment('작업 UUID');
            $table->text('connection')->comment('큐 커넥션 이름');
            $table->text('queue')->comment('큐 이름');
            $table->longText('payload')->comment('직렬화된 작업 페이로드');
            $table->longText('exception')->comment('예외 스택트레이스');
            $table->timestamp('failed_at')->useCurrent()->comment('실패 시각');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
    }
};
