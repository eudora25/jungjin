<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('product')) ?? false;
    }

    public function rules(): array
    {
        /** @var Product|null $product */
        $product = $this->route('product');
        $productId = $product?->id;

        return [
            // --- 식별 ---
            'insurance_code' => [
                'required', 'string', 'max:50',
                Rule::unique('products', 'insurance_code')->ignore($productId),
            ],
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
            'status' => ['sometimes', Rule::in([
                Product::STATUS_ACTIVE,
                Product::STATUS_INACTIVE,
                Product::STATUS_DISCONTINUED,
            ])],

            // --- 대체품 (선택) — 자기 자신 지정 금지 ---
            'replacement_product_id' => [
                'nullable',
                'integer',
                Rule::notIn([$productId]),
                Rule::exists('products', 'id')->whereNull('deleted_at'),
            ],

            // --- 비고/이미지 ---
            'remarks' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],

            // --- 마약/향정 변경 사유 (after 규칙에서 조건부 required 검사) ---
            'change_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return array_merge((new StoreProductRequest)->attributes(), [
            'remove_image' => '이미지 제거',
            'change_reason' => '변경 사유',
        ]);
    }

    /**
     * 마약/향정 정책: 다음 경우 `change_reason` 필수
     *  1) 기존 또는 신규 `drug_type`이 마약/향정인 경우
     *  2) `nims_managed`인 제품의 핵심 컬럼이 변경되는 경우 (이름/성분/함량/단위/제조사/대체품/단종 전환)
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            /** @var Product|null $product */
            $product = $this->route('product');
            if (! $product) {
                return;
            }

            $newDrugType = $this->input('drug_type', $product->drug_type);
            $isNimsRelated = in_array($product->drug_type, Product::NIMS_DRUG_TYPES, true)
                || in_array($newDrugType, Product::NIMS_DRUG_TYPES, true)
                || (bool) $product->nims_managed;

            if (! $isNimsRelated) {
                return;
            }

            $coreFields = [
                'product_name',
                'generic_name',
                'strength',
                'unit',
                'pack_size',
                'manufacturer',
                'drug_type',
                'storage_condition',
                'nims_item_code',
                'replacement_product_id',
                'status',
            ];

            $changed = false;
            foreach ($coreFields as $field) {
                if (! $this->has($field)) {
                    continue;
                }
                $newValue = $this->input($field);
                $oldValue = $product->{$field};
                if ((string) $newValue !== (string) $oldValue) {
                    $changed = true;
                    break;
                }
            }

            if ($changed && ! filled($this->input('change_reason'))) {
                $v->errors()->add(
                    'change_reason',
                    '마약/향정 관리대상 제품의 핵심 정보를 변경할 때는 변경 사유를 반드시 입력해야 합니다.',
                );
            }
        });
    }
}
