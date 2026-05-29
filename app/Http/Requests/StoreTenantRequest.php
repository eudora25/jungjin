<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '제약사명',
            'code' => '코드',
            'business_registration_number' => '사업자등록번호',
            'status' => '상태',
        ];
    }
}
