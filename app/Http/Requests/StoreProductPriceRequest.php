<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Product|null $product */
        $product = $this->route('product');

        return $product !== null
            && ($this->user()?->can('create', ProductPrice::class) ?? false);
    }

    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'price_type' => ['required', Rule::in(ProductPrice::TYPES)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'source' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            /** @var Product $product */
            $product = $this->route('product');
            $type = $this->input('price_type');
            $from = $this->input('effective_from');

            $exists = $product->prices()
                ->ofType($type)
                ->whereDate('effective_from', $from)
                ->exists();

            if ($exists) {
                $v->errors()->add(
                    'effective_from',
                    '동일한 적용 시작일에 이미 가격이 등록되어 있습니다. (가격 종류 + 시작일 조합 unique)',
                );
            }
        });
    }

    public function attributes(): array
    {
        return [
            'price_type' => '가격 종류',
            'amount' => '금액',
            'effective_from' => '적용 시작일',
            'effective_to' => '적용 종료일',
            'source' => '근거',
            'note' => '비고',
        ];
    }
}
