<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notice_reads', function (Blueprint $table) {
            $table->comment('공지사항 사용자별 읽음 기록');
            $table->id()->comment('읽음 기록 PK');
            $table->foreignId('notice_id')
                ->constrained('notices')
                ->cascadeOnDelete()
                ->comment('공지 FK (notices.id)');
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('사용자 FK (users.id)');
            $table->timestamp('read_at')->useCurrent()->comment('최초 조회 일시');
            $table->unique(['notice_id', 'user_id'], 'notice_reads_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_reads');
    }
};
