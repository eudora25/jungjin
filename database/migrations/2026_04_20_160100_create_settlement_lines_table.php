<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('settlements')->cascadeOnDelete();
            $table->foreignId('performance_id')->constrained('performances')->restrictOnDelete();
            $table->decimal('snapshot_unit_price', 12, 2);
            $table->decimal('snapshot_commission_rate', 5, 2)->nullable();
            $table->integer('quantity');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('commission_amount', 14, 2)->nullable();
            $table->timestamps();

            $table->unique(['settlement_id', 'performance_id'], 'settlement_lines_settlement_perf_unique');
            $table->index('performance_id', 'settlement_lines_performance_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_lines');
    }
};
