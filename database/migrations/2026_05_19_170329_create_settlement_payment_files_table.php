<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-5-2: 정산 지급 증빙 파일 첨부.
 * 이체확인서 / 지급내역 캡처 등 (PDF/이미지). private 디스크 저장.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_payment_files', function (Blueprint $table) {
            $table->comment('정산 지급 증빙 파일 (GAP-5)');
            $table->id();
            $table->foreignId('settlement_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('path');
            $table->unsignedBigInteger('size');
            $table->string('mime_type', 100);
            $table->string('extension', 20)->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_payment_files');
    }
};
