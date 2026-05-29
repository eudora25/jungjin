<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-10 — 코드 정의(공통 코드) 마스터.
 * role 등 구분 값의 의미(라벨/설명)를 저장해 나중에 조회·검색할 수 있게 한다.
 * group_code 로 묶어 여러 코드 그룹을 한 테이블에서 관리(재사용).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_definitions', function (Blueprint $table) {
            $table->comment('공통 코드 정의 — 구분 값(role 등)의 의미·라벨·설명 저장');
            $table->id()->comment('코드 정의 PK');
            $table->string('group_code', 50)->comment('코드 그룹 (예: user_role)');
            $table->string('code', 50)->comment('코드 값 (예: platform)');
            $table->string('name')->comment('표시 라벨 (예: 플랫폼 운영자)');
            $table->text('description')->nullable()->comment('상세 설명');
            $table->unsignedInteger('sort_order')->default(0)->comment('정렬 순서');
            $table->boolean('is_active')->default(true)->comment('활성 여부');
            $table->timestamps();

            $table->unique(['group_code', 'code'], 'code_definitions_group_code_unique');
            $table->index('group_code');
        });

        // user_role 코드 시드 (platform/pharma/cso)
        $now = now();
        DB::table('code_definitions')->insert([
            [
                'group_code' => 'user_role', 'code' => 'platform', 'sort_order' => 1, 'is_active' => true,
                'name' => '플랫폼 운영자', 'description' => '정진팜 플랫폼 운영자. 모든 제약사·시스템을 관리(전역). 소속 테넌트 없음.',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'group_code' => 'user_role', 'code' => 'pharma', 'sort_order' => 2, 'is_active' => true,
                'name' => '제약사 관리자', 'description' => '제약사(테넌트) 관리자. 자사 제품·실적·정산·거래처·소속 영업(CSO)을 관리.',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'group_code' => 'user_role', 'code' => 'cso', 'sort_order' => 3, 'is_active' => true,
                'name' => '영업(CSO)', 'description' => '소속 제약사의 제품 판매 실적을 등록·조회하는 영업 담당.',
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('code_definitions');
    }
};
