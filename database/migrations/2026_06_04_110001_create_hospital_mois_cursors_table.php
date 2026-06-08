<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospital_mois_cursors', function (Blueprint $table) {
            $table->comment('병의원 MOIS API 업종별 증분 커서 (마지막 반영 DAT_UPDT_PNT 추적)');
            $table->id()->comment('PK');
            $table->string('api_id', 20)->unique()->comment('업종 데이터셋 API ID (clinics=15154874 등) — 유니크');
            $table->string('last_synced_at', 14)->nullable()->comment('마지막으로 반영한 DAT_UPDT_PNT (yyyyMMddHHmmss)');
            $table->foreignId('last_sync_id')->nullable()->constrained('hospital_mois_syncs')->nullOnDelete()->comment('마지막 성공 동기화 이력 FK');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_mois_cursors');
    }
};
