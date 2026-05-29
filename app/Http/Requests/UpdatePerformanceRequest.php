<?php

namespace App\Http\Requests;

use App\Models\Performance;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePerformanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Performance|null $perf */
        $perf = $this->route('performance');

        return $perf !== null && ($this->user()?->can('update', $perf) ?? false);
    }

    public function rules(): array
    {
        return [
            // 수정에서 허용하는 것: 수량, 비고, 실적일 (실적일 변경 시 스냅샷은 재해석)
            'performance_date' => ['required', 'date', 'before_or_equal:today'],
            'quantity' => ['required', 'integer', 'not_in:0'],
            'note' => ['nullable', 'string', 'max:500'],
            // company/product 변경은 draft 삭제 후 새로 등록하는 게 더 명확 — 본 버전에서는 금지
        ];
    }

    public function attributes(): array
    {
        return [
            'performance_date' => '실적 발생일',
            'quantity' => '수량',
            'note' => '비고',
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.not_in' => '수량은 0일 수 없습니다.',
        ];
    }
}
