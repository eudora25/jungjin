<?php

namespace App\Http\Requests\Platform;

use App\Http\Requests\Concerns\NormalizesBusinessNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 플랫폼 — 병의원·약국 사업자등록번호 변경 (이력 기록). (GAP-10)
 * 대상 엔티티는 라우트 파라미터(hospital | pharmacy)에서 해석한다.
 */
class ChangeBusinessNumberRequest extends FormRequest
{
    use NormalizesBusinessNumber;

    private function entity(): ?Model
    {
        return $this->route('hospital') ?? $this->route('pharmacy');
    }

    public function authorize(): bool
    {
        $entity = $this->entity();

        return $entity !== null && ($this->user()?->can('update', $entity) ?? false);
    }

    protected function prepareForValidation(): void
    {
        // 숫자만 정규화 (하이픈 무관) — 현재 번호와의 비교·유니크 검사가 숫자 기준으로 동작
        $this->normalizeBusinessNumber('new_business_registration_number');
    }

    public function rules(): array
    {
        $entity = $this->entity();
        $table = $entity->getTable();

        return [
            'new_business_registration_number' => [
                'required', 'string', 'max:20',
                Rule::unique($table, 'business_registration_number')->ignore($entity->getKey())->whereNull('deleted_at'),
                function ($attribute, $value, $fail) use ($entity) {
                    if ($value !== '' && $value === $entity->business_registration_number) {
                        $fail('현재 사업자등록번호와 동일합니다. 다른 번호를 입력하세요.');
                    }
                },
            ],
            'valid_from' => ['nullable', 'date'],
            'previous_valid_to' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'new_business_registration_number' => '새 사업자등록번호',
            'valid_from' => '새 번호 적용 시작일',
            'previous_valid_to' => '이전 번호 종료일',
            'reason' => '변경 사유',
            'note' => '비고',
        ];
    }

    public function messages(): array
    {
        return [
            'new_business_registration_number.unique' => '이미 다른 곳에 등록된 사업자등록번호입니다.',
        ];
    }
}
