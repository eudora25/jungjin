<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performances', function (Blueprint $table) {
            $table->id();

            $table->string('performance_no', 20)->unique()
                ->comment('사람이 읽는 실적번호 (YYYYMMDD-NNNN)');
            $table->date('performance_date')
                ->comment('실적 발생일 — 가격/수수료 해석의 기준 시점');

            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            $table->integer('quantity')
                ->comment('수량 — 음수 허용(반품/조정)');

            // 스냅샷
            $table->decimal('unit_price', 12, 2)
                ->comment('스냅샷: 생성 시점에 해석된 매출 단가');
            $table->decimal('commission_rate', 5, 2)->nullable()
                ->comment('스냅샷: 생성 시점에 해석된 수수료율 (%)');
            // subtotal / commission_amount 은 DB virtual stored 컬럼으로 생성.
            // MariaDB/MySQL 문법 차이 때문에 raw statement 로 처리.

            // 출처 추적
            $table->enum('price_source', ['override', 'product_sale', 'products_price', 'manual'])
                ->comment('단가가 어디에서 해석됐는지');
            $table->enum('commission_source', ['override', 'matrix', 'manual', 'none'])
                ->comment('수수료율이 어디에서 해석됐는지');
            $table->foreignId('price_override_id')->nullable()
                ->constrained('company_product_overrides')->nullOnDelete();
            $table->foreignId('price_id')->nullable()
                ->constrained('product_prices')->nullOnDelete();
            $table->foreignId('commission_rate_id')->nullable()
                ->constrained('product_commission_rates')->nullOnDelete();

            $table->string('note', 500)->nullable();

            // 상태 머신
            $table->enum('status', [
                'draft', 'submitted', 'reviewed', 'approved', 'rejected', 'cancelled',
            ])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejected_reason', 500)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['performance_date', 'company_id', 'status'], 'perf_date_company_idx');
            $table->index(['company_id', 'status', 'performance_date'], 'perf_company_status_idx');
            $table->index(['product_id', 'performance_date'], 'perf_product_date_idx');
        });

        // 가상 컬럼(VIRTUAL STORED) — MySQL/MariaDB
        // subtotal = quantity * unit_price
        // commission_amount = subtotal * commission_rate / 100  (rate NULL 이면 NULL)
        \DB::statement(<<<'SQL'
            ALTER TABLE `performances`
                ADD COLUMN `subtotal` DECIMAL(14,2)
                    AS (`quantity` * `unit_price`) STORED
                    COMMENT 'quantity * unit_price (DB-generated)'
                    AFTER `unit_price`
        SQL);

        \DB::statement(<<<'SQL'
            ALTER TABLE `performances`
                ADD COLUMN `commission_amount` DECIMAL(14,2)
                    AS (CASE WHEN `commission_rate` IS NULL THEN NULL
                             ELSE (`quantity` * `unit_price`) * `commission_rate` / 100 END) STORED
                    COMMENT 'subtotal * commission_rate/100 (DB-generated)'
                    AFTER `commission_rate`
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('performances');
    }
};
