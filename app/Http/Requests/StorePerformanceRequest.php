<?php

namespace App\Http\Requests;

use App\Models\Performance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePerformanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Performance::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'performance_date' => ['required', 'date', 'before_or_equal:today'],
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'integer', 'not_in:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'performance_date' => '실적 발생일',
            'company_id' => '거래처',
            'product_id' => '제품',
            'quantity' => '수량',
            'note' => '비고',
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.not_in' => '수량은 0일 수 없습니다. (반품은 음수로 입력)',
        ];
    }
}
