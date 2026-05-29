<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-10 MT-4 — 도메인 루트 테이블에 tenant_id 부착 + 기본 제약사 백필 + NOT NULL.
 *
 * 대상(루트): products, companies, performances, settlements, sales_quotas.
 * 자식 테이블(product_prices, settlement_lines 등)은 부모 관계로 스코핑하므로 컬럼 미부착.
 *
 * 절차(각 테이블): nullable 컬럼 추가 → 기본 제약사로 백필 → FK(restrict)+인덱스.
 *
 * NOT NULL 전환은 **MT-3(생성 시 tenant_id 자동 주입) 이후** 별도 finalize 마이그레이션에서 적용한다.
 * (지금 NOT NULL 로 하면 tenant_id 를 아직 세팅하지 않는 앱 생성 경로가 모두 깨짐)
 */
return new class extends Migration
{
    private const TABLES = ['products', 'companies', 'performances', 'settlements', 'sales_quotas'];

    public function up(): void
    {
        // 기본 제약사 보장 (MT-2 시드 — 없으면 안전망 생성)
        $tenantId = DB::table('tenants')->where('code', 'DEFAULT')->value('id');
        if (! $tenantId) {
            $tenantId = DB::table('tenants')->insertGetId([
                'name' => '기본 제약사',
                'code' => 'DEFAULT',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (self::TABLES as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id')
                    ->comment('소속 제약사(테넌트) FK');
            });

            DB::table($t)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);

            Schema::table($t, function (Blueprint $table) {
                $table->index('tenant_id');
                $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
