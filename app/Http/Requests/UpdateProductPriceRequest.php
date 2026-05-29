<?php

namespace App\Http\Requests;

use App\Models\ProductPrice;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProductPrice|null $price */
        $price = $this->route('price');

        return $price !== null
            && ($this->user()?->can('update', $price) ?? false);
    }

    public function rules(): array
    {
        /** @var ProductPrice $price */
        $price = $this->route('price');

        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:'.$price->effective_from->toDateString()],
            'source' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'amount' => '금액',
            'effective_to' => '적용 종료일',
            'source' => '근거',
            'note' => '비고',
        ];
    }
}
