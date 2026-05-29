<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesQuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPharma() ?? false;
    }

    public function rules(): array
    {
        $quotaId = $this->route('sales_quota')?->id;

        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'period_type' => ['required', 'string', 'in:monthly,quarterly,yearly'],
            'period' => [
                'required',
                'string',
                'max:10',
                function (string $attribute, mixed $value, Closure $fail) {
                    $type = $this->input('period_type');
                    $valid = match ($type) {
                        'monthly' => (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value),
                        'quarterly' => (bool) preg_match('/^\d{4}-Q[1-4]$/', $value),
                        'yearly' => (bool) preg_match('/^\d{4}$/', $value),
                        default => false,
                    };
                    if (! $valid) {
                        $fail('기간 형식이 올바르지 않습니다. (월: 2026-04, 분기: 2026-Q2, 연간: 2026)');
                    }
                },
                Rule::unique('sales_quotas', 'period')
                    ->where('user_id', $this->input('user_id'))
                    ->where('period_type', $this->input('period_type'))
                    ->where(fn ($q) => $this->input('product_id')
                        ? $q->where('product_id', $this->input('product_id'))
                        : $q->whereNull('product_id')
                    )
                    ->ignore($quotaId),
            ],
            'target_amount' => ['required', 'numeric', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => '영업사원을 선택해 주세요.',
            'period_type.required' => '기간 유형을 선택해 주세요.',
            'period.required' => '기간을 입력해 주세요.',
            'period.unique' => '동일한 조건의 목표가 이미 존재합니다.',
            'target_amount.required' => '목표 금액을 입력해 주세요.',
            'target_amount.min' => '목표 금액은 1원 이상이어야 합니다.',
        ];
    }
}
