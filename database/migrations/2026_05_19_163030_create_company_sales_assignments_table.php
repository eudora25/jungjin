<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_sales_assignments', function (Blueprint $table) {
            $table->comment('영업사원-거래처 담당 배정 (GAP-4)');
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'user_id'], 'csa_company_user_unique');
            $table->index('user_id', 'csa_user_idx');
            $table->index('company_id', 'csa_company_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_sales_assignments');
    }
};
