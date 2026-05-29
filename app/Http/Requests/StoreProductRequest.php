<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPharma() ?? false;
    }

    public function rules(): array
    {
        return [
            // --- 식별 ---
            'insurance_code' => ['required', 'string', 'max:50', Rule::unique('products', 'insurance_code')],
            'standard_code' => ['nullable', 'string', 'max:50'],
            'barcode_gtin' => ['nullable', 'string', 'max:20'],
            'product_code' => ['required', 'string', 'max:50'],
            'product_name' => ['required', 'string', 'max:255'],
            'generic_name' => ['nullable', 'string', 'max:150'],
            'strength' => ['nullable', 'string', 'max:50'],
            'unit' => ['nullable', 'string', 'max:20'],
            'pack_size' => ['nullable', 'integer', 'min:1'],

            // --- 분류·보관 ---
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'drug_type' => ['sometimes', Rule::in([
                Product::DRUG_TYPE_GENERAL,
                Product::DRUG_TYPE_ETC,
                Product::DRUG_TYPE_NARCOTIC,
                Product::DRUG_TYPE_PSYCHOTROPIC,
            ])],
            'storage_condition' => ['sometimes', Rule::in(['room', 'cold', 'frozen'])],

            // --- NIMS ---
            'nims_item_code' => ['nullable', 'string', 'max:50'],

            // --- 본문/표시 ---
            'description' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],

            // --- 상태 ---
            // approval_status 는 컨트롤러에서 'draft'로 강제. 입력은 무시.
            'status' => ['sometimes', Rule::in([
                Product::STATUS_ACTIVE,
                Product::STATUS_INACTIVE,
                Product::STATUS_DISCONTINUED,
            ])],

            // --- 단종 시 대체품 (선택) ---
            'replacement_product_id' => ['nullable', 'integer', 'different:id', Rule::exists('products', 'id')->whereNull('deleted_at')],

            // --- 비고/이미지 ---
            'remarks' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'insurance_code' => '보험코드',
            'standard_code' => '표준코드',
            'barcode_gtin' => '바코드(GTIN)',
            'product_code' => '제품코드',
            'product_name' => '제품명',
            'generic_name' => '성분명',
            'strength' => '함량',
            'unit' => '단위',
            'pack_size' => '포장 수량',
            'manufacturer' => '제조사',
            'category' => '카테고리',
            'drug_type' => '약품 유형',
            'storage_condition' => '보관 조건',
            'nims_item_code' => 'NIMS 품목코드',
            'description' => '제품 설명',
            'price' => '약가',
            'status' => '상태',
            'replacement_product_id' => '대체품',
            'remarks' => '비고',
            'image' => '제품 이미지',
        ];
    }
}
