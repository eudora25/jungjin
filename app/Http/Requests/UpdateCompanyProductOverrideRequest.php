<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyProductOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        $override = $this->route('override');

        return $override !== null
            && ($this->user()?->can('update', $override) ?? false);
    }

    public function rules(): array
    {
        return [
            'override_unit_price' => ['nullable', 'numeric', 'gt:0', 'max:9999999999.99'],
            'override_commission_rate' => ['nullable', 'numeric', 'gte:0', 'lte:100'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $override = $this->route('override');
        if ($override) {
            $this->merge([
                'effective_from' => $override->effective_from->toDateString(),
            ]);
        }
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
        });
    }

    public function attributes(): array
    {
        return [
            'override_unit_price' => '예외 단가',
            'override_commission_rate' => '예외 수수료율',
            'effective_to' => '적용 종료일',
            'reason' => '사유',
        ];
    }
}
