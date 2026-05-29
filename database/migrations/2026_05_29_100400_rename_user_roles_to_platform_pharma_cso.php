<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * GAP-10 — role 코드 값 리네임 (의미 명확화).
 *   super_admin → platform (정진팜 플랫폼 운영자)
 *   admin       → pharma   (제약사 관리자)
 *   sales       → cso      (영업, CSO)
 *
 * tenant_id=null 로 super 를 구분하던 방식을 버리고, role 코드 값으로만 명확히 구분.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) 신·구 값을 모두 허용하는 superset 으로 확장
        DB::statement("ALTER TABLE users MODIFY role ENUM('super_admin','admin','sales','platform','pharma','cso') NOT NULL DEFAULT 'sales'");

        // 2) 값 치환
        DB::table('users')->where('role', 'super_admin')->update(['role' => 'platform']);
        DB::table('users')->where('role', 'admin')->update(['role' => 'pharma']);
        DB::table('users')->where('role', 'sales')->update(['role' => 'cso']);

        // 3) 최종 enum 으로 축소
        DB::statement("ALTER TABLE users MODIFY role ENUM('platform','pharma','cso') NOT NULL DEFAULT 'cso' COMMENT '권한: platform=정진팜 운영자, pharma=제약사 관리자, cso=영업(CSO)'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('super_admin','admin','sales','platform','pharma','cso') NOT NULL DEFAULT 'cso'");

        DB::table('users')->where('role', 'platform')->update(['role' => 'super_admin']);
        DB::table('users')->where('role', 'pharma')->update(['role' => 'admin']);
        DB::table('users')->where('role', 'cso')->update(['role' => 'sales']);

        DB::statement("ALTER TABLE users MODIFY role ENUM('super_admin','admin','sales') NOT NULL DEFAULT 'sales' COMMENT '권한 구분: super_admin=플랫폼 운영자, admin=제약사 관리자, sales=영업사원'");
    }
};
