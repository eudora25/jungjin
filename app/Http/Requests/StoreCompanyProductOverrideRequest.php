<?php

namespace App\Http\Requests;

use App\Models\CompanyProductOverride;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyProductOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Product|null $product */
        $product = $this->route('product');

        return $product !== null
            && ($this->user()?->can('create', CompanyProductOverride::class) ?? false);
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'override_unit_price' => ['nullable', 'numeric', 'gt:0', 'max:9999999999.99'],
            'override_commission_rate' => ['nullable', 'numeric', 'gte:0', 'lte:100'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $unit = $this->input('override_unit_price');
            $rate = $this->input('override_commission_rate');
            if (($unit === null || $unit === '') && ($rate === null || $rate === '')) {
                $v->errors()->add(
                    'override_unit_price',
                    '단가 예외 또는 수수료율 예외 중 최소 1개는 입력해야 합니다.',
                );
            }

            /** @var Product $product */
            $product = $this->route('product');
            $exists = $product->companyOverrides()
                ->forCompany((int) $this->input('company_id'))
                ->whereDate('effective_from', $this->input('effective_from'))
                ->exists();

            if ($exists) {
                $v->errors()->add(
                    'effective_from',
                    '같은 거래처 + 시작일 조합의 예외가 이미 존재합니다.',
                );
            }
        });
    }

    public function attributes(): array
    {
        return [
            'company_id' => '거래처',
            'override_unit_price' => '예외 단가',
            'override_commission_rate' => '예외 수수료율',
            'effective_from' => '적용 시작일',
            'effective_to' => '적용 종료일',
            'reason' => '사유',
        ];
    }
}
