<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * GAP-10 MT-2 (1부) — 기본 제약사 시드 + 기존 사용자 백필.
 *
 * 멀티테넌시 전환 시 현재 단일 조직 데이터를 깨지 않기 위해:
 *  1) 기본 제약사(테넌트) 1개 생성 (super_admin 이 MT-6 에서 개명 가능)
 *  2) 기존 admin/sales 사용자의 tenant_id 를 기본 제약사로 백필
 *
 * 도메인 테이블(products/companies/performances/settlements/sales_quotas)의
 * tenant_id 부착·백필은 MT-4 에서 일괄 처리한다 (본 마이그레이션 범위 외).
 */
return new class extends Migration
{
    private const DEFAULT_CODE = 'DEFAULT';

    private const DEFAULT_NAME = '기본 제약사';

    public function up(): void
    {
        // 1) 기본 제약사 — 이미 있으면 재사용 (idempotent)
        $tenantId = DB::table('tenants')->where('code', self::DEFAULT_CODE)->value('id');

        if (! $tenantId) {
            $tenantId = DB::table('tenants')->insertGetId([
                'name' => self::DEFAULT_NAME,
                'code' => self::DEFAULT_CODE,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2) 소속 없는 admin/sales 를 기본 제약사로 백필 (super_admin 은 제외 → NULL 유지)
        DB::table('users')
            ->whereNull('tenant_id')
            ->whereIn('role', ['admin', 'sales', 'pharma', 'cso'])
            ->update(['tenant_id' => $tenantId]);
    }

    public function down(): void
    {
        $tenantId = DB::table('tenants')->where('code', self::DEFAULT_CODE)->value('id');

        if ($tenantId) {
            DB::table('users')->where('tenant_id', $tenantId)->update(['tenant_id' => null]);
            DB::table('tenants')->where('id', $tenantId)->delete();
        }
    }
};
