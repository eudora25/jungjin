<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospital_company_assignments', function (Blueprint $table) {
            $table->comment('병의원 ↔ 업체 연결(레거시 hospital_company_assignments 이관용)');
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->enum('commission_grade', ['A', 'B', 'C', 'D', 'E'])->default('E')->index();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();

            $table->unique(['hospital_id', 'company_id'], 'hca_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_company_assignments');
    }
};

