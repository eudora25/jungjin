<?php

namespace App\Http\Requests\Platform;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * 플랫폼(platform) 사용자 등록 — 모든 제약사의 pharma·cso 계정을 만든다. (GAP-10 후속-C §6.10)
 * `platform` 역할은 UI 로 만들지 않는다(artisan 승격 — C-5). 생성 시 대상 제약사(`tenant_id`) 필수.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatform() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in([User::ROLE_PHARMA, User::ROLE_CSO])],
            'tenant_id' => ['required', 'integer', Rule::exists('tenants', 'id')->whereNull('deleted_at')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '성명',
            'email' => '이메일',
            'password' => '비밀번호',
            'role' => '권한',
            'tenant_id' => '제약사',
            'is_active' => '활성 여부',
        ];
    }
}
