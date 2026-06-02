<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 제약사(tenant) 사업자 정보 보강 — 대표자·소재지·연락처. (파일 첨부는 후속)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('representative_name', 100)->nullable()->after('business_registration_number');
            $table->string('postcode', 10)->nullable()->after('representative_name');
            $table->string('address')->nullable()->after('postcode');
            $table->string('phone', 30)->nullable()->after('address');
            $table->string('email')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['representative_name', 'postcode', 'address', 'phone', 'email']);
        });
    }
};
