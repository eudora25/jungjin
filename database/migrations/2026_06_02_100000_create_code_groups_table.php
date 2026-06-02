<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-10 — 코드 그룹(공통 코드 그룹) 마스터.
 * code_definitions.group_code 의 상위 정의. 어떤 코드 그룹이 존재하는지와
 * 그 그룹의 한글명·설명을 한 곳에서 관리(화이트리스트)하고, code_definitions 가 FK 로 참조한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_groups', function (Blueprint $table) {
            $table->comment('공통 코드 그룹 — 코드 그룹(group_code)의 의미·라벨·설명 정의');
            $table->id()->comment('코드 그룹 PK');
            $table->string('group_code', 50)->unique()->comment('코드 그룹 값 (예: user_role) — code_definitions 가 참조');
            $table->string('name')->comment('그룹 표시 라벨 (예: 사용자 권한)');
            $table->text('description')->nullable()->comment('그룹 상세 설명');
            $table->unsignedInteger('sort_order')->default(0)->comment('정렬 순서');
            $table->boolean('is_active')->default(true)->comment('활성 여부');
            $table->timestamps();
        });

        // 기존 code_definitions 의 그룹을 시드 (user_role)
        $now = now();
        DB::table('code_groups')->insert([
            [
                'group_code' => 'user_role', 'sort_order' => 1, 'is_active' => true,
                'name' => '사용자 권한', 'description' => '사용자 역할 구분 (platform/pharma/cso).',
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        // code_definitions.group_code → code_groups.group_code FK 연결 (시드 후)
        Schema::table('code_definitions', function (Blueprint $table) {
            $table->foreign('group_code')
                ->references('group_code')->on('code_groups')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('code_definitions', function (Blueprint $table) {
            $table->dropForeign(['group_code']);
        });

        Schema::dropIfExists('code_groups');
    }
};
