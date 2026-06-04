<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-10 MT-4-finalize — 도메인 루트 테이블 tenant_id 를 NOT NULL 로 전환.
 * 선행: MT-3(생성 시 tenant_id 자동 주입) 완료 → 앱/팩토리 생성 경로가 항상 tenant_id 를 채움.
 * 만약을 위한 안전망으로, 전환 직전 잔여 null 은 기본 제약사(DEFAULT)로 백필한다.
 */
return new class extends Migration
{
    private const TABLES = ['products', 'companies', 'performances', 'settlements', 'sales_quotas'];

    public function up(): void
    {
        $tenantId = DB::table('tenants')->where('code', 'DEFAULT')->value('id');

        foreach (self::TABLES as $t) {
            // 안전망: 잔여 null 백필 (정상 경로면 0건)
            if ($tenantId) {
                DB::table($t)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            }

            Schema::table($t, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable(false)->comment('소속 제약사(테넌트) FK')->change();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->comment('소속 제약사(테넌트) FK')->change();
            });
        }
    }
};
