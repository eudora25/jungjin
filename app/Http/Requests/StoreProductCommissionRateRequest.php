<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductCommissionRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'base_month' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'commission_rate_a' => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_rate_b' => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_rate_c' => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_rate_d' => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_rate_e' => ['required', 'numeric', 'min:0', 'max:100'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'base_month' => '기준월',
            'commission_rate_a' => 'A등급 수수료율',
            'commission_rate_b' => 'B등급 수수료율',
            'commission_rate_c' => 'C등급 수수료율',
            'commission_rate_d' => 'D등급 수수료율',
            'commission_rate_e' => 'E등급 수수료율',
            'effective_from' => '적용 시작일',
            'effective_to' => '적용 종료일',
            'status' => '상태',
        ];
    }
}
