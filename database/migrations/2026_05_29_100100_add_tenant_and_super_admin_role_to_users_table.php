<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-10 MT-1 — users 에 멀티테넌시 토대 추가.
 *  - role enum 에 super_admin(플랫폼 운영자) 추가
 *  - tenant_id (소속 제약사) FK — admin/sales 는 값, super_admin 은 NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        // role enum 확장: super_admin 추가 (MariaDB — 원자적 ALTER)
        DB::statement(
            "ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'admin', 'sales') NOT NULL DEFAULT 'sales' COMMENT '권한 구분: super_admin=플랫폼 운영자, admin=제약사 관리자, sales=영업사원'"
        );

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('role')
                ->constrained('tenants')
                ->nullOnDelete()
                ->comment('소속 제약사(테넌트) FK — super_admin 은 NULL');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        DB::statement(
            "ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'sales') NOT NULL DEFAULT 'sales' COMMENT '권한 구분: admin=관리자(CSO), sales=영업사원'"
        );
    }
};
