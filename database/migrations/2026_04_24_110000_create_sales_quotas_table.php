<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->enum('period_type', ['monthly', 'quarterly', 'yearly']);
            $table->string('period', 10); // 2026-04 / 2026-Q2 / 2026
            $table->decimal('target_amount', 14, 2);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['user_id', 'period_type', 'period']);
            $table->index(['product_id', 'period_type', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_quotas');
    }
};
