<?php

namespace App\Http\Requests\Platform;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * 플랫폼(platform) 사용자 수정 — pharma·cso 계정. (GAP-10 후속-C §6.10)
 * 대상이 platform 계정인 경우는 컨트롤러 가드에서 차단(artisan 전용). 비밀번호는 비우면 유지.
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatform() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('password') === null || $this->input('password') === '') {
            $this->request->remove('password');
            $this->request->remove('password_confirmation');
        }
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $id = $user instanceof User ? $user->id : (int) $user;

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
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
