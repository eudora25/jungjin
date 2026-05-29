<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiscontinueProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('discontinue', $this->route('product')) ?? false;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'replacement_product_id' => [
                'nullable',
                'integer',
                Rule::notIn([$productId]),
                Rule::exists('products', 'id')->whereNull('deleted_at'),
            ],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'replacement_product_id' => '대체품',
            'reason' => '단종 사유',
        ];
    }
}
