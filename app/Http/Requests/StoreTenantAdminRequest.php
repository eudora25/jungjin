<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * 위임형(D-2) — super_admin 이 특정 제약사의 admin 계정을 생성.
 */
class StoreTenantAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageAdmins', $this->route('tenant')) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '성명',
            'email' => '이메일',
            'password' => '비밀번호',
        ];
    }
}
