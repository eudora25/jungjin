<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacies', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->unique()
                ->constrained('companies')
                ->nullOnDelete()
                ->after('id')
                ->comment('통합 거래처(Company) FK (약국을 companies로 통합할 때 사용)');
        });
    }

    public function down(): void
    {
        Schema::table('pharmacies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};

