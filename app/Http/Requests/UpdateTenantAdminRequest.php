<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 위임형(D-2) — super_admin 이 특정 제약사의 admin 계정을 수정 (성명·이메일).
 * 비밀번호 변경은 별도 '비밀번호 재설정'(resetAdminPassword)에서 처리.
 */
class UpdateTenantAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageAdmins', $this->route('tenant')) ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '성명',
            'email' => '이메일',
        ];
    }
}
