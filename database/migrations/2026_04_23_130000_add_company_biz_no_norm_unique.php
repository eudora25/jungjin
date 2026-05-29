<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) 정규화 컬럼 추가 (원본은 보존)
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'business_registration_number_norm')) {
                $table->string('business_registration_number_norm', 10)
                    ->nullable()
                    ->after('business_registration_number')
                    ->comment('사업자등록번호 정규화(숫자만, 10자리) — unique 대상');
            }
        });

        // 2) 숫자만 추출해서 norm에 채우기
        //    MariaDB 11: REGEXP_REPLACE 사용 가능
        DB::statement(<<<'SQL'
            UPDATE companies
               SET business_registration_number_norm = NULLIF(REGEXP_REPLACE(COALESCE(business_registration_number, ''), '[^0-9]', ''), '')
        SQL);

        // 3) 10자리가 아니면 무효 처리
        DB::statement(<<<'SQL'
            UPDATE companies
               SET business_registration_number_norm = NULL
             WHERE business_registration_number_norm IS NOT NULL
               AND CHAR_LENGTH(business_registration_number_norm) <> 10
        SQL);

        // 4) 중복 제거 (첫 레코드만 유지, 나머지는 NULL 처리)
        //    -> 원본 컬럼은 변경하지 않고, norm만 unique 보장
        DB::statement(<<<'SQL'
            UPDATE companies c
            JOIN (
                SELECT id
                FROM (
                    SELECT id,
                           ROW_NUMBER() OVER (PARTITION BY business_registration_number_norm ORDER BY id) AS rn
                    FROM companies
                    WHERE business_registration_number_norm IS NOT NULL
                ) t
                WHERE t.rn > 1
            ) d ON d.id = c.id
            SET c.business_registration_number_norm = NULL
        SQL);

        // 5) unique 인덱스 추가
        $hasIndex = function (string $table, string $name): bool {
            $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]);

            return count($rows) > 0;
        };

        Schema::table('companies', function (Blueprint $table) use ($hasIndex) {
            if (! $hasIndex('companies', 'companies_biz_no_norm_unique')) {
                $table->unique('business_registration_number_norm', 'companies_biz_no_norm_unique');
            }
            if (! $hasIndex('companies', 'companies_biz_no_norm_index')) {
                $table->index('business_registration_number_norm', 'companies_biz_no_norm_index');
            }
        });
    }

    public function down(): void
    {
        $hasIndex = function (string $table, string $name): bool {
            $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]);

            return count($rows) > 0;
        };

        Schema::table('companies', function (Blueprint $table) use ($hasIndex) {
            if ($hasIndex('companies', 'companies_biz_no_norm_unique')) {
                $table->dropUnique('companies_biz_no_norm_unique');
            }
            if ($hasIndex('companies', 'companies_biz_no_norm_index')) {
                $table->dropIndex('companies_biz_no_norm_index');
            }
        });

        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'business_registration_number_norm')) {
                $table->dropColumn('business_registration_number_norm');
            }
        });
    }
};
