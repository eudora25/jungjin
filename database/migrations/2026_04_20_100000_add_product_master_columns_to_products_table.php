<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) 컬럼 추가 — 이미 있으면 skip (재실행 안전)
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'standard_code')) {
                $table->string('standard_code', 50)->nullable()->after('insurance_code')
                    ->comment('식약처 표준코드(KD 코드)');
            }
            if (! Schema::hasColumn('products', 'barcode_gtin')) {
                $table->string('barcode_gtin', 20)->nullable()->after('standard_code')
                    ->comment('유통 GTIN(바코드)');
            }
            if (! Schema::hasColumn('products', 'generic_name')) {
                $table->string('generic_name', 150)->nullable()->after('product_name')
                    ->comment('성분명/일반명');
            }
            if (! Schema::hasColumn('products', 'strength')) {
                $table->string('strength', 50)->nullable()->after('generic_name')
                    ->comment('함량 (예: 500mg)');
            }
            if (! Schema::hasColumn('products', 'unit')) {
                $table->string('unit', 20)->nullable()->after('strength')
                    ->comment('기본 단위 (정/캡슐/병/앰플/포 등)');
            }
            if (! Schema::hasColumn('products', 'pack_size')) {
                $table->unsignedInteger('pack_size')->nullable()->after('unit')
                    ->comment('포장 수량 (예: 100정/박스)');
            }

            if (! Schema::hasColumn('products', 'drug_type')) {
                $table->enum('drug_type', ['general', 'etc', 'narcotic', 'psychotropic'])
                    ->default('general')->after('category')
                    ->comment('약품 유형 (일반/전문/마약/향정)');
            }
            if (! Schema::hasColumn('products', 'storage_condition')) {
                $table->enum('storage_condition', ['room', 'cold', 'frozen'])
                    ->default('room')->after('drug_type')
                    ->comment('보관 조건 (실온/냉장/냉동)');
            }

            if (! Schema::hasColumn('products', 'replacement_product_id')) {
                $table->foreignId('replacement_product_id')->nullable()->after('status')
                    ->constrained('products')->nullOnDelete()
                    ->comment('단종 시 대체품 FK (선택)');
            }

            if (! Schema::hasColumn('products', 'approval_status')) {
                $table->enum('approval_status', ['draft', 'pending', 'reviewed', 'approved', 'rejected'])
                    ->default('draft')->after('replacement_product_id')
                    ->comment('승인 상태 (4단계)');
            }
            if (! Schema::hasColumn('products', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('approval_status')
                    ->comment('검수 완료 일시');
            }
            if (! Schema::hasColumn('products', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')
                    ->constrained('users')->nullOnDelete()
                    ->comment('검수자 FK (users.id)');
            }
            if (! Schema::hasColumn('products', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('reviewed_by')
                    ->comment('승인 일시');
            }
            if (! Schema::hasColumn('products', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approved_at')
                    ->constrained('users')->nullOnDelete()
                    ->comment('승인자 FK (users.id)');
            }

            if (! Schema::hasColumn('products', 'nims_managed')) {
                $table->boolean('nims_managed')->default(false)->after('approved_by')
                    ->comment('NIMS 관리대상 여부 (마약/향정이면 자동 true)');
            }
            if (! Schema::hasColumn('products', 'nims_item_code')) {
                $table->string('nims_item_code', 50)->nullable()->after('nims_managed')
                    ->comment('NIMS 품목코드');
            }
        });

        // 2) insurance_code 중복 정리 — 그룹 내 첫 행(최저 id)은 유지, 나머지는 "-dup{id}" 접미사
        //    (운영자가 중복 행을 다시 식별해 정정/병합할 수 있도록 흔적을 남김)
        DB::statement(<<<'SQL'
            UPDATE products p
            JOIN (
                SELECT id
                FROM (
                    SELECT id,
                           ROW_NUMBER() OVER (PARTITION BY insurance_code ORDER BY id) AS rn
                    FROM products
                    WHERE insurance_code IS NOT NULL
                      AND insurance_code <> ''
                ) t
                WHERE t.rn > 1
            ) d ON d.id = p.id
            SET p.insurance_code = CONCAT(p.insurance_code, '-dup', p.id)
        SQL);

        // 3) 인덱스 / Unique 추가 — 이미 있으면 skip
        $hasIndex = function (string $table, string $name): bool {
            $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]);

            return count($rows) > 0;
        };

        Schema::table('products', function (Blueprint $table) use ($hasIndex) {
            if (! $hasIndex('products', 'products_standard_code_index')) {
                $table->index('standard_code', 'products_standard_code_index');
            }
            if (! $hasIndex('products', 'products_barcode_gtin_index')) {
                $table->index('barcode_gtin', 'products_barcode_gtin_index');
            }
            if (! $hasIndex('products', 'products_generic_name_index')) {
                $table->index('generic_name', 'products_generic_name_index');
            }
            if (! $hasIndex('products', 'products_drug_type_index')) {
                $table->index('drug_type', 'products_drug_type_index');
            }
            if (! $hasIndex('products', 'products_approval_status_index')) {
                $table->index('approval_status', 'products_approval_status_index');
            }
            if (! $hasIndex('products', 'products_nims_managed_index')) {
                $table->index('nims_managed', 'products_nims_managed_index');
            }
            if (! $hasIndex('products', 'products_nims_item_code_index')) {
                $table->index('nims_item_code', 'products_nims_item_code_index');
            }
            if (! $hasIndex('products', 'products_insurance_code_unique')) {
                $table->unique('insurance_code', 'products_insurance_code_unique');
            }
        });
    }

    public function down(): void
    {
        $hasIndex = function (string $table, string $name): bool {
            $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]);

            return count($rows) > 0;
        };

        Schema::table('products', function (Blueprint $table) use ($hasIndex) {
            if ($hasIndex('products', 'products_insurance_code_unique')) {
                $table->dropUnique('products_insurance_code_unique');
            }
            foreach ([
                'products_nims_item_code_index',
                'products_nims_managed_index',
                'products_approval_status_index',
                'products_drug_type_index',
                'products_generic_name_index',
                'products_barcode_gtin_index',
                'products_standard_code_index',
            ] as $idx) {
                if ($hasIndex('products', $idx)) {
                    $table->dropIndex($idx);
                }
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }
            if (Schema::hasColumn('products', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
            if (Schema::hasColumn('products', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }
            foreach (['reviewed_at', 'approval_status'] as $c) {
                if (Schema::hasColumn('products', $c)) {
                    $table->dropColumn($c);
                }
            }
            if (Schema::hasColumn('products', 'replacement_product_id')) {
                $table->dropConstrainedForeignId('replacement_product_id');
            }
            foreach ([
                'storage_condition', 'drug_type', 'pack_size', 'unit',
                'strength', 'generic_name', 'barcode_gtin', 'standard_code',
                'nims_item_code', 'nims_managed',
            ] as $c) {
                if (Schema::hasColumn('products', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
