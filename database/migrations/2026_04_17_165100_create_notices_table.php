<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table) {
            $table->comment('공지사항');
            $table->id()->comment('공지 PK');
            $table->string('title')->comment('제목');
            $table->text('content')->nullable()->comment('본문 (HTML 허용)');
            $table->boolean('is_pinned')->default(false)->index()->comment('상단 고정 여부');
            $table->unsignedInteger('view_count')->default(0)->comment('조회수');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('작성자 FK (users.id)');
            $table->timestamps();
            $table->softDeletes()->comment('삭제 일시 (NULL이면 활성)');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notices');
    }
};
