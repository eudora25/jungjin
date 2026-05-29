<?php

namespace App\Http\Requests;

use App\Models\Settlement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GAP-5: 정산 지급 처리 요청.
 *
 * 지급일(`paid_on`) 은 필수, 지급 수단·Batch·메모는 선택.
 * confirm 상태인 정산만 지급 처리 가능 (Policy 에서 별도 검증).
 */
class PaySettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $settlement = $this->route('settlement');

        return $settlement instanceof Settlement
            && ($this->user()?->can('pay', $settlement) ?? false);
    }

    public function rules(): array
    {
        return [
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['nullable', Rule::in(Settlement::PAYMENT_METHODS)],
            'payment_batch_no' => ['nullable', 'string', 'max:50'],
            'payment_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'paid_on.required' => '지급일을 입력해 주세요.',
            'paid_on.before_or_equal' => '지급일은 오늘 이전 날짜여야 합니다.',
            'payment_method.in' => '지급 수단이 올바르지 않습니다.',
        ];
    }
}
