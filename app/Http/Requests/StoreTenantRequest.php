<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Tenant::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('tenants', 'code')],
            'business_registration_number' => ['nullable', 'string', 'max:20', Rule::unique('tenants', 'business_registration_number')],
            'status' => ['required', Rule::in([Tenant::STATUS_ACTIVE, Tenant::STATUS_INACTIVE])],

            // 제약사 등록 시 초기 관리자(pharma) 계정을 함께 생성 (D-2 위임형)
            'admin_name' => ['required', 'string', 'max:100'],
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'admin_password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '제약사명',
            'code' => '코드',
            'business_registration_number' => '사업자등록번호',
            'status' => '상태',
            'admin_name' => '관리자 성명',
            'admin_email' => '관리자 이메일',
            'admin_password' => '관리자 비밀번호',
        ];
    }
}
