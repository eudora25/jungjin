<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_commission_rates', function (Blueprint $table) {
            $table->comment('제품×기준월별 등급(A~E) 수수료율 매트릭스');
            $table->id()->comment('수수료율 레코드 PK');
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete()
                ->comment('제품 FK (products.id)');
            $table->char('base_month', 7)->index()->comment('기준월 (YYYY-MM)');
            $table->decimal('commission_rate_a', 5, 2)->default(0)->comment('A등급 수수료율 (%)');
            $table->decimal('commission_rate_b', 5, 2)->default(0)->comment('B등급 수수료율 (%)');
            $table->decimal('commission_rate_c', 5, 2)->default(0)->comment('C등급 수수료율 (%)');
            $table->decimal('commission_rate_d', 5, 2)->default(0)->comment('D등급 수수료율 (%)');
            $table->decimal('commission_rate_e', 5, 2)->default(0)->comment('E등급 수수료율 (%)');
            $table->date('effective_from')->comment('수수료율 적용 시작일');
            $table->date('effective_to')->nullable()->comment('수수료율 적용 종료일 (NULL = 무제한)');
            $table->enum('status', ['active', 'inactive'])->default('active')->index()->comment('레코드 활성 상태');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('등록자 FK (users.id)');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->comment('최종 수정자 FK (users.id)');
            $table->timestamps();

            $table->unique(['product_id', 'base_month', 'effective_from'], 'pcr_unique_per_product_month');
            $table->index(['effective_from', 'effective_to'], 'pcr_effective_range');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_commission_rates');
    }
};
