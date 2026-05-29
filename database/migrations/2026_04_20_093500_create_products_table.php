<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->comment('제품 (의약품) 마스터');
            $table->id()->comment('제품 PK');
            $table->string('insurance_code', 50)->index()->comment('보험코드 (의료보험 급여코드)');
            $table->string('product_code', 50)->index()->comment('제품코드 (사내 관리용)');
            $table->string('product_name')->index()->comment('제품명');
            $table->string('manufacturer', 100)->nullable()->index()->comment('제조사명');
            $table->string('category', 100)->nullable()->index()->comment('제품 카테고리');
            $table->text('description')->nullable()->comment('제품 설명');
            $table->string('image_path')->nullable()->comment('제품 이미지 스토리지 상대 경로');
            $table->decimal('price', 12, 2)->nullable()->comment('약가 (원)');
            $table->enum('status', ['active', 'inactive', 'discontinued'])
                ->default('active')
                ->index()
                ->comment('제품 상태 (active/inactive/discontinued)');
            $table->text('remarks')->nullable()->comment('비고');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('등록자 FK (users.id)');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->comment('최종 수정자 FK (users.id)');
            $table->timestamps();
            $table->softDeletes()->comment('삭제 일시 (NULL이면 활성)');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
