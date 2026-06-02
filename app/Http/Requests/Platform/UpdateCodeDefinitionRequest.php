<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 플랫폼 — 코드 정의 수정. (GAP-10)
 */
class UpdateCodeDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageDefinitions', $this->route('codeGroup')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => trim((string) $this->input('code')),
            'sort_order' => $this->input('sort_order', 0),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('code_definitions', 'code')
                    ->where('group_code', $this->route('codeGroup')->group_code)
                    ->ignore($this->route('definition')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => '코드 값',
            'name' => '코드 라벨',
            'description' => '설명',
            'sort_order' => '정렬 순서',
            'is_active' => '활성 여부',
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => '코드 값은 영문 소문자·숫자·밑줄(_)만 사용할 수 있습니다.',
            'code.unique' => '이 코드 그룹에 이미 같은 코드 값이 있습니다.',
        ];
    }
}
