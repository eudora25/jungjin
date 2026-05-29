<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 식약처·건강보험 공개 「개별의약품 목록」 엑셀을 1행 1레코드로 적재하는 스테이징/레퍼런스 테이블.
     *
     * 품목기준코드는 동일 코드가 여러 행에 존재할 수 있어 unique로 두지 않음.
     * 동일 파일 재적재 시 행 단위 식별을 위해 (스냅샷일, 원본 엑셀 행번호) unique.
     */
    public function up(): void
    {
        Schema::create('health_individual_drugs', function (Blueprint $table) {
            $table->comment('개별의약품 목록(공개 엑셀) 원본 적재');
            $table->id()->comment('PK');

            $table->date('data_snapshot_date')->comment('데이터 기준일(파일명·배치에서 설정)');
            $table->unsignedInteger('source_row_number')->comment('엑셀 시트 상 행 번호(헤더=1)');

            $table->string('item_code', 30)->nullable()->index()->comment('품목기준코드');
            $table->string('drug_name_rule_name', 255)->nullable()->comment('의약품명 적용 규칙명');
            $table->string('drug_creation_rule_name', 255)->nullable()->comment('의약품 생성 적용 규칙명');
            $table->string('representative_standard_code', 50)->nullable()->index()->comment('대표표준코드');
            $table->string('individual_drug_name', 500)->nullable()->index()->comment('개별 의약품명');
            $table->string('licensed_product_name', 500)->nullable()->comment('의약품허가품목명');
            $table->string('manufacturer_name', 200)->nullable()->index()->comment('제약업체명');
            $table->string('business_registration_number', 20)->nullable()->comment('사업자등록번호');
            $table->string('company_status', 50)->nullable()->comment('업체상태');
            $table->string('license_item_number', 50)->nullable()->index()->comment('품목허가번호');
            $table->date('license_date')->nullable()->comment('품목허가일자');
            $table->string('cancel_status', 50)->nullable()->comment('취소/취하상태');
            $table->date('cancel_date')->nullable()->comment('취소/취하일자');
            $table->string('item_category', 50)->nullable()->comment('품목구분');
            $table->string('rx_otc_type', 20)->nullable()->comment('전문일반구분');
            $table->string('report_permit_type', 20)->nullable()->comment('신고허가구분');
            $table->string('finished_material_type', 20)->nullable()->comment('완제원료구분');
            $table->string('narcotic_type', 50)->nullable()->comment('마약류구분');
            $table->string('efficacy_class', 100)->nullable()->comment('약효분류');
            $table->string('atc_code', 20)->nullable()->index()->comment('ATC코드');
            $table->text('main_ingredient_name')->nullable()->comment('주성분명');
            $table->string('main_ingredient_count', 20)->nullable()->comment('주성분수');
            $table->string('is_new_drug', 20)->nullable()->comment('신약여부');
            $table->string('is_rare_drug', 20)->nullable()->comment('희귀의약품여부');
            $table->string('main_ingredient_code', 100)->nullable()->comment('주성분코드');
            $table->string('current_insurance_code', 50)->nullable()->index()->comment('현재보험코드');
            $table->decimal('current_insurance_price', 14, 2)->nullable()->comment('현재보험약가');
            $table->date('current_insurance_price_start_date')->nullable()->comment('현재보험약가적용시작일자');
            $table->string('reimbursement_mapping_status', 50)->nullable()->comment('급여매핑상태');
            $table->string('reference_drug_flag', 20)->nullable()->comment('대조약여부');
            $table->string('reference_drug_type_name', 200)->nullable()->comment('대조약구분명');
            $table->date('reference_drug_notice_date')->nullable()->comment('대조약공고일자');
            $table->string('bioequivalence_flag', 20)->nullable()->comment('생동성인정품목여부');
            $table->date('bioequivalence_notice_date')->nullable()->comment('생동성인정품목공고일자');

            $table->string('source_file_name', 255)->nullable()->comment('원본 파일명');
            $table->timestamp('imported_at')->nullable()->comment('DB 적재 시각');

            $table->timestamps();

            $table->unique(
                ['data_snapshot_date', 'source_row_number'],
                'health_individual_drugs_snapshot_row_unique'
            );
            $table->index(
                ['item_code', 'current_insurance_code'],
                'health_individual_drugs_item_insurance_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_individual_drugs');
    }
};
