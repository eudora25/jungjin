<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospital_pharmacy_assignments', function (Blueprint $table) {
            $table->comment('병의원 ↔ 약국 연결(레거시 hospital_pharmacy_assignments 이관용)');
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('pharmacy_id')->constrained('pharmacies')->cascadeOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();

            $table->unique(['hospital_id', 'pharmacy_id'], 'hpa_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_pharmacy_assignments');
    }
};

