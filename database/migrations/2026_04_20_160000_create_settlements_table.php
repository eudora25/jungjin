<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_no', 32)->unique()
                ->comment('정산 번호 (예: 202604-000001)');
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->char('period_month', 7)
                ->comment('정산 대상 월 YYYY-MM');
            $table->enum('status', ['draft', 'confirmed', 'paid', 'cancelled'])->default('draft');
            $table->unsignedInteger('line_count')->default(0);
            $table->bigInteger('total_quantity')->default(0);
            $table->decimal('total_subtotal', 16, 2)->default(0);
            $table->decimal('total_commission', 16, 2)->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'period_month'], 'settlements_company_period_unique');
            $table->index(['period_month', 'status'], 'settlements_period_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
