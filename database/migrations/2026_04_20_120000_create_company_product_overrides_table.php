<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_product_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('override_unit_price', 12, 2)->nullable()
                ->comment('거래처 전용 단가 — 비어있으면 product_prices(sale) 적용');
            $table->decimal('override_commission_rate', 5, 2)->nullable()
                ->comment('거래처 전용 수수료율 — 비어있으면 등급/표준 매트릭스 적용');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('reason', 500)->nullable()->comment('예외 적용 사유');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'product_id', 'effective_from'], 'cpo_unique_eff');
            $table->index(['company_id', 'product_id', 'effective_from', 'effective_to'], 'cpo_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_product_overrides');
    }
};
