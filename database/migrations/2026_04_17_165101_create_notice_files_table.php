<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notice_files', function (Blueprint $table) {
            $table->comment('공지사항 첨부파일');
            $table->id()->comment('첨부파일 PK');
            $table->foreignId('notice_id')
                ->constrained('notices')
                ->cascadeOnDelete()
                ->comment('공지 FK (notices.id)');
            $table->string('original_name')->comment('업로드 당시 원본 파일명');
            $table->string('stored_name')->comment('스토리지에 저장된 파일명');
            $table->string('path', 500)->comment('스토리지 상대 경로');
            $table->unsignedBigInteger('size')->default(0)->comment('파일 크기 (bytes)');
            $table->string('mime_type', 150)->comment('MIME 타입');
            $table->string('extension', 16)->comment('확장자');
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('업로더 FK (users.id)');
            $table->timestamps();
            $table->softDeletes()->comment('삭제 일시 (NULL이면 활성)');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_files');
    }
};
