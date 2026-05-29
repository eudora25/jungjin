<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 공개 「개별의약품 목록」 엑셀 행을 그대로 보관하는 스테이징/레퍼런스 모델.
 */
class HealthIndividualDrug extends Model
{
    protected $table = 'health_individual_drugs';

    protected $fillable = [
        'data_snapshot_date',
        'source_row_number',
        'item_code',
        'drug_name_rule_name',
        'drug_creation_rule_name',
        'representative_standard_code',
        'individual_drug_name',
        'licensed_product_name',
        'manufacturer_name',
        'business_registration_number',
        'company_status',
        'license_item_number',
        'license_date',
        'cancel_status',
        'cancel_date',
        'item_category',
        'rx_otc_type',
        'report_permit_type',
        'finished_material_type',
        'narcotic_type',
        'efficacy_class',
        'atc_code',
        'main_ingredient_name',
        'main_ingredient_count',
        'is_new_drug',
        'is_rare_drug',
        'main_ingredient_code',
        'current_insurance_code',
        'current_insurance_price',
        'current_insurance_price_start_date',
        'reimbursement_mapping_status',
        'reference_drug_flag',
        'reference_drug_type_name',
        'reference_drug_notice_date',
        'bioequivalence_flag',
        'bioequivalence_notice_date',
        'source_file_name',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'data_snapshot_date' => 'date',
            'license_date' => 'date',
            'cancel_date' => 'date',
            'current_insurance_price_start_date' => 'date',
            'reference_drug_notice_date' => 'date',
            'bioequivalence_notice_date' => 'date',
            'current_insurance_price' => 'decimal:2',
            'imported_at' => 'datetime',
        ];
    }
}
