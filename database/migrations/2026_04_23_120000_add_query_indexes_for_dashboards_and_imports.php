<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasIndex = function (string $table, string $name): bool {
            $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]);

            return count($rows) > 0;
        };

        // sales 대시보드 및 개인 실적 조회 최적화
        Schema::table('performances', function (Blueprint $table) use ($hasIndex) {
            if (! $hasIndex('performances', 'perf_creator_date_idx')) {
                $table->index(['created_by', 'performance_date'], 'perf_creator_date_idx');
            }
            if (! $hasIndex('performances', 'perf_creator_status_date_idx')) {
                $table->index(['created_by', 'status', 'performance_date'], 'perf_creator_status_date_idx');
            }
        });

        // 거래처(Company) 목록 필터 최적화 (partner_type/status/name 조합)
        Schema::table('companies', function (Blueprint $table) use ($hasIndex) {
            if (! $hasIndex('companies', 'companies_partner_status_name_idx')) {
                $table->index(['partner_type', 'status', 'company_name'], 'companies_partner_status_name_idx');
            }
        });

        // health_individual_drugs: 스냅샷 기반 매칭/조회 최적화
        Schema::table('health_individual_drugs', function (Blueprint $table) use ($hasIndex) {
            if (! $hasIndex('health_individual_drugs', 'hid_snapshot_insurance_idx')) {
                $table->index(['data_snapshot_date', 'current_insurance_code'], 'hid_snapshot_insurance_idx');
            }
            if (! $hasIndex('health_individual_drugs', 'hid_snapshot_item_code_idx')) {
                $table->index(['data_snapshot_date', 'item_code'], 'hid_snapshot_item_code_idx');
            }
        });
    }

    public function down(): void
    {
        $hasIndex = function (string $table, string $name): bool {
            $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]);

            return count($rows) > 0;
        };

        Schema::table('performances', function (Blueprint $table) use ($hasIndex) {
            foreach ([
                'perf_creator_date_idx',
                'perf_creator_status_date_idx',
            ] as $idx) {
                if ($hasIndex('performances', $idx)) {
                    $table->dropIndex($idx);
                }
            }
        });

        Schema::table('companies', function (Blueprint $table) use ($hasIndex) {
            if ($hasIndex('companies', 'companies_partner_status_name_idx')) {
                $table->dropIndex('companies_partner_status_name_idx');
            }
        });

        Schema::table('health_individual_drugs', function (Blueprint $table) use ($hasIndex) {
            foreach ([
                'hid_snapshot_insurance_idx',
                'hid_snapshot_item_code_idx',
            ] as $idx) {
                if ($hasIndex('health_individual_drugs', $idx)) {
                    $table->dropIndex($idx);
                }
            }
        });
    }
};
