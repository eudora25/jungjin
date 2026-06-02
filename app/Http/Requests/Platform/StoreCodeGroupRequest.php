<?php

namespace App\Http\Requests\Platform;

use App\Models\CodeGroup;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 플랫폼 — 코드 그룹 등록. (GAP-10)
 */
class StoreCodeGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CodeGroup::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'group_code' => trim((string) $this->input('group_code')),
            'sort_order' => $this->input('sort_order', 0),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    public function rules(): array
    {
        return [
            'group_code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', 'unique:code_groups,group_code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'group_code' => '코드 그룹 값',
            'name' => '그룹 라벨',
            'description' => '설명',
            'sort_order' => '정렬 순서',
            'is_active' => '활성 여부',
        ];
    }

    public function messages(): array
    {
        return [
            'group_code.regex' => '코드 그룹 값은 영문 소문자·숫자·밑줄(_)만 사용할 수 있습니다.',
        ];
    }
}
