<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_files', function (Blueprint $table) {
            $table->comment('제품 첨부 문서 (허가증/안전정보/카탈로그 등)');
            $table->id()->comment('첨부 PK');
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete()
                ->comment('제품 FK (products.id)');
            $table->enum('file_type', ['license', 'safety', 'catalog', 'etc'])
                ->default('etc')
                ->index()
                ->comment('문서 분류 (허가증/안전정보/카탈로그/기타)');
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
        Schema::dropIfExists('product_files');
    }
};
