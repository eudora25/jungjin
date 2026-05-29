<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->comment('애플리케이션 캐시 저장소 (DB 캐시 드라이버)');
            $table->string('key')->primary()->comment('캐시 키');
            $table->mediumText('value')->comment('직렬화된 캐시 값');
            $table->integer('expiration')->comment('만료 시각 (unix timestamp)');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->comment('캐시 분산 락 (동시성 제어)');
            $table->string('key')->primary()->comment('락 키');
            $table->string('owner')->comment('락 소유자 식별자');
            $table->integer('expiration')->comment('락 만료 시각 (unix timestamp)');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
